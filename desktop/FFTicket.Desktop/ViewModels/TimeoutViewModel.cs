using System.Collections.ObjectModel;
using System.Globalization;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class TimeoutViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private TimeoutUser? _selectedUser;
    private DateTimeOffset? _releaseDate;
    private TimeSpan _releaseTime;

    public TimeoutViewModel(IApiService apiService)
    {
        _apiService = apiService;
        RefreshCommand = new AsyncRelayCommand(LoadAsync);
        StartOrUpdateCommand = new AsyncRelayCommand(StartOrUpdateAsync);
        ReleaseCommand = new AsyncRelayCommand(ReleaseAsync);
        SetReleaseDateTime(DefaultReleaseAtMyt());
        _ = LoadAsync();
    }

    public ObservableCollection<TimeoutUser> Users { get; } = [];
    public IAsyncRelayCommand RefreshCommand { get; }
    public IAsyncRelayCommand StartOrUpdateCommand { get; }
    public IAsyncRelayCommand ReleaseCommand { get; }

    public TimeoutUser? SelectedUser
    {
        get => _selectedUser;
        set
        {
            if (!SetProperty(ref _selectedUser, value))
            {
                return;
            }

            SetReleaseDateTime(
                TryParseReleaseAtMyt(value?.ReleaseAtMyt, out var releaseAt)
                    ? releaseAt
                    : DefaultReleaseAtMyt());
        }
    }

    public DateTimeOffset? ReleaseDate
    {
        get => _releaseDate;
        set => SetProperty(ref _releaseDate, value);
    }

    public TimeSpan ReleaseTime
    {
        get => _releaseTime;
        set => SetProperty(ref _releaseTime, value);
    }

    public async Task LoadAsync()
    {
        ClearMessages();
        var response = await _apiService.GetAsync<List<TimeoutUser>>("admin/timeouts.php");
        Users.Clear();
        if (!response.IsSuccess || response.Data == null)
        {
            ErrorMessage = response.Message;
            return;
        }

        foreach (var user in response.Data)
        {
            Users.Add(user);
        }
    }

    private async Task StartOrUpdateAsync()
    {
        if (SelectedUser is not { CanTimeout: true })
        {
            ErrorMessage = "Select a staff or IT staff account.";
            return;
        }

        ClearMessages();
        var releaseAt = BuildReleaseAtMyt();
        if (releaseAt == null)
        {
            ErrorMessage = "Choose a release date and time in MYT.";
            return;
        }

        var action = SelectedUser.TimedOut ? "update" : "start";
        var response = await _apiService.PostJsonAsync<object>("admin/timeouts.php", new
        {
            action,
            user_id = SelectedUser.Id,
            release_at = releaseAt,
        });
        await FinishMutationAsync(response, action == "start" ? "Timeout started." : "Timeout release time updated.");
    }

    private async Task ReleaseAsync()
    {
        if (SelectedUser is not { CanTimeout: true })
        {
            return;
        }

        var response = await _apiService.PostJsonAsync<object>("admin/timeouts.php", new
        {
            action = "release",
            user_id = SelectedUser.Id,
        });
        await FinishMutationAsync(response, "User released from timeout.");
    }

    private async Task FinishMutationAsync(ApiResponse<object> response, string success)
    {
        ClearMessages();
        if (!response.IsSuccess)
        {
            ErrorMessage = response.Message;
            return;
        }

        SuccessMessage = success;
        await LoadAsync();
    }

    private static DateTime DefaultReleaseAtMyt() => DateTimeOffset.UtcNow
        .ToOffset(TimeSpan.FromHours(8))
        .AddHours(1)
        .DateTime;

    private void SetReleaseDateTime(DateTime value)
    {
        var mytOffset = TimeSpan.FromHours(8);
        ReleaseDate = new DateTimeOffset(value.Date, mytOffset);
        ReleaseTime = value.TimeOfDay;
    }

    private string? BuildReleaseAtMyt()
    {
        if (ReleaseDate is not { } date)
        {
            return null;
        }

        return (date.Date + ReleaseTime).ToString("yyyy-MM-ddTHH:mm", CultureInfo.InvariantCulture);
    }

    private static bool TryParseReleaseAtMyt(string? value, out DateTime releaseAt)
    {
        return DateTime.TryParseExact(
            value,
            "yyyy-MM-dd HH:mm",
            CultureInfo.InvariantCulture,
            DateTimeStyles.None,
            out releaseAt);
    }
}
