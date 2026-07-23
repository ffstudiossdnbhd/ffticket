using System.Text.Json.Serialization;

namespace FFTicket.Desktop.Models;

public sealed class Ticket
{
    public int Id { get; set; }

    [JsonPropertyName("ticket_number")]
    public string TicketNumber { get; set; } = "";

    [JsonPropertyName("user_id")]
    public int UserId { get; set; }

    [JsonPropertyName("assigned_to")]
    public int? AssignedTo { get; set; }

    [JsonPropertyName("category_id")]
    public int CategoryId { get; set; }

    [JsonPropertyName("urgency_type_id")]
    public int? UrgencyTypeId { get; set; }

    [JsonPropertyName("location_id")]
    public int LocationId { get; set; }

    public string Subject { get; set; } = "";
    public string Description { get; set; } = "";
    public string Status { get; set; } = "Open";
    public string? Urgency { get; set; }

    [JsonPropertyName("creator_name")]
    public string CreatorName { get; set; } = "";

    [JsonPropertyName("assignee_name")]
    public string? AssigneeName { get; set; }

    [JsonPropertyName("category_name")]
    public string CategoryName { get; set; } = "";

    [JsonPropertyName("location_name")]
    public string LocationName { get; set; } = "";

    [JsonPropertyName("created_at")]
    public string CreatedAt { get; set; } = "";

    [JsonPropertyName("updated_at")]
    public string UpdatedAt { get; set; } = "";

    [JsonPropertyName("closed_at")]
    public string? ClosedAt { get; set; }
}

public sealed class Category
{
    public int Id { get; set; }
    public string Name { get; set; } = "";
    public string? Description { get; set; }

    [JsonPropertyName("is_active")]
    public bool IsActive { get; set; } = true;
}

public sealed class UrgencyType
{
    public int Id { get; set; }
    public string Name { get; set; } = "";
    public string? Description { get; set; }

    [JsonPropertyName("is_active")]
    public bool IsActive { get; set; } = true;
}

public sealed class TicketLocation
{
    public int Id { get; set; }
    public string Name { get; set; } = "";
    public string? Description { get; set; }

    [JsonPropertyName("is_active")]
    public bool IsActive { get; set; } = true;
}

public sealed class TicketOption
{
    public int Id { get; set; }
    public string Name { get; set; } = "";
    public string? Description { get; set; }

    [JsonPropertyName("is_active")]
    public bool IsActive { get; set; } = true;
}

public sealed class Attachment
{
    public int Id { get; set; }

    [JsonPropertyName("file_name")]
    public string FileName { get; set; } = "";

    [JsonPropertyName("file_size")]
    public int FileSize { get; set; }

    [JsonPropertyName("file_type")]
    public string FileType { get; set; } = "";

    [JsonPropertyName("uploaded_at")]
    public string UploadedAt { get; set; } = "";
}

public sealed class AuditLog
{
    public int Id { get; set; }
    public string Action { get; set; } = "";
    public string? Notes { get; set; }

    [JsonPropertyName("performed_by_name")]
    public string PerformedByName { get; set; } = "";

    [JsonPropertyName("created_at")]
    public string CreatedAt { get; set; } = "";
}

public sealed class TicketComment
{
    public int Id { get; set; }
    public string Body { get; set; } = "";

    [JsonPropertyName("created_by_name")]
    public string CreatedByName { get; set; } = "";

    [JsonPropertyName("created_at")]
    public string CreatedAt { get; set; } = "";
}

public sealed class TicketDetail
{
    public Ticket Ticket { get; set; } = new();
    public List<Attachment> Attachments { get; set; } = [];

    [JsonPropertyName("audit_logs")]
    public List<AuditLog> AuditLogs { get; set; } = [];

    public List<TicketComment> Comments { get; set; } = [];
}
