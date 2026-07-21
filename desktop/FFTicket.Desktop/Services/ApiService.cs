using System.Net.Http;
using System.Net.Http.Headers;
using System.IO;
using System.Text;
using System.Text.Json;
using dotenv.net;
using FFTicket.Desktop.Models;

namespace FFTicket.Desktop.Services;

public sealed class ApiService : IApiService, IDisposable
{
    private readonly HttpClient _httpClient;
    private readonly JsonSerializerOptions _jsonOptions = new() { PropertyNameCaseInsensitive = true };

    public ApiService()
    {
        try
        {
            DotEnv.Load();
        }
        catch
        {
            // Missing .env is handled by fallback defaults and user-facing API errors.
        }

        var baseUrl = Environment.GetEnvironmentVariable("API_BASE_URL") ?? "http://localhost/ffticket/backend/api";
        var timeoutText = Environment.GetEnvironmentVariable("API_TIMEOUT_SECONDS") ?? "30";
        var timeout = int.TryParse(timeoutText, out var seconds) ? seconds : 30;

        _httpClient = new HttpClient
        {
            BaseAddress = new Uri(baseUrl.TrimEnd('/') + "/"),
            Timeout = TimeSpan.FromSeconds(timeout)
        };
    }

    public string? Token
    {
        get => _httpClient.DefaultRequestHeaders.Authorization?.Parameter;
        set
        {
            _httpClient.DefaultRequestHeaders.Authorization =
                string.IsNullOrWhiteSpace(value) ? null : new AuthenticationHeaderValue("Bearer", value);
        }
    }

    public Task<ApiResponse<T>> GetAsync<T>(string path, CancellationToken cancellationToken = default) =>
        SendAsync<T>(() => new HttpRequestMessage(HttpMethod.Get, path), cancellationToken);

    public Task<ApiResponse<T>> PostJsonAsync<T>(string path, object payload, CancellationToken cancellationToken = default) =>
        SendAsync<T>(() => CreateJsonRequest(HttpMethod.Post, path, payload), cancellationToken);

    public Task<ApiResponse<T>> PutJsonAsync<T>(string path, object payload, CancellationToken cancellationToken = default) =>
        SendAsync<T>(() => CreateJsonRequest(HttpMethod.Put, path, payload), cancellationToken);

    public Task<ApiResponse<T>> DeleteJsonAsync<T>(string path, object payload, CancellationToken cancellationToken = default) =>
        SendAsync<T>(() => CreateJsonRequest(HttpMethod.Delete, path, payload), cancellationToken);

    public async Task<ApiResponse<T>> PostMultipartAsync<T>(string path, Dictionary<string, string> fields, string? filePath, CancellationToken cancellationToken = default)
    {
        return await SendAsync<T>(() =>
        {
            var content = new MultipartFormDataContent();
            foreach (var field in fields)
            {
                content.Add(new StringContent(field.Value, Encoding.UTF8), field.Key);
            }

            if (!string.IsNullOrWhiteSpace(filePath))
            {
                var file = new StreamContent(File.OpenRead(filePath));
                file.Headers.ContentType = new MediaTypeHeaderValue(GetContentType(filePath));
                content.Add(file, "attachment", Path.GetFileName(filePath));
            }

            return new HttpRequestMessage(HttpMethod.Post, path) { Content = content };
        }, cancellationToken);
    }

    private async Task<ApiResponse<T>> SendAsync<T>(Func<HttpRequestMessage> requestFactory, CancellationToken cancellationToken)
    {
        try
        {
            using var request = requestFactory();
            using var response = await _httpClient.SendAsync(request, cancellationToken);
            var body = await response.Content.ReadAsStringAsync(cancellationToken);

            if (response.Content.Headers.ContentType?.MediaType == "text/csv")
            {
                return ApiResponse<T>.Success((T)(object)body, "CSV exported.", (int)response.StatusCode);
            }

            ApiEnvelope<T>? envelope = null;
            if (!string.IsNullOrWhiteSpace(body))
            {
                envelope = JsonSerializer.Deserialize<ApiEnvelope<T>>(body, _jsonOptions);
            }

            var message = envelope?.Message ?? response.ReasonPhrase ?? "Unexpected API response.";
            if (!response.IsSuccessStatusCode || envelope?.Status == "error")
            {
                return ApiResponse<T>.Failure(message, (int)response.StatusCode);
            }

            return ApiResponse<T>.Success(envelope == null ? default : envelope.Data, message, (int)response.StatusCode);
        }
        catch (TaskCanceledException)
        {
            return ApiResponse<T>.Failure("The request timed out. Check your connection and try again.");
        }
        catch (HttpRequestException)
        {
            return ApiResponse<T>.Failure("Unable to reach the FFTicket API.");
        }
        catch (JsonException)
        {
            return ApiResponse<T>.Failure("The API returned a response the app could not read.");
        }
        catch (IOException)
        {
            return ApiResponse<T>.Failure("Unable to read the selected attachment.");
        }
    }

    private static HttpRequestMessage CreateJsonRequest(HttpMethod method, string path, object payload)
    {
        var json = JsonSerializer.Serialize(payload);
        return new HttpRequestMessage(method, path)
        {
            Content = new StringContent(json, Encoding.UTF8, "application/json")
        };
    }

    private static string GetContentType(string path)
    {
        return Path.GetExtension(path).ToLowerInvariant() switch
        {
            ".png" => "image/png",
            ".jpg" or ".jpeg" => "image/jpeg",
            ".pdf" => "application/pdf",
            _ => "application/octet-stream"
        };
    }

    public void Dispose() => _httpClient.Dispose();
}
