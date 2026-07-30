using System.Text.Json.Serialization;

namespace FFTicket.Desktop.Models;

public sealed class Faq
{
    public int Id { get; set; }
    public string Title { get; set; } = "";
    public string Description { get; set; } = "";

    [JsonPropertyName("created_at")]
    public string CreatedAt { get; set; } = "";

    [JsonPropertyName("updated_at")]
    public string UpdatedAt { get; set; } = "";
}

public sealed class TicketCollaborator
{
    [JsonPropertyName("user_id")]
    public int UserId { get; set; }

    public string Name { get; set; } = "";
    public string Mode { get; set; } = "viewing";

    public string DisplayState => $"{Name} ({Mode})";
}

public sealed class TimeoutState
{
    [JsonPropertyName("release_at")]
    public string? ReleaseAt { get; set; }

    [JsonPropertyName("release_at_myt")]
    public string? ReleaseAtMyt { get; set; }

    public bool Warning { get; set; }
}

public sealed class PresenceHeartbeat
{
    public List<TicketCollaborator> Collaborators { get; set; } = [];
    public TimeoutState? Timeout { get; set; }
}

public sealed class TimeoutUser
{
    public int Id { get; set; }
    public string Name { get; set; } = "";
    public string? Nickname { get; set; }
    public string Email { get; set; } = "";
    public string Role { get; set; } = "";
    public bool Online { get; set; }

    [JsonPropertyName("timed_out")]
    public bool TimedOut { get; set; }

    [JsonPropertyName("timeout_warning")]
    public bool TimeoutWarning { get; set; }

    [JsonPropertyName("release_at_myt")]
    public string? ReleaseAtMyt { get; set; }

    [JsonPropertyName("can_timeout")]
    public bool CanTimeout { get; set; }

    public string Status => TimedOut
        ? $"Timed out until {ReleaseAtMyt} MYT"
        : Online ? "Online" : "Offline";
}
