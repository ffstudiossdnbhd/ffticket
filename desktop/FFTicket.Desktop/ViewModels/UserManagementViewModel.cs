using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class UserManagementViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private User? _selectedUser;
    private string _newName = "";
    private string _newNickname = "";
    private string _newEmail = "";
    private string _newPassword = "";
    private string _newRole = "staff";
    private string _resetPassword = "";

    public UserManagementViewModel(IApiService apiService)
    {
        _apiService = apiService;
        RefreshCommand = new AsyncRelayCommand(LoadAsync);
        CreateCommand = new AsyncRelayCommand(CreateAsync);
        UpdateCommand = new AsyncRelayCommand(UpdateAsync);
        DeleteCommand = new AsyncRelayCommand(DeleteAsync);
        _ = LoadAsync();
    }

    public ObservableCollection<User> Users { get; } = [];
    public IReadOnlyList<string> Roles { get; } = ["staff", "it_staff", "admin"];
    public IAsyncRelayCommand RefreshCommand { get; }
    public IAsyncRelayCommand CreateCommand { get; }
    public IAsyncRelayCommand UpdateCommand { get; }
    public IAsyncRelayCommand DeleteCommand { get; }

    public User? SelectedUser
    {
        get => _selectedUser;
        set => SetProperty(ref _selectedUser, value);
    }

    public string NewName
    {
        get => _newName;
        set => SetProperty(ref _newName, value);
    }

    public string NewNickname
    {
        get => _newNickname;
        set => SetProperty(ref _newNickname, value);
    }

    public string NewEmail
    {
        get => _newEmail;
        set => SetProperty(ref _newEmail, value);
    }

    public string NewPassword
    {
        get => _newPassword;
        set => SetProperty(ref _newPassword, value);
    }

    public string NewRole
    {
        get => _newRole;
        set => SetProperty(ref _newRole, value);
    }

    public string ResetPassword
    {
        get => _resetPassword;
        set => SetProperty(ref _resetPassword, value);
    }

    public async Task LoadAsync()
    {
        ClearMessages();
        var response = await _apiService.GetAsync<List<User>>("users/index.php");
        Users.Clear();
        if (response.IsSuccess && response.Data != null)
        {
            foreach (var user in response.Data)
            {
                Users.Add(user);
            }
            return;
        }
        ErrorMessage = response.Message;
    }

    private async Task CreateAsync()
    {
        ClearMessages();
        var response = await _apiService.PostJsonAsync<UserCreateResult>("users/crud.php", new
        {
            name = NewName,
            nickname = NewNickname,
            email = NewEmail,
            password = NewPassword,
            role = NewRole
        });

        if (!response.IsSuccess)
        {
            ErrorMessage = response.Message;
            return;
        }

        await LoadAsync();
        SuccessMessage = response.Data?.TemporaryPassword is { Length: > 0 } password
            ? $"User created. Temporary password: {password}"
            : "User created.";
        if (response.IsSuccess)
        {
            NewName = "";
            NewNickname = "";
            NewEmail = "";
            NewPassword = "";
            NewRole = "staff";
        }
    }

    private async Task UpdateAsync()
    {
        if (SelectedUser == null)
        {
            return;
        }

        var response = await _apiService.PutJsonAsync<object>("users/crud.php", new
        {
            id = SelectedUser.Id,
            name = SelectedUser.Name,
            nickname = SelectedUser.Nickname,
            email = SelectedUser.Email,
            role = SelectedUser.Role,
            password = string.IsNullOrWhiteSpace(ResetPassword) ? null : ResetPassword
        });
        await FinishMutationAsync(response, "User updated.");
        if (response.IsSuccess)
        {
            ResetPassword = "";
        }
    }

    private async Task DeleteAsync()
    {
        if (SelectedUser == null)
        {
            return;
        }

        var response = await _apiService.DeleteJsonAsync<object>("users/crud.php", new { id = SelectedUser.Id });
        await FinishMutationAsync(response, "User deleted.");
    }

    private async Task FinishMutationAsync(ApiResponse<object> response, string success)
    {
        ClearMessages();
        if (!response.IsSuccess)
        {
            ErrorMessage = response.Message;
            return;
        }

        SuccessMessage = success;
        await LoadAsync();
    }
}
