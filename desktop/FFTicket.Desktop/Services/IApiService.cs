using FFTicket.Desktop.Models;

namespace FFTicket.Desktop.Services;

public interface IApiService
{
    string? Token { get; set; }
    Task<ApiResponse<T>> GetAsync<T>(string path, CancellationToken cancellationToken = default);
    Task<ApiResponse<T>> PostJsonAsync<T>(string path, object payload, CancellationToken cancellationToken = default);
    Task<ApiResponse<T>> PutJsonAsync<T>(string path, object payload, CancellationToken cancellationToken = default);
    Task<ApiResponse<T>> DeleteJsonAsync<T>(string path, object payload, CancellationToken cancellationToken = default);
    Task<ApiResponse<T>> PostMultipartAsync<T>(string path, Dictionary<string, string> fields, string? filePath, CancellationToken cancellationToken = default);
    Task<ApiResponse<byte[]>> DownloadAsync(string path, CancellationToken cancellationToken = default);
}
