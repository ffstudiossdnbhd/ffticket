using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using FFTicket.Desktop.Models;

namespace FFTicket.Desktop.Services;

public sealed class AuthService : IAuthService
{
    private readonly IApiService _apiService;
    private readonly string _appDataDirectory = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "FFTicket");
    private readonly string _sessionPath;
    private readonly string _devicePath;
    private readonly SemaphoreSlim _refreshLock = new(1, 1);
    private string? _deviceId;
    private string? _refreshToken;
    private bool _rememberSession;

    private sealed class StoredSession
    {
        public string Token { get; set; } = "";
        public string RefreshToken { get; set; } = "";
        public string DeviceId { get; set; } = "";
        public User User { get; set; } = new();
    }

    public AuthService(IApiService apiService)
    {
        _apiService = apiService;
        _sessionPath = Path.Combine(_appDataDirectory, "session.json");
        _devicePath = Path.Combine(_appDataDirectory, "device-id.txt");
        apiService.ConfigureSessionRefresh(RefreshSessionAsync);
    }

    public User? CurrentUser { get; private set; }
    public string DeviceId => _deviceId ??= GetOrCreateDeviceId();
    public event Action? SessionInvalidated;

    public async Task<ApiResponse<User>> LoginAsync(string email, string password, bool rememberMe)
    {
        var response = await _apiService.PostJsonAsync<DesktopSessionResult>(
            "auth/desktop-login.php",
            new { email, password, device_id = DeviceId });
        if (!response.IsSuccess || response.Data == null)
        {
            return ApiResponse<User>.Failure(response.Message, response.StatusCode);
        }

        if (
            string.IsNullOrWhiteSpace(response.Data.Token) ||
            string.IsNullOrWhiteSpace(response.Data.RefreshToken) ||
            !Guid.TryParse(response.Data.DeviceId, out _) ||
            !string.Equals(response.Data.DeviceId, DeviceId, StringComparison.OrdinalIgnoreCase)
        )
        {
            return ApiResponse<User>.Failure("The API returned an invalid desktop session.");
        }

        _apiService.Token = response.Data.Token;
        _refreshToken = response.Data.RefreshToken;
        CurrentUser = response.Data.User;
        _rememberSession = rememberMe;

        if (rememberMe)
        {
            await PersistSessionAsync();
        }
        else
        {
            RemoveStoredSession();
        }

        return ApiResponse<User>.Success(CurrentUser, response.Message, response.StatusCode);
    }

    public async Task<bool> TryRestoreSessionAsync()
    {
        try
        {
            if (!File.Exists(_sessionPath))
            {
                return false;
            }

            var protectedBytes = Convert.FromBase64String(await File.ReadAllTextAsync(_sessionPath));
            var json = Encoding.UTF8.GetString(ProtectedData.Unprotect(
                protectedBytes,
                optionalEntropy: null,
                DataProtectionScope.CurrentUser));
            var session = JsonSerializer.Deserialize<StoredSession>(json);
            if (
                session == null ||
                string.IsNullOrWhiteSpace(session.Token) ||
                string.IsNullOrWhiteSpace(session.RefreshToken) ||
                !Guid.TryParse(session.DeviceId, out _) ||
                !string.Equals(session.DeviceId, DeviceId, StringComparison.OrdinalIgnoreCase)
            )
            {
                RemoveStoredSession();
                return false;
            }

            _apiService.Token = session.Token;
            _refreshToken = session.RefreshToken;
            CurrentUser = session.User;
            _rememberSession = true;

            await RefreshSessionAsync();
            return CurrentUser != null;
        }
        catch
        {
            ClearLocalSession(raiseInvalidated: false);
            return false;
        }
    }

    public async Task<bool> RefreshSessionAsync(CancellationToken cancellationToken = default)
    {
        if (CurrentUser == null || string.IsNullOrWhiteSpace(_refreshToken))
        {
            return false;
        }

        await _refreshLock.WaitAsync(cancellationToken);
        try
        {
            if (CurrentUser == null || string.IsNullOrWhiteSpace(_refreshToken))
            {
                return false;
            }

            var response = await _apiService.PostJsonAsync<DesktopSessionResult>(
                "auth/refresh.php",
                new { device_id = DeviceId, refresh_token = _refreshToken },
                cancellationToken);
            if (!response.IsSuccess || response.Data == null)
            {
                if (response.StatusCode is 401 or 422)
                {
                    ClearLocalSession(raiseInvalidated: true);
                }
                return false;
            }

            if (
                string.IsNullOrWhiteSpace(response.Data.Token) ||
                string.IsNullOrWhiteSpace(response.Data.RefreshToken) ||
                !string.Equals(response.Data.DeviceId, DeviceId, StringComparison.OrdinalIgnoreCase)
            )
            {
                ClearLocalSession(raiseInvalidated: true);
                return false;
            }

            _apiService.Token = response.Data.Token;
            _refreshToken = response.Data.RefreshToken;
            CurrentUser = response.Data.User;
            if (_rememberSession)
            {
                await PersistSessionAsync();
            }
            return true;
        }
        finally
        {
            _refreshLock.Release();
        }
    }

    public async Task LogoutAsync()
    {
        try
        {
            if (CurrentUser != null && !string.IsNullOrWhiteSpace(_apiService.Token))
            {
                await _apiService.PostJsonAsync<object>(
                    "auth/desktop-logout.php",
                    new { device_id = DeviceId });
            }
        }
        finally
        {
            ClearLocalSession(raiseInvalidated: false);
        }
    }

    private async Task PersistSessionAsync()
    {
        if (CurrentUser == null || string.IsNullOrWhiteSpace(_apiService.Token) || string.IsNullOrWhiteSpace(_refreshToken))
        {
            return;
        }

        Directory.CreateDirectory(_appDataDirectory);
        var json = JsonSerializer.Serialize(new StoredSession
        {
            Token = _apiService.Token,
            RefreshToken = _refreshToken,
            DeviceId = DeviceId,
            User = CurrentUser,
        });
        var protectedBytes = ProtectedData.Protect(
            Encoding.UTF8.GetBytes(json),
            optionalEntropy: null,
            DataProtectionScope.CurrentUser);
        await File.WriteAllTextAsync(_sessionPath, Convert.ToBase64String(protectedBytes));
    }

    private string GetOrCreateDeviceId()
    {
        try
        {
            if (File.Exists(_devicePath))
            {
                var existing = File.ReadAllText(_devicePath).Trim();
                if (Guid.TryParse(existing, out _))
                {
                    return existing;
                }
            }

            Directory.CreateDirectory(_appDataDirectory);
            var deviceId = Guid.NewGuid().ToString();
            File.WriteAllText(_devicePath, deviceId);
            return deviceId;
        }
        catch
        {
            return Guid.NewGuid().ToString();
        }
    }

    private void ClearLocalSession(bool raiseInvalidated)
    {
        CurrentUser = null;
        _refreshToken = null;
        _rememberSession = false;
        _apiService.Token = null;
        RemoveStoredSession();
        if (raiseInvalidated)
        {
            SessionInvalidated?.Invoke();
        }
    }

    private void RemoveStoredSession()
    {
        try
        {
            if (File.Exists(_sessionPath))
            {
                File.Delete(_sessionPath);
            }
        }
        catch
        {
            // A locked cache must not prevent sign-out; the next successful sign-in overwrites it.
        }
    }
}
