using System.Collections.ObjectModel;
using System.Threading;
using FFTicket.Desktop.Services;
using FFTicket.Desktop.Views;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.Windows.AppLifecycle;
using Microsoft.Windows.AppNotifications;

namespace FFTicket.Desktop;

public partial class App : Application
{
    private const string SingleInstanceKey = "FFTicket.Desktop.Primary";
    private AppInstance? _primaryInstance;
    private IReadOnlyDictionary<string, string>? _pendingNotificationArguments;
    private bool _startMinimized;

    public App()
    {
        InitializeComponent();
        UnhandledException += App_UnhandledException;
    }

    public Window? ActiveWindow { get; private set; }

    protected override void OnLaunched(LaunchActivatedEventArgs args)
    {
        if (!EnsurePrimaryInstance())
        {
            return;
        }

        AppNotificationManager.Default.NotificationInvoked -= NotificationManager_NotificationInvoked;
        AppNotificationManager.Default.NotificationInvoked += NotificationManager_NotificationInvoked;
        AppNotificationManager.Default.Register();

        var activatedArgs = AppInstance.GetCurrent().GetActivatedEventArgs();
        _startMinimized = HasMinimizedArgument(args.Arguments);
        if (
            activatedArgs.Kind == ExtendedActivationKind.AppNotification &&
            activatedArgs.Data is AppNotificationActivatedEventArgs notificationArgs)
        {
            RouteNotification(notificationArgs.Arguments);
        }
        ShowLoginWindow();
    }

    public void ShowLoginWindow()
    {
        var previous = ActiveWindow;
        if (previous is MainWindow main)
        {
            main.DisposeForNavigation();
        }

        var window = new LoginWindow();
        ActiveWindow = window;
        window.Activate();
        previous?.Close();
    }

    public void ShowMainWindow(IAuthService authService, IApiService apiService)
    {
        var previous = ActiveWindow;
        var pickerService = new FilePickerService(() =>
            ActiveWindow ?? throw new InvalidOperationException("No active FFTicket window is available."));
        var window = new MainWindow(authService, apiService, pickerService, _startMinimized);
        _startMinimized = false;
        ActiveWindow = window;
        window.Activate();
        previous?.Close();

        if (_pendingNotificationArguments != null)
        {
            window.HandleNotificationActivation(_pendingNotificationArguments);
            _pendingNotificationArguments = null;
        }
    }

    private bool EnsurePrimaryInstance()
    {
        if (_primaryInstance != null)
        {
            return true;
        }

        var activation = AppInstance.GetCurrent().GetActivatedEventArgs();
        var instance = AppInstance.FindOrRegisterForKey(SingleInstanceKey);
        if (!instance.IsCurrent)
        {
            var redirected = new ManualResetEventSlim(false);
            _ = Task.Run(async () =>
            {
                try
                {
                    await instance.RedirectActivationToAsync(activation);
                }
                finally
                {
                    redirected.Set();
                }
            });
            redirected.Wait();
            Environment.Exit(0);
            return false;
        }

        _primaryInstance = instance;
        _primaryInstance.Activated += PrimaryInstance_Activated;
        return true;
    }

    private void PrimaryInstance_Activated(object? sender, AppActivationArguments args)
    {
        if (
            args.Kind == ExtendedActivationKind.AppNotification &&
            args.Data is AppNotificationActivatedEventArgs notificationArgs)
        {
            RouteNotification(notificationArgs.Arguments);
            return;
        }
        if (args.Kind == ExtendedActivationKind.Launch &&
            args.Data is LaunchActivatedEventArgs launchArgs &&
            HasMinimizedArgument(launchArgs.Arguments))
        {
            return;
        }

        if (ActiveWindow is MainWindow main)
        {
            main.DispatcherQueue.TryEnqueue(() => Helpers.WindowHelper.RestoreAndActivate(main));
        }
        else
        {
            ActiveWindow?.DispatcherQueue.TryEnqueue(() => ActiveWindow.Activate());
        }
    }

    private void NotificationManager_NotificationInvoked(AppNotificationManager sender, AppNotificationActivatedEventArgs args) =>
        RouteNotification(args.Arguments);

    private void RouteNotification(IDictionary<string, string> arguments)
    {
        var copied = new ReadOnlyDictionary<string, string>(
            new Dictionary<string, string>(arguments, StringComparer.OrdinalIgnoreCase));
        if (ActiveWindow is MainWindow main)
        {
            main.HandleNotificationActivation(copied);
            return;
        }

        _pendingNotificationArguments = copied;
    }

    private void App_UnhandledException(object sender, Microsoft.UI.Xaml.UnhandledExceptionEventArgs e)
    {
        var logPath = WriteCrashLog(e.Exception);
        e.Handled = true;
        _ = ShowCrashDialogAsync(logPath);
    }

    private static bool HasMinimizedArgument(string arguments) =>
        arguments.Split(' ', StringSplitOptions.RemoveEmptyEntries)
            .Any(argument => string.Equals(argument, "--minimized", StringComparison.OrdinalIgnoreCase));

    private async Task ShowCrashDialogAsync(string logPath)
    {
        if (ActiveWindow?.Content is not FrameworkElement root)
        {
            return;
        }

        var dialog = new ContentDialog
        {
            Title = "FFTicket Error",
            Content = $"FFTicket hit an unexpected error.\n\nDetails were saved to:\n{logPath}",
            CloseButtonText = "Close",
            XamlRoot = root.XamlRoot
        };
        await dialog.ShowAsync();
    }

    private static string WriteCrashLog(Exception exception)
    {
        var appData = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "FFTicket");
        Directory.CreateDirectory(appData);

        var logPath = Path.Combine(appData, "crash.log");
        var entry =
            $"[{DateTimeOffset.UtcNow:u}]{Environment.NewLine}" +
            $"HRESULT: 0x{exception.HResult:X8}{Environment.NewLine}" +
            $"{exception}{Environment.NewLine}{Environment.NewLine}";
        File.AppendAllText(logPath, entry);
        return logPath;
    }
}
