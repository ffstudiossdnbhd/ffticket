using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class MainWindowViewModel : ViewModelBase
{
    public MainWindowViewModel(IAuthService authService, IApiService apiService)
    {
        CurrentUserName = authService.CurrentUser?.Name ?? "User";
        CurrentRole = authService.CurrentUser?.Role ?? "staff";
        CurrentView = CurrentRole == "staff"
            ? new StaffDashboardViewModel(apiService)
            : new AdminDashboardViewModel(apiService, authService);
    }

    public string CurrentUserName { get; }
    public string CurrentRole { get; }
    public object CurrentView { get; }
}

