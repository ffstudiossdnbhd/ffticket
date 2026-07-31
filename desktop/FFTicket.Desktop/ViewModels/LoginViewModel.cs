using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class LoginViewModel(IAuthService authService) : ViewModelBase
{
    private string _email = "";
    private bool _rememberMe = true;

    public event Action<User>? LoginSucceeded;

    public string Email
    {
        get => _email;
        set => SetProperty(ref _email, value);
    }

    public bool RememberMe
    {
        get => _rememberMe;
        set => SetProperty(ref _rememberMe, value);
    }

    public async Task LoginAsync(string password)
    {
        ClearMessages();
        if (string.IsNullOrWhiteSpace(Email) || string.IsNullOrWhiteSpace(password))
        {
            ErrorMessage = "Email and password are required.";
            return;
        }

        IsBusy = true;
        var result = await authService.LoginAsync(Email.Trim(), password, RememberMe);
        IsBusy = false;

        if (!result.IsSuccess || result.Data == null)
        {
            ErrorMessage = result.Message;
            return;
        }

        LoginSucceeded?.Invoke(result.Data);
    }
}

