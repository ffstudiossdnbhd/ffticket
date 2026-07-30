using FFTicket.Desktop.Models;
using Microsoft.UI.Dispatching;
using Microsoft.Windows.AppNotifications;
using Microsoft.Windows.AppNotifications.Builder;

namespace FFTicket.Desktop.Services;

public sealed class DesktopNotificationService : IDisposable
{
    private readonly IAuthService _authService;
    private readonly IApiService _apiService;
    private readonly DesktopSettingsService _settings;
    private readonly DispatcherQueue _dispatcherQueue;
    private readonly SemaphoreSlim _syncLock = new(1, 1);
    private Timer? _syncTimer;
    private bool _started;
    private bool _disposed;

    public DesktopNotificationService(
        IAuthService authService,
        IApiService apiService,
        DesktopSettingsService settings,
        DispatcherQueue dispatcherQueue)
    {
        _authService = authService;
        _apiService = apiService;
        _settings = settings;
        _dispatcherQueue = dispatcherQueue;
        _authService.SessionInvalidated += AuthService_SessionInvalidated;
    }

    public event Action<int>? TicketRequested;

    public async Task StartAsync()
    {
        if (_disposed || _started || !_settings.NotificationsEnabled || _authService.CurrentUser == null)
        {
            return;
        }

        _started = true;
        await SyncAsync();
        _syncTimer = new Timer(
            _ => _ = SyncAsync(),
            state: null,
            dueTime: TimeSpan.FromMinutes(1),
            period: TimeSpan.FromMinutes(1));
    }

    public async Task SetEnabledAsync(bool enabled)
    {
        await _settings.SetNotificationsEnabledAsync(enabled);
        if (!enabled)
        {
            await StopAsync();
            return;
        }

        await StartAsync();
    }

    public Task StopAsync()
    {
        _syncTimer?.Dispose();
        _syncTimer = null;
        _started = false;
        return Task.CompletedTask;
    }

    public void HandleActivation(IReadOnlyDictionary<string, string> arguments)
    {
        if (
            !arguments.TryGetValue("ticketId", out var ticketText) ||
            !int.TryParse(ticketText, out var ticketId) ||
            ticketId < 1)
        {
            return;
        }

        if (
            arguments.TryGetValue("notificationId", out var notificationText) &&
            long.TryParse(notificationText, out var notificationId) &&
            notificationId > 0)
        {
            _ = MarkReadAsync(notificationId);
        }

        TicketRequested?.Invoke(ticketId);
    }

    public async Task SyncAsync()
    {
        if (_disposed || !_started || !_settings.NotificationsEnabled || _authService.CurrentUser == null)
        {
            return;
        }

        if (!await _syncLock.WaitAsync(0))
        {
            return;
        }

        try
        {
            var response = await _apiService.GetAsync<List<UserNotification>>(
                "notifications/index.php?unread=1&limit=100");
            if (!response.IsSuccess || response.Data == null)
            {
                return;
            }

            foreach (var notification in response.Data)
            {
                if (
                    notification.Id < 1 ||
                    !await _settings.TryRememberDisplayedNotificationAsync(notification.Id))
                {
                    continue;
                }

                _dispatcherQueue.TryEnqueue(() => ShowToast(notification));
            }
        }
        finally
        {
            _syncLock.Release();
        }
    }

    private void ShowToast(UserNotification notification)
    {
        if (_disposed || !_settings.NotificationsEnabled)
        {
            return;
        }

        try
        {
            var toast = new AppNotificationBuilder()
                .AddArgument("action", "openTicket")
                .AddArgument("notificationId", notification.Id.ToString())
                .AddArgument("ticketId", notification.TicketId.ToString())
                .AddText(notification.Title)
                .AddText(notification.Body)
                .BuildNotification();
            AppNotificationManager.Default.Show(toast);
        }
        catch
        {
            // Notification display must never interrupt ticket work.
        }
    }

    private async Task MarkReadAsync(long notificationId)
    {
        if (_authService.CurrentUser == null || notificationId < 1)
        {
            return;
        }

        await _apiService.PostJsonAsync<object>(
            "notifications/read.php",
            new { ids = new[] { notificationId } });
    }

    private void AuthService_SessionInvalidated() => _ = StopAsync();

    public void Dispose()
    {
        if (_disposed)
        {
            return;
        }

        _disposed = true;
        _syncTimer?.Dispose();
        _syncLock.Dispose();
        _authService.SessionInvalidated -= AuthService_SessionInvalidated;
    }
}
