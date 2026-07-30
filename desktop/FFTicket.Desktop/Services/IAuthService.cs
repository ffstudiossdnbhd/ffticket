using FFTicket.Desktop.Models;

namespace FFTicket.Desktop.Services;

public interface IAuthService
{
    User? CurrentUser { get; }
    string DeviceId { get; }
    event Action? SessionInvalidated;
    Task<ApiResponse<User>> LoginAsync(string email, string password, bool rememberMe);
    Task<bool> TryRestoreSessionAsync();
    Task<bool> RefreshSessionAsync(CancellationToken cancellationToken = default);
    Task LogoutAsync();
}
