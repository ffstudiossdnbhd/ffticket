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
    private readonly Uri _apiRoot;
    private readonly JsonSerializerOptions _jsonOptions = new() { PropertyNameCaseInsensitive = true };
    private Func<CancellationToken, Task<bool>>? _sessionRefreshHandler;

    public ApiService()
    {
        LoadEnvironment();

        var baseUrl = Environment.GetEnvironmentVariable("API_BASE_URL") ?? "http://localhost/ffticket/backend/api";
        var timeoutText = Environment.GetEnvironmentVariable("API_TIMEOUT_SECONDS") ?? "30";
        var timeout = int.TryParse(timeoutText, out var seconds) ? seconds : 30;
        _apiRoot = new Uri(baseUrl.TrimEnd('/') + "/");

        _httpClient = new HttpClient
        {
            BaseAddress = _apiRoot,
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

    public void ConfigureSessionRefresh(Func<CancellationToken, Task<bool>>? refreshHandler) =>
        _sessionRefreshHandler = refreshHandler;

    public Task<ApiResponse<T>> GetAsync<T>(string path, CancellationToken cancellationToken = default) =>
        SendAsync<T>(() => new HttpRequestMessage(HttpMethod.Get, path), cancellationToken, CanRefreshForPath(path));

    public Task<ApiResponse<T>> PostJsonAsync<T>(string path, object payload, CancellationToken cancellationToken = default) =>
        SendAsync<T>(() => CreateJsonRequest(HttpMethod.Post, path, payload), cancellationToken, CanRefreshForPath(path));

    public Task<ApiResponse<T>> PutJsonAsync<T>(string path, object payload, CancellationToken cancellationToken = default) =>
        SendAsync<T>(() => CreateJsonRequest(HttpMethod.Put, path, payload), cancellationToken, CanRefreshForPath(path));

    public Task<ApiResponse<T>> DeleteJsonAsync<T>(string path, object payload, CancellationToken cancellationToken = default) =>
        SendAsync<T>(() => CreateJsonRequest(HttpMethod.Delete, path, payload), cancellationToken, CanRefreshForPath(path));

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
        }, cancellationToken, CanRefreshForPath(path));
    }

    public async Task<ApiResponse<byte[]>> DownloadAsync(string path, CancellationToken cancellationToken = default)
    {
        try
        {
            using var response = await _httpClient.GetAsync(path, cancellationToken);
            if (
                response.StatusCode == System.Net.HttpStatusCode.Unauthorized &&
                CanRefreshForPath(path) &&
                _sessionRefreshHandler != null &&
                await _sessionRefreshHandler(cancellationToken))
            {
                using var retriedResponse = await _httpClient.GetAsync(path, cancellationToken);
                return await ParseDownloadResponseAsync(retriedResponse, cancellationToken);
            }
            return await ParseDownloadResponseAsync(response, cancellationToken);
        }
        catch (TaskCanceledException)
        {
            return ApiResponse<byte[]>.Failure($"The request to {_apiRoot} timed out.");
        }
        catch (HttpRequestException)
        {
            return ApiResponse<byte[]>.Failure($"Unable to reach the FFTicket API at {_apiRoot}.");
        }
        catch (IOException)
        {
            return ApiResponse<byte[]>.Failure("Unable to read the downloaded attachment.");
        }
    }

    private async Task<ApiResponse<byte[]>> ParseDownloadResponseAsync(HttpResponseMessage response, CancellationToken cancellationToken)
    {
        if (!response.IsSuccessStatusCode)
        {
            var body = await response.Content.ReadAsStringAsync(cancellationToken);
            ApiEnvelope<object>? envelope = null;
            if (!string.IsNullOrWhiteSpace(body))
            {
                try
                {
                    envelope = JsonSerializer.Deserialize<ApiEnvelope<object>>(body, _jsonOptions);
                }
                catch (JsonException)
                {
                    // A non-JSON error response falls back to the HTTP reason phrase.
                }
            }

            return ApiResponse<byte[]>.Failure(
                envelope?.Message ?? response.ReasonPhrase ?? "Unable to download the attachment.",
                (int)response.StatusCode);
        }

        var bytes = await response.Content.ReadAsByteArrayAsync(cancellationToken);
        return ApiResponse<byte[]>.Success(bytes, "Attachment downloaded.", (int)response.StatusCode);
    }

    private async Task<ApiResponse<T>> SendAsync<T>(
        Func<HttpRequestMessage> requestFactory,
        CancellationToken cancellationToken,
        bool allowSessionRefresh)
    {
        try
        {
            using var request = requestFactory();
            using var response = await _httpClient.SendAsync(request, cancellationToken);
            if (
                response.StatusCode == System.Net.HttpStatusCode.Unauthorized &&
                allowSessionRefresh &&
                _sessionRefreshHandler != null &&
                await _sessionRefreshHandler(cancellationToken))
            {
                return await SendAsync<T>(requestFactory, cancellationToken, allowSessionRefresh: false);
            }
            return await ParseResponseAsync<T>(response, cancellationToken);
        }
        catch (TaskCanceledException)
        {
            return ApiResponse<T>.Failure($"The request to {_apiRoot} timed out. Check your connection and API_BASE_URL.");
        }
        catch (HttpRequestException)
        {
            return ApiResponse<T>.Failure($"Unable to reach the FFTicket API at {_apiRoot}. Check API_BASE_URL in .env.");
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

    private async Task<ApiResponse<T>> ParseResponseAsync<T>(HttpResponseMessage response, CancellationToken cancellationToken)
    {
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

    private static bool CanRefreshForPath(string path) =>
        !path.StartsWith("auth/", StringComparison.OrdinalIgnoreCase);

    /*
     * The remaining helpers are intentionally below request handling so all authenticated
     * calls receive a single serialized refresh-and-retry attempt.
     */

    private static void LoadEnvironment()
    {
        try
        {
            var envPaths = new[]
            {
                Path.Combine(AppContext.BaseDirectory, ".env"),
                Path.Combine(Environment.CurrentDirectory, ".env")
            }
            .Where(File.Exists)
            .Distinct(StringComparer.OrdinalIgnoreCase)
            .ToArray();

            if (envPaths.Length > 0)
            {
                DotEnv.Load(options: new DotEnvOptions(envFilePaths: envPaths, overwriteExistingVars: true));
                return;
            }

            if (LoadEmbeddedEnvironment())
            {
                return;
            }

            DotEnv.Load();
        }
        catch
        {
            // Missing .env is handled by fallback defaults and user-facing API errors.
        }
    }

    private static bool LoadEmbeddedEnvironment()
    {
        using var stream = typeof(ApiService).Assembly.GetManifestResourceStream("FFTicket.Desktop.env");
        if (stream == null)
        {
            return false;
        }

        using var reader = new StreamReader(stream);
        while (reader.ReadLine() is { } line)
        {
            line = line.Trim();
            if (line == "" || line.StartsWith('#'))
            {
                continue;
            }

            var separator = line.IndexOf('=');
            if (separator < 1)
            {
                continue;
            }

            var key = line[..separator].Trim();
            if (key is not ("API_BASE_URL" or "API_TIMEOUT_SECONDS"))
            {
                continue;
            }

            var value = line[(separator + 1)..].Trim().Trim('"', '\'');
            if (value != "")
            {
                Environment.SetEnvironmentVariable(key, value);
            }
        }

        return true;
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
