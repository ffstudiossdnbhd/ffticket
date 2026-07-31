using Microsoft.Win32;

namespace FFTicket.Desktop.Services;

public static class StartupTaskService
{
    private const string RunKeyPath = "Software\\Microsoft\\Windows\\CurrentVersion\\Run";
    private const string RunValueName = "FFTicket";

    public static Task<bool> IsEnabledAsync()
    {
        try
        {
            using var key = Registry.CurrentUser.OpenSubKey(RunKeyPath, writable: false);
            return Task.FromResult(key?.GetValue(RunValueName) is string value && !string.IsNullOrWhiteSpace(value));
        }
        catch
        {
            return Task.FromResult(false);
        }
    }

    public static Task<bool> SetEnabledAsync(bool enabled)
    {
        try
        {
            using var key = Registry.CurrentUser.CreateSubKey(RunKeyPath, writable: true);
            if (key == null)
            {
                return Task.FromResult(false);
            }

            if (!enabled)
            {
                key.DeleteValue(RunValueName, throwOnMissingValue: false);
                return Task.FromResult(false);
            }

            var executablePath = Environment.GetEnvironmentVariable("FFTicket_LauncherPath");
            if (string.IsNullOrWhiteSpace(executablePath))
            {
                executablePath = Environment.ProcessPath;
            }
            if (string.IsNullOrWhiteSpace(executablePath) ||
                !string.Equals(Path.GetExtension(executablePath), ".exe", StringComparison.OrdinalIgnoreCase))
            {
                return Task.FromResult(false);
            }

            key.SetValue(RunValueName, $"\"{executablePath}\" --minimized", RegistryValueKind.String);
            return Task.FromResult(true);
        }
        catch
        {
            return Task.FromResult(false);
        }
    }
}
