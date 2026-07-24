using FFTicket.Desktop.Helpers;
using FFTicket.Desktop.Services;
using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;

namespace FFTicket.Desktop.Views;

public sealed partial class MainWindow : Window
{
    private readonly IApiService _apiService;
    private readonly MainWindowViewModel _viewModel;

    public MainWindow(IAuthService authService, IApiService apiService, IFilePickerService filePickerService)
    {
        InitializeComponent();
        _apiService = apiService;
        _viewModel = new MainWindowViewModel(authService, apiService, filePickerService);
        _viewModel.LogoutRequested += OpenLoginWindow;
        _viewModel.ChangePasswordRequested += ShowChangePasswordDialog;
        Root.DataContext = _viewModel;

        ExtendsContentIntoTitleBar = true;
        SetTitleBar(TitleBarDragRegion);
        WindowHelper.Configure(this, 1360, 860, 980, 640);

        if (_viewModel.CurrentView is StaffDashboardViewModel staff)
        {
            MainContent.Content = new StaffDashboardView { DataContext = staff };
        }
        else if (_viewModel.CurrentView is AdminDashboardViewModel admin)
        {
            var console = new AdminDashboardView { DataContext = admin };
            console.SearchAvailabilityChanged += enabled => SearchBox.IsEnabled = enabled;
            MainContent.Content = console;
        }
    }

    private void OpenLoginWindow()
    {
        ((App)Application.Current).ShowLoginWindow();
    }

    private async void ShowChangePasswordDialog()
    {
        var dialog = new ChangePasswordWindow
        {
            DataContext = new ChangePasswordViewModel(_apiService),
            XamlRoot = Root.XamlRoot
        };
        await dialog.ShowAsync();
    }

    private async void SearchBox_QuerySubmitted(AutoSuggestBox sender, AutoSuggestBoxQuerySubmittedEventArgs args)
    {
        await _viewModel.SubmitSearchAsync(args.QueryText);
    }
}
