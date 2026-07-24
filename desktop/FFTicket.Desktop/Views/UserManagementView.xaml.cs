using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;

namespace FFTicket.Desktop.Views;

public sealed partial class UserManagementView : UserControl
{
    public UserManagementView()
    {
        InitializeComponent();
    }

    private async void AddUser_Click(object sender, RoutedEventArgs e)
    {
        if (DataContext is not UserManagementViewModel viewModel)
        {
            return;
        }

        viewModel.NewPassword = NewPasswordInput.Password;
        if (viewModel.CreateCommand.CanExecute(null))
        {
            await viewModel.CreateCommand.ExecuteAsync(null);
            if (string.IsNullOrEmpty(viewModel.NewPassword))
            {
                NewPasswordInput.Password = "";
            }
        }
    }

    private async void SaveSelected_Click(object sender, RoutedEventArgs e)
    {
        if (DataContext is not UserManagementViewModel viewModel)
        {
            return;
        }

        viewModel.ResetPassword = ResetPasswordInput.Password;
        if (viewModel.UpdateCommand.CanExecute(null))
        {
            await viewModel.UpdateCommand.ExecuteAsync(null);
            if (string.IsNullOrEmpty(viewModel.ResetPassword))
            {
                ResetPasswordInput.Password = "";
            }
        }
    }
}
