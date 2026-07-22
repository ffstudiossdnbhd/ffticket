using System.Windows;
using System.Windows.Controls;
using System.Windows.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.ViewModels;

namespace FFTicket.Desktop.Views;

public partial class KanbanBoardView : UserControl
{
    public KanbanBoardView()
    {
        InitializeComponent();
    }

    private async void TicketCard_MouseLeftButtonDown(object sender, MouseButtonEventArgs e)
    {
        if (e.ClickCount != 2 || sender is not FrameworkElement { DataContext: Ticket ticket })
        {
            return;
        }

        if (DataContext is KanbanBoardViewModel viewModel && viewModel.OpenDetailCommand.CanExecute(ticket))
        {
            await viewModel.OpenDetailCommand.ExecuteAsync(ticket);
        }
    }
}
