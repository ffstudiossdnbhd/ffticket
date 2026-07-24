using FFTicket.Desktop.Models;
using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Input;
using Windows.ApplicationModel.DataTransfer;

namespace FFTicket.Desktop.Views;

public sealed partial class KanbanBoardView : UserControl
{
    private Ticket? _draggedTicket;

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

    private void TicketList_DragItemsStarting(object sender, DragItemsStartingEventArgs e)
    {
        _draggedTicket = e.Items.OfType<Ticket>().FirstOrDefault();
        e.Data.RequestedOperation = _draggedTicket == null
            ? DataPackageOperation.None
            : DataPackageOperation.Move;
    }

    private void TicketList_DragItemsCompleted(ListViewBase sender, DragItemsCompletedEventArgs args)
    {
        _draggedTicket = null;
    }

    private void StatusColumn_DragOver(object sender, DragEventArgs e)
    {
        if (_draggedTicket != null && sender is FrameworkElement { Tag: string status } && status != _draggedTicket.Status)
        {
            e.AcceptedOperation = DataPackageOperation.Move;
            e.DragUIOverride.Caption = $"Move to {status}";
            e.DragUIOverride.IsCaptionVisible = true;
        }
    }

    private async void StatusColumn_Drop(object sender, DragEventArgs e)
    {
        if (
            _draggedTicket is Ticket ticket &&
            sender is FrameworkElement { Tag: string status } &&
            DataContext is KanbanBoardViewModel viewModel)
        {
            e.AcceptedOperation = DataPackageOperation.Move;
            e.Handled = true;
            await viewModel.MoveTicketAsync(ticket, status);
        }

        _draggedTicket = null;
    }

    private void BoardScroller_SizeChanged(object sender, SizeChangedEventArgs e)
    {
        BoardGrid.Width = Math.Max(960, e.NewSize.Width);
    }

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
