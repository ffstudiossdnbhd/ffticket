using FFTicket.Desktop.Models;
using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;

namespace FFTicket.Desktop.Views;

public sealed partial class TicketDetailView : UserControl
{
    public TicketDetailView()
    {
        InitializeComponent();
    }

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
