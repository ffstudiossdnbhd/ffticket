using FFTicket.Desktop.Models;

namespace FFTicket.Desktop.Services;

/// <summary>
/// Keeps an authenticated desktop session visible to administrators and receives
/// the one-minute timeout warning independently of any open ticket detail.
/// </summary>
public sealed class DesktopPresenceService : IDisposable
{
    private readonly IAuthService _authService;
    private readonly IApiService _apiService;
    private CancellationTokenSource? _cancellation;
    private Task? _loop;
    private bool _disposed;

    public DesktopPresenceService(IAuthService authService, IApiService apiService)
    {
        _authService = authService;
        _apiService = apiService;
    }

    public event Action<TimeoutState>? TimeoutWarning;

    public Task StartAsync()
    {
        if (_disposed || _loop != null || _authService.CurrentUser == null)
        {
            return Task.CompletedTask;
        }

        _cancellation = new CancellationTokenSource();
        _loop = RunAsync(_cancellation.Token);
        return PulseAsync(cancellationToken: _cancellation.Token);
    }

    public async Task StopAsync()
    {
        if (_cancellation == null)
        {
            return;
        }

        _cancellation.Cancel();
        try
        {
            if (_loop != null)
            {
                await _loop;
            }
        }
        catch (OperationCanceledException)
        {
            // Expected when the application closes or returns to sign-in.
        }
        finally
        {
            _cancellation.Dispose();
            _cancellation = null;
            _loop = null;
        }
    }

    public async Task<ApiResponse<PresenceHeartbeat>> PulseAsync(
        int ticketId = 0,
        bool editing = false,
        CancellationToken cancellationToken = default)
    {
        if (_disposed || _authService.CurrentUser == null)
        {
            return ApiResponse<PresenceHeartbeat>.Failure("You are signed out.", 401);
        }

        var response = await _apiService.PostJsonAsync<PresenceHeartbeat>("presence/heartbeat.php", new
        {
            client_id = _authService.DeviceId,
            ticket_id = ticketId,
            mode = editing ? "editing" : "viewing",
        }, cancellationToken);

        if (response.StatusCode == 423)
        {
            _authService.InvalidateSession();
            return response;
        }

        if (response.Data?.Timeout is { Warning: true } warning)
        {
            TimeoutWarning?.Invoke(warning);
        }

        return response;
    }

    private async Task RunAsync(CancellationToken cancellationToken)
    {
        using var timer = new PeriodicTimer(TimeSpan.FromSeconds(30));
        while (await timer.WaitForNextTickAsync(cancellationToken))
        {
            await PulseAsync(cancellationToken: cancellationToken);
        }
    }

    public void Dispose()
    {
        if (_disposed)
        {
            return;
        }

        _disposed = true;
        _cancellation?.Cancel();
        _cancellation?.Dispose();
    }
}
