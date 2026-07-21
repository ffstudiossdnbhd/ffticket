using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class MainWindowViewModel : ViewModelBase
{
    private readonly IAuthService _authService;

    public MainWindowViewModel(IAuthService authService, IApiService apiService)
    {
        _authService = authService;
        CurrentUserName = authService.CurrentUser?.Name ?? "User";
        CurrentRole = authService.CurrentUser?.Role ?? "staff";
        CurrentView = CurrentRole == "staff"
            ? new StaffDashboardViewModel(apiService)
            : new AdminDashboardViewModel(apiService, authService);
        LogoutCommand = new RelayCommand(Logout);
    }

    public event Action? LogoutRequested;
    public string CurrentUserName { get; }
    public string CurrentRole { get; }
    public object CurrentView { get; }
    public IRelayCommand LogoutCommand { get; }

    private void Logout()
    {
        _authService.Logout();
        LogoutRequested?.Invoke();
    }
}
