using FFTicket.Desktop.Models;
using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using CommunityToolkit.WinUI.UI.Controls;

namespace FFTicket.Desktop.Views;

public sealed partial class TicketOverviewView : UserControl
{
    public TicketOverviewView()
    {
        InitializeComponent();
    }

    private async void TicketsGrid_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        if (DataContext is TicketOverviewViewModel viewModel && sender is DataGrid { SelectedItem: Ticket ticket })
        {
            await viewModel.OpenTicketAsync(ticket);
        }
    }

    private async void OpenTicket_Click(object sender, RoutedEventArgs e)
    {
        if (DataContext is TicketOverviewViewModel viewModel && sender is Button { Tag: Ticket ticket })
        {
            await viewModel.OpenTicketAsync(ticket);
        }
    }
}
