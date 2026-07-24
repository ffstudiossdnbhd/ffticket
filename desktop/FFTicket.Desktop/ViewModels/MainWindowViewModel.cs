using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class MainWindowViewModel : ViewModelBase
{
    private readonly IAuthService _authService;

    public MainWindowViewModel(IAuthService authService, IApiService apiService, IFilePickerService filePickerService)
    {
        _authService = authService;
        CurrentUserName = authService.CurrentUser?.Name ?? "User";
        CurrentRole = authService.CurrentUser?.Role ?? "staff";
        IsStaff = CurrentRole == "staff";
        CanManageUsers = CurrentRole == "admin";
        CurrentView = CurrentRole == "staff"
            ? new StaffDashboardViewModel(apiService, filePickerService)
            : new AdminDashboardViewModel(apiService, authService);
        ChangePasswordCommand = new RelayCommand(OpenChangePassword);
        LogoutCommand = new RelayCommand(Logout);
    }

    public event Action? LogoutRequested;
    public event Action? ChangePasswordRequested;
    public string CurrentUserName { get; }
    public string CurrentRole { get; }
    public bool IsStaff { get; }
    public bool CanManageUsers { get; }
    public object CurrentView { get; }
    public IRelayCommand ChangePasswordCommand { get; }
    public IRelayCommand LogoutCommand { get; }

    public async Task SubmitSearchAsync(string query)
    {
        if (CurrentView is StaffDashboardViewModel staff)
        {
            staff.Search = query;
            return;
        }

        if (CurrentView is AdminDashboardViewModel admin && admin.IsTicketOverviewActive)
        {
            admin.TicketOverview.Search = query.Trim();
            await admin.TicketOverview.LoadAsync();
        }
    }

    private void OpenChangePassword() => ChangePasswordRequested?.Invoke();

    private void Logout()
    {
        _authService.Logout();
        LogoutRequested?.Invoke();
    }
}
