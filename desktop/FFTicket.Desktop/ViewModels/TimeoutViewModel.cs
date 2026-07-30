using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class TimeoutViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private TimeoutUser? _selectedUser;
    private string _releaseAtMyt = DefaultReleaseAtMyt();

    public TimeoutViewModel(IApiService apiService)
    {
        _apiService = apiService;
        RefreshCommand = new AsyncRelayCommand(LoadAsync);
        StartOrUpdateCommand = new AsyncRelayCommand(StartOrUpdateAsync);
        ReleaseCommand = new AsyncRelayCommand(ReleaseAsync);
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
            if (!SetProperty(ref _selectedUser, value) || value == null)
            {
                return;
            }

            ReleaseAtMyt = string.IsNullOrWhiteSpace(value.ReleaseAtMyt)
                ? DefaultReleaseAtMyt()
                : value.ReleaseAtMyt.Replace(' ', 'T');
        }
    }

    public string ReleaseAtMyt
    {
        get => _releaseAtMyt;
        set => SetProperty(ref _releaseAtMyt, value);
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
        var action = SelectedUser.TimedOut ? "update" : "start";
        var response = await _apiService.PostJsonAsync<object>("admin/timeouts.php", new
        {
            action,
            user_id = SelectedUser.Id,
            release_at = ReleaseAtMyt.Trim().Replace(' ', 'T'),
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

    private static string DefaultReleaseAtMyt() => DateTimeOffset.UtcNow
        .ToOffset(TimeSpan.FromHours(8))
        .AddHours(1)
        .ToString("yyyy-MM-ddTHH:mm", System.Globalization.CultureInfo.InvariantCulture);
}
