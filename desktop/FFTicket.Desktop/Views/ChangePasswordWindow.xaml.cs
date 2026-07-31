using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Xaml.Controls;

namespace FFTicket.Desktop.Views;

public sealed partial class ChangePasswordWindow : ContentDialog
{
    public ChangePasswordWindow()
    {
        InitializeComponent();
    }

    private async void ChangePassword_PrimaryButtonClick(ContentDialog sender, ContentDialogButtonClickEventArgs args)
    {
        var deferral = args.GetDeferral();
        args.Cancel = true;
        if (DataContext is not ChangePasswordViewModel viewModel)
        {
            deferral.Complete();
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

        if (!string.IsNullOrWhiteSpace(viewModel.SuccessMessage))
        {
            args.Cancel = false;
        }
        deferral.Complete();
    }
}
