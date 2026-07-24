using FFTicket.Desktop.Models;
using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Input;

namespace FFTicket.Desktop.Views;

public sealed partial class KanbanBoardView : UserControl
{
    public KanbanBoardView()
    {
        InitializeComponent();
    }

    private async void TicketCard_DoubleTapped(object sender, DoubleTappedRoutedEventArgs e)
    {
        if (sender is not FrameworkElement { DataContext: Ticket ticket } ||
            DataContext is not KanbanBoardViewModel viewModel)
        {
            return;
        }

        var detail = new TicketDetailViewModel(viewModel.ApiService, ticket.Id, true);
        await detail.LoadAsync();
        var dialog = new TicketDetailWindow
        {
            DataContext = detail,
            XamlRoot = XamlRoot
        };
        await dialog.ShowAsync();
        await viewModel.LoadAsync();
    }

    private async void StartTicket_Click(object sender, RoutedEventArgs e) =>
        await ExecuteTicketCommandAsync(sender, vm => vm.MoveToInProgressCommand);

    private async void PendingTicket_Click(object sender, RoutedEventArgs e) =>
        await ExecuteTicketCommandAsync(sender, vm => vm.MoveToPendingCommand);

    private async void CloseTicket_Click(object sender, RoutedEventArgs e) =>
        await ExecuteTicketCommandAsync(sender, vm => vm.MoveToClosedCommand);

    private async void ReopenTicket_Click(object sender, RoutedEventArgs e) =>
        await ExecuteTicketCommandAsync(sender, vm => vm.MoveToOpenCommand);

    private async Task ExecuteTicketCommandAsync(
        object sender,
        Func<KanbanBoardViewModel, CommunityToolkit.Mvvm.Input.IAsyncRelayCommand<Ticket>> commandSelector)
    {
        if (sender is Button { Tag: Ticket ticket } && DataContext is KanbanBoardViewModel viewModel)
        {
            var command = commandSelector(viewModel);
            if (command.CanExecute(ticket))
            {
                await command.ExecuteAsync(ticket);
            }
        }
    }
}
