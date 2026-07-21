using FFTicket.Desktop.Models;

namespace FFTicket.Desktop.Services;

public interface IAuthService
{
    User? CurrentUser { get; }
    Task<ApiResponse<User>> LoginAsync(string email, string password, bool rememberMe);
    bool TryRestoreSession();
    void Logout();
}
