using FFTicket.Desktop.ViewModels;
using FFTicket.Desktop.Models;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Input;

namespace FFTicket.Desktop.Views;

public sealed partial class StaffDashboardView : UserControl
{
    private StaffDashboardViewModel? _subscribedViewModel;

    public StaffDashboardView()
    {
        InitializeComponent();
        DataContextChanged += StaffDashboardView_DataContextChanged;
    }

    private void StaffDashboardView_DataContextChanged(FrameworkElement sender, DataContextChangedEventArgs args)
    {
        if (_subscribedViewModel != null)
        {
            _subscribedViewModel.DetailRequested -= ViewModel_DetailRequested;
        }

        _subscribedViewModel = args.NewValue as StaffDashboardViewModel;
        if (_subscribedViewModel != null)
        {
            _subscribedViewModel.DetailRequested += ViewModel_DetailRequested;
        }
    }

    private async void ViewModel_DetailRequested(Ticket ticket)
    {
        if (DataContext is not StaffDashboardViewModel viewModel)
        {
            return;
        }

        var detail = new TicketDetailViewModel(GetApiService(viewModel), ticket.Id, false);
        await detail.LoadAsync();
        var dialog = new TicketDetailWindow
        {
            DataContext = detail,
            XamlRoot = XamlRoot
        };
        await dialog.ShowAsync();
        await viewModel.RefreshTicketsAsync();
    }

    private async void TicketsGrid_DoubleTapped(object sender, DoubleTappedRoutedEventArgs e)
    {
        if (DataContext is StaffDashboardViewModel viewModel && viewModel.OpenDetailCommand.CanExecute(null))
        {
            await viewModel.OpenDetailCommand.ExecuteAsync(null);
        }
    }

    private static Services.IApiService GetApiService(StaffDashboardViewModel viewModel) =>
        viewModel.ApiService;

    private void LayoutRoot_SizeChanged(object sender, SizeChangedEventArgs e)
    {
        var compact = e.NewSize.Width < 1050;
        if (compact)
        {
            FormColumn.Width = new GridLength(1, GridUnitType.Star);
            TicketsColumn.Width = new GridLength(0);
            FormRow.Height = new GridLength(1.1, GridUnitType.Star);
            TicketsRow.Height = new GridLength(1, GridUnitType.Star);
            Grid.SetColumn(FormCard, 0);
            Grid.SetRow(FormCard, 0);
            Grid.SetColumn(TicketsCard, 0);
            Grid.SetRow(TicketsCard, 1);
        }
        else
        {
            FormColumn.Width = new GridLength(400);
            TicketsColumn.Width = new GridLength(1, GridUnitType.Star);
            FormRow.Height = new GridLength(1, GridUnitType.Star);
            TicketsRow.Height = new GridLength(0);
            Grid.SetColumn(FormCard, 0);
            Grid.SetRow(FormCard, 0);
            Grid.SetColumn(TicketsCard, 1);
            Grid.SetRow(TicketsCard, 0);
        }
    }
}
