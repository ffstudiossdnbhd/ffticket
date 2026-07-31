using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace FFTicket.Desktop.Services;

public sealed class DesktopSettingsService
{
    private const int DisplayedNotificationLimit = 500;
    private readonly string _settingsPath = Path.Combine(
        Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
        "FFTicket",
        "settings.json");
    private readonly SemaphoreSlim _settingsLock = new(1, 1);
    private StoredSettings _settings;

    private sealed class StoredSettings
    {
        public bool NotificationsEnabled { get; set; } = true;
        public List<long> DisplayedNotificationIds { get; set; } = [];
    }

    public DesktopSettingsService()
    {
        _settings = Load();
    }

    public bool NotificationsEnabled => _settings.NotificationsEnabled;

    public async Task SetNotificationsEnabledAsync(bool enabled)
    {
        await _settingsLock.WaitAsync();
        try
        {
            _settings.NotificationsEnabled = enabled;
            await SaveAsync();
        }
        finally
        {
            _settingsLock.Release();
        }
    }

    public async Task<bool> TryRememberDisplayedNotificationAsync(long notificationId)
    {
        if (notificationId < 1)
        {
            return false;
        }

        await _settingsLock.WaitAsync();
        try
        {
            if (_settings.DisplayedNotificationIds.Contains(notificationId))
            {
                return false;
            }

            _settings.DisplayedNotificationIds.Add(notificationId);
            if (_settings.DisplayedNotificationIds.Count > DisplayedNotificationLimit)
            {
                _settings.DisplayedNotificationIds.RemoveRange(
                    0,
                    _settings.DisplayedNotificationIds.Count - DisplayedNotificationLimit);
            }
            await SaveAsync();
            return true;
        }
        finally
        {
            _settingsLock.Release();
        }
    }

    private StoredSettings Load()
    {
        try
        {
            if (!File.Exists(_settingsPath))
            {
                return new StoredSettings();
            }

            var protectedBytes = Convert.FromBase64String(File.ReadAllText(_settingsPath));
            var json = Encoding.UTF8.GetString(ProtectedData.Unprotect(
                protectedBytes,
                optionalEntropy: null,
                DataProtectionScope.CurrentUser));
            var settings = JsonSerializer.Deserialize<StoredSettings>(json) ?? new StoredSettings();
            settings.DisplayedNotificationIds ??= [];
            return settings;
        }
        catch
        {
            return new StoredSettings();
        }
    }

    private async Task SaveAsync()
    {
        Directory.CreateDirectory(Path.GetDirectoryName(_settingsPath)!);
        var json = JsonSerializer.Serialize(_settings);
        var protectedBytes = ProtectedData.Protect(
            Encoding.UTF8.GetBytes(json),
            optionalEntropy: null,
            DataProtectionScope.CurrentUser);
        await File.WriteAllTextAsync(_settingsPath, Convert.ToBase64String(protectedBytes));
    }
}
