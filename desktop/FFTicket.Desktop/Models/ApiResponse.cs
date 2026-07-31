using System.Text.Json.Serialization;

namespace FFTicket.Desktop.Models;

public sealed class ApiEnvelope<T>
{
    [JsonPropertyName("status")]
    public string Status { get; set; } = "";

    [JsonPropertyName("message")]
    public string Message { get; set; } = "";

    [JsonPropertyName("data")]
    public T? Data { get; set; }
}

public sealed class ApiResponse<T>
{
    public bool IsSuccess { get; init; }
    public string Message { get; init; } = "";
    public T? Data { get; init; }
    public int? StatusCode { get; init; }

    public static ApiResponse<T> Success(T? data, string message, int? statusCode = null) =>
        new() { IsSuccess = true, Data = data, Message = message, StatusCode = statusCode };

    public static ApiResponse<T> Failure(string message, int? statusCode = null) =>
        new() { IsSuccess = false, Message = message, StatusCode = statusCode };
}

