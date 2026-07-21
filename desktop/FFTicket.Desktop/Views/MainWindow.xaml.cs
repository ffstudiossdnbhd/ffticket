using System.Windows;
using FFTicket.Desktop.Services;
using FFTicket.Desktop.ViewModels;

namespace FFTicket.Desktop.Views;

public partial class MainWindow : Window
{
    public MainWindow(IAuthService authService, IApiService apiService)
    {
        InitializeComponent();
        var viewModel = new MainWindowViewModel(authService, apiService);
        viewModel.LogoutRequested += OpenLoginWindow;
        DataContext = viewModel;
    }

    private void OpenLoginWindow()
    {
        var window = new LoginWindow();
        Application.Current.MainWindow = window;
        window.Show();
        Close();
    }
}
