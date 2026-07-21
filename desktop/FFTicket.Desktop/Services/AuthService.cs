using System.IO;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using FFTicket.Desktop.Models;

namespace FFTicket.Desktop.Services;

public sealed class AuthService(IApiService apiService) : IAuthService
{
    private readonly string _sessionPath = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "FFTicket",
        "session.json");

    public User? CurrentUser { get; private set; }

    private sealed class StoredSession
    {
        public string Token { get; set; } = "";
        public User User { get; set; } = new();
    }

    public async Task<ApiResponse<User>> LoginAsync(string email, string password, bool rememberMe)
    {
        var response = await apiService.PostJsonAsync<LoginResult>("auth/login.php", new { email, password });
        if (!response.IsSuccess || response.Data == null)
        {
            return ApiResponse<User>.Failure(response.Message, response.StatusCode);
        }

        apiService.Token = response.Data.Token;
        CurrentUser = response.Data.User;

        if (rememberMe)
        {
            Directory.CreateDirectory(Path.GetDirectoryName(_sessionPath)!);
            var json = JsonSerializer.Serialize(new StoredSession
            {
                Token = response.Data.Token,
                User = response.Data.User
            });
            var protectedBytes = ProtectedData.Protect(Encoding.UTF8.GetBytes(json), null, DataProtectionScope.CurrentUser);
            await File.WriteAllTextAsync(_sessionPath, Convert.ToBase64String(protectedBytes));
        }

        return ApiResponse<User>.Success(CurrentUser, response.Message, response.StatusCode);
    }

    public bool TryRestoreSession()
    {
        try
        {
            if (!File.Exists(_sessionPath))
            {
                return false;
            }

            var protectedBytes = Convert.FromBase64String(File.ReadAllText(_sessionPath));
            var json = Encoding.UTF8.GetString(ProtectedData.Unprotect(protectedBytes, null, DataProtectionScope.CurrentUser));
            var session = JsonSerializer.Deserialize<StoredSession>(json);
            if (session == null || string.IsNullOrWhiteSpace(session.Token))
            {
                return false;
            }

            apiService.Token = session.Token;
            CurrentUser = session.User;
            return true;
        }
        catch
        {
            return false;
        }
    }

    public void Logout()
    {
        CurrentUser = null;
        apiService.Token = null;
        if (File.Exists(_sessionPath))
        {
            File.Delete(_sessionPath);
        }
    }
}
