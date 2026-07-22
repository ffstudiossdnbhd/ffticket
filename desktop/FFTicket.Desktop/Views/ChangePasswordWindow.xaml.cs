using System.Windows;
using FFTicket.Desktop.ViewModels;

namespace FFTicket.Desktop.Views;

public partial class ChangePasswordWindow : Window
{
    public ChangePasswordWindow()
    {
        InitializeComponent();
    }

    private async void ChangePassword_Click(object sender, RoutedEventArgs e)
    {
        if (DataContext is not ChangePasswordViewModel viewModel)
        {
            return;
        }

        var request = new PasswordChangeRequest(
            CurrentPasswordInput.Password,
            NewPasswordInput.Password,
            ConfirmPasswordInput.Password);

        if (viewModel.ChangePasswordCommand.CanExecute(request))
        {
            await viewModel.ChangePasswordCommand.ExecuteAsync(request);
        }
    }
}
