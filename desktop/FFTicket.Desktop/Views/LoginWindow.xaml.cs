using System.Windows;
using FFTicket.Desktop.Services;
using FFTicket.Desktop.ViewModels;

namespace FFTicket.Desktop.Views;

public partial class LoginWindow : Window
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
        DataContext = _viewModel;
        Loaded += LoginWindow_Loaded;
    }

    private void LoginWindow_Loaded(object sender, RoutedEventArgs e)
    {
        if (_authService.TryRestoreSession())
        {
            OpenMainWindow();
        }
    }

    private async void LoginButton_Click(object sender, RoutedEventArgs e)
    {
        LoginButton.IsEnabled = false;
        await _viewModel.LoginAsync(PasswordInput.Password);
        LoginButton.IsEnabled = true;
    }

    private void OpenMainWindow()
    {
        var window = new MainWindow(_authService, _apiService);
        Application.Current.MainWindow = window;
        window.Show();
        Close();
    }
}
