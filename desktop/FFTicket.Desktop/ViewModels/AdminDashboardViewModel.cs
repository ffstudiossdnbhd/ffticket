using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class AdminDashboardViewModel : ViewModelBase
{
    public AdminDashboardViewModel(IApiService apiService, IAuthService authService)
    {
        TicketOverview = new TicketOverviewViewModel(apiService, authService);
        KanbanBoard = new KanbanBoardViewModel(apiService);
        UserManagement = new UserManagementViewModel(apiService);
    }

    public TicketOverviewViewModel TicketOverview { get; }
    public KanbanBoardViewModel KanbanBoard { get; }
    public UserManagementViewModel UserManagement { get; }
}

