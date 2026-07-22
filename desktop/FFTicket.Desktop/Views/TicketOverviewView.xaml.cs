using System.Windows.Controls;
using System.Windows.Input;
using FFTicket.Desktop.ViewModels;

namespace FFTicket.Desktop.Views;

public partial class TicketOverviewView : UserControl
{
    public TicketOverviewView()
    {
        InitializeComponent();
    }

    private async void TicketsGrid_MouseDoubleClick(object sender, MouseButtonEventArgs e)
    {
        if (DataContext is TicketOverviewViewModel viewModel && viewModel.OpenDetailCommand.CanExecute(null))
        {
            await viewModel.OpenDetailCommand.ExecuteAsync(null);
        }
    }
}
