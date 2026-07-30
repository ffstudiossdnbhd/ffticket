using FFTicket.Desktop.Models;
using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Dispatching;

namespace FFTicket.Desktop.Views;

public sealed partial class TicketDetailView : UserControl
{
    private readonly DispatcherQueueTimer _presenceTimer;
    private DateTimeOffset _editingUntil;

    public TicketDetailView()
    {
        InitializeComponent();
        _presenceTimer = DispatcherQueue.CreateTimer();
        _presenceTimer.Interval = TimeSpan.FromSeconds(15);
        _presenceTimer.Tick += PresenceTimer_Tick;
        Loaded += TicketDetailView_Loaded;
        Unloaded += TicketDetailView_Unloaded;
    }

    private void TicketDetailView_Loaded(object sender, RoutedEventArgs e)
    {
        if (DataContext is TicketDetailViewModel { CanManageInternalNotes: true })
        {
            _presenceTimer.Start();
            _ = PulsePresenceAsync();
        }
    }

    private void TicketDetailView_Unloaded(object sender, RoutedEventArgs e) => _presenceTimer.Stop();

    private async void PresenceTimer_Tick(DispatcherQueueTimer sender, object args) =>
        await PulsePresenceAsync();

    private async Task PulsePresenceAsync()
    {
        if (DataContext is TicketDetailViewModel { CanManageInternalNotes: true } viewModel)
        {
            await viewModel.RefreshCollaborationAsync(DateTimeOffset.UtcNow < _editingUntil);
        }
    }

    private void EditControl_GotFocus(object sender, RoutedEventArgs e) =>
        _editingUntil = DateTimeOffset.UtcNow.AddSeconds(16);

    private async void Attachment_Click(object sender, RoutedEventArgs e)
    {
        if (
            DataContext is TicketDetailViewModel viewModel &&
            sender is HyperlinkButton { Tag: Attachment attachment } &&
            viewModel.OpenAttachmentCommand.CanExecute(attachment))
        {
            await viewModel.OpenAttachmentCommand.ExecuteAsync(attachment);
        }
    }
}
