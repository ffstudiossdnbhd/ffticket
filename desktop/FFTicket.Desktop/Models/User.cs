using System.Text.Json.Serialization;

namespace FFTicket.Desktop.Models;

public sealed class User
{
    public int Id { get; set; }
    public string Name { get; set; } = "";
    public string Email { get; set; } = "";
    public string Role { get; set; } = "staff";

    [JsonPropertyName("created_at")]
    public string? CreatedAt { get; set; }

    [JsonPropertyName("updated_at")]
    public string? UpdatedAt { get; set; }
}

public sealed class LoginResult
{
    public string Token { get; set; } = "";
    public User User { get; set; } = new();
}

public sealed class UserCreateResult
{
    public int Id { get; set; }

    [JsonPropertyName("temporary_password")]
    public string TemporaryPassword { get; set; } = "";
}
