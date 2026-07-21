using System.IO;
using System.Windows;
using System.Windows.Threading;

namespace FFTicket.Desktop;

public partial class App : Application
{
    protected override void OnStartup(StartupEventArgs e)
    {
        DispatcherUnhandledException += App_DispatcherUnhandledException;
        base.OnStartup(e);
    }

    private static void App_DispatcherUnhandledException(object sender, DispatcherUnhandledExceptionEventArgs e)
    {
        var logPath = WriteCrashLog(e.Exception);
        MessageBox.Show(
            $"FFTicket hit an unexpected error and needs to close.\n\nDetails were saved to:\n{logPath}",
            "FFTicket Error",
            MessageBoxButton.OK,
            MessageBoxImage.Error);

        e.Handled = true;
        Current.Shutdown(1);
    }

    private static string WriteCrashLog(Exception exception)
    {
        var appData = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "FFTicket");
        Directory.CreateDirectory(appData);

        var logPath = Path.Combine(appData, "crash.log");
        var entry = $"[{DateTimeOffset.UtcNow:u}]{Environment.NewLine}{exception}{Environment.NewLine}{Environment.NewLine}";
        File.AppendAllText(logPath, entry);
        return logPath;
    }
}
