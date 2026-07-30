using System.Text.Json.Serialization;

namespace FFTicket.Desktop.Models;

public sealed class UserNotification
{
    public long Id { get; set; }

    [JsonPropertyName("ticket_id")]
    public int TicketId { get; set; }

    [JsonPropertyName("event_type")]
    public string EventType { get; set; } = "";

    public string Title { get; set; } = "";
    public string Body { get; set; } = "";

    [JsonPropertyName("created_at")]
    public string CreatedAt { get; set; } = "";

    [JsonPropertyName("read_at")]
    public string? ReadAt { get; set; }
}
