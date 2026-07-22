using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class MainWindowViewModel : ViewModelBase
{
    private readonly IAuthService _authService;
    private readonly IApiService _apiService;

    public MainWindowViewModel(IAuthService authService, IApiService apiService)
    {
        _authService = authService;
        _apiService = apiService;
        CurrentUserName = authService.CurrentUser?.Name ?? "User";
        CurrentRole = authService.CurrentUser?.Role ?? "staff";
        CurrentView = CurrentRole == "staff"
            ? new StaffDashboardViewModel(apiService)
            : new AdminDashboardViewModel(apiService, authService);
        ChangePasswordCommand = new RelayCommand(OpenChangePassword);
        LogoutCommand = new RelayCommand(Logout);
    }

    public event Action? LogoutRequested;
    public string CurrentUserName { get; }
    public string CurrentRole { get; }
    public object CurrentView { get; }
    public IRelayCommand ChangePasswordCommand { get; }
    public IRelayCommand LogoutCommand { get; }

    private void OpenChangePassword()
    {
        var vm = new ChangePasswordViewModel(_apiService);
        new Views.ChangePasswordWindow { DataContext = vm, Owner = App.Current.MainWindow }.ShowDialog();
    }

    private void Logout()
    {
        _authService.Logout();
        LogoutRequested?.Invoke();
    }
}
