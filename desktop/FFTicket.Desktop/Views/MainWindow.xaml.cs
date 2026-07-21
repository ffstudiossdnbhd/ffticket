using System.Windows;
using FFTicket.Desktop.Services;
using FFTicket.Desktop.ViewModels;

namespace FFTicket.Desktop.Views;

public partial class MainWindow : Window
{
    public MainWindow(IAuthService authService, IApiService apiService)
    {
        InitializeComponent();
        DataContext = new MainWindowViewModel(authService, apiService);
    }
}

