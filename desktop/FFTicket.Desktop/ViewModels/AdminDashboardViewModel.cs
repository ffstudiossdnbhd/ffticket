using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class AdminDashboardViewModel : ViewModelBase
{
    private bool _isTicketOverviewActive = true;

    public AdminDashboardViewModel(IApiService apiService, IAuthService authService)
    {
        TicketOverview = new TicketOverviewViewModel(apiService, authService);
        KanbanBoard = new KanbanBoardViewModel(apiService);
        UserManagement = new UserManagementViewModel(apiService);
        CustomizeTicket = new CustomizeTicketViewModel(apiService);
        CanManageUsers = authService.CurrentUser?.Role == "admin";
    }

    public TicketOverviewViewModel TicketOverview { get; }
    public KanbanBoardViewModel KanbanBoard { get; }
    public UserManagementViewModel UserManagement { get; }
    public CustomizeTicketViewModel CustomizeTicket { get; }
    public bool CanManageUsers { get; }

    public bool IsTicketOverviewActive
    {
        get => _isTicketOverviewActive;
        set => SetProperty(ref _isTicketOverviewActive, value);
    }
}
