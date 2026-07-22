using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class ChangePasswordViewModel : ViewModelBase
{
    private readonly IApiService _apiService;

    public ChangePasswordViewModel(IApiService apiService)
    {
        _apiService = apiService;
        ChangePasswordCommand = new AsyncRelayCommand<PasswordChangeRequest>(ChangePasswordAsync);
    }

    public event Action? PasswordChanged;
    public IAsyncRelayCommand<PasswordChangeRequest> ChangePasswordCommand { get; }

    private async Task ChangePasswordAsync(PasswordChangeRequest? request)
    {
        ClearMessages();
        if (request == null || string.IsNullOrWhiteSpace(request.CurrentPassword) || string.IsNullOrWhiteSpace(request.NewPassword))
        {
            ErrorMessage = "Current password and new password are required.";
            return;
        }

        if (request.NewPassword != request.ConfirmPassword)
        {
            ErrorMessage = "New password and confirmation do not match.";
            return;
        }

        IsBusy = true;
        var response = await _apiService.PostJsonAsync<object>("auth/change-password.php", new
        {
            current_password = request.CurrentPassword,
            new_password = request.NewPassword
        });
        IsBusy = false;

        if (!response.IsSuccess)
        {
            ErrorMessage = response.Message;
            return;
        }

        SuccessMessage = "Password changed.";
        PasswordChanged?.Invoke();
    }
}

public sealed record PasswordChangeRequest(string CurrentPassword, string NewPassword, string ConfirmPassword);
