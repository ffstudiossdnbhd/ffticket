using FFTicket.Desktop.Models;
using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Input;
using Microsoft.UI.Xaml.Media;
using CommunityToolkit.WinUI.UI.Controls;

namespace FFTicket.Desktop.Views;

public sealed partial class TicketOverviewView : UserControl
{
    public TicketOverviewView()
    {
        InitializeComponent();
        TicketsGrid.AddHandler(
            UIElement.TappedEvent,
            new TappedEventHandler(TicketsGrid_Tapped),
            handledEventsToo: true);
    }

    private async void TicketsGrid_Tapped(object sender, TappedRoutedEventArgs e)
    {
        DependencyObject? source = e.OriginalSource as DependencyObject;
        while (source != null && source is not DataGridRow && source != TicketsGrid)
        {
            source = VisualTreeHelper.GetParent(source);
        }

        if (
            source is not DataGridRow ||
            DataContext is not TicketOverviewViewModel viewModel ||
            TicketsGrid.SelectedItem is not Ticket ticket)
        {
            return;
        }

        await viewModel.OpenTicketAsync(ticket);
    }

    private async void ReportDate_DateChanged(CalendarDatePicker sender, CalendarDatePickerDateChangedEventArgs args)
    {
        if (args.OldDate != null && DataContext is TicketOverviewViewModel viewModel)
        {
            await viewModel.ApplyDateFilterAsync();
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
