using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;

namespace FFTicket.Desktop.Views;

public sealed partial class AdminDashboardView : UserControl
{
    public event Action<bool>? SearchAvailabilityChanged;

    public AdminDashboardView()
    {
        InitializeComponent();
        Loaded += AdminDashboardView_Loaded;
    }

    private void AdminDashboardView_Loaded(object sender, RoutedEventArgs e)
    {
        ShowSection("tickets");
    }

    private void ConsoleNavigation_SelectionChanged(NavigationView sender, NavigationViewSelectionChangedEventArgs args)
    {
        if (args.SelectedItemContainer?.Tag is string tag)
        {
            ShowSection(tag);
        }
    }

    private void ShowSection(string tag)
    {
        if (DataContext is not AdminDashboardViewModel viewModel)
        {
            return;
        }

        viewModel.IsTicketOverviewActive = tag == "tickets";
        SearchAvailabilityChanged?.Invoke(viewModel.IsTicketOverviewActive);
        SectionContent.Content = tag switch
        {
            "kanban" => new KanbanBoardView { DataContext = viewModel.KanbanBoard },
            "users" when viewModel.CanManageUsers => new UserManagementView { DataContext = viewModel.UserManagement },
            "customize" => new CustomizeTicketView { DataContext = viewModel.CustomizeTicket },
            _ => new TicketOverviewView { DataContext = viewModel.TicketOverview }
        };
    }
}
