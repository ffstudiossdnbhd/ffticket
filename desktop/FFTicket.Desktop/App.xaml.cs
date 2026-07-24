using System.IO;
using FFTicket.Desktop.Services;
using FFTicket.Desktop.Views;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;

namespace FFTicket.Desktop;

public partial class App : Application
{
    public App()
    {
        InitializeComponent();
        UnhandledException += App_UnhandledException;
    }

    public Window? ActiveWindow { get; private set; }

    protected override void OnLaunched(LaunchActivatedEventArgs args)
    {
        ShowLoginWindow();
    }

    public void ShowLoginWindow()
    {
        var previous = ActiveWindow;
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
        var window = new MainWindow(authService, apiService, pickerService);
        ActiveWindow = window;
        window.Activate();
        previous?.Close();
    }

    private void App_UnhandledException(object sender, Microsoft.UI.Xaml.UnhandledExceptionEventArgs e)
    {
        var logPath = WriteCrashLog(e.Exception);
        e.Handled = true;
        _ = ShowCrashDialogAsync(logPath);
    }

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
