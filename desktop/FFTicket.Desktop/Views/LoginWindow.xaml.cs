using FFTicket.Desktop.Helpers;
using FFTicket.Desktop.Services;
using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;

namespace FFTicket.Desktop.Views;

public sealed partial class LoginWindow : Window
{
    private readonly IApiService _apiService;
    private readonly IAuthService _authService;
    private readonly LoginViewModel _viewModel;

    public LoginWindow()
    {
        InitializeComponent();
        _apiService = new ApiService();
        _authService = new AuthService(_apiService);
        _viewModel = new LoginViewModel(_authService);
        _viewModel.LoginSucceeded += _ => OpenMainWindow();
        Root.DataContext = _viewModel;
        Root.Loaded += LoginWindow_Loaded;
        ExtendsContentIntoTitleBar = true;
        SetTitleBar(TitleBarDragRegion);
        WindowHelper.Configure(this, 460, 570, 460, 570);
    }

    private void LoginWindow_Loaded(object sender, RoutedEventArgs e)
    {
        if (_authService.TryRestoreSession())
        {
            Root.DispatcherQueue.TryEnqueue(OpenMainWindow);
        }
    }

    private async void LoginButton_Click(object sender, RoutedEventArgs e)
    {
        if (sender is not Button button)
        {
            return;
        }

        var password = (Root.FindName("PasswordInput") as PasswordBox)?.Password ?? "";
        try
        {
            button.IsEnabled = false;
            await _viewModel.LoginAsync(password);
        }
        finally
        {
            button.IsEnabled = true;
        }
    }

    private void OpenMainWindow()
    {
        ((App)Application.Current).ShowMainWindow(_authService, _apiService);
    }
}
