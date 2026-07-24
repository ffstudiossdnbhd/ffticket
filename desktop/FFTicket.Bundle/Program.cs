using System.Diagnostics;
using System.IO.Compression;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Security.Cryptography;

internal static class Program
{
    private const string PayloadResourceName = "FFTicket.Payload.zip";
    private const string AppExecutableName = "FFTicket.exe";

    [STAThread]
    private static int Main()
    {
        try
        {
            using var payload = OpenPayload();
            var payloadId = GetPayloadId(payload);
            var runtimeDirectory = EnsurePayloadExtracted(payload, payloadId);
            var appPath = Path.Combine(runtimeDirectory, AppExecutableName);

            using var process = Process.Start(new ProcessStartInfo
            {
                FileName = appPath,
                WorkingDirectory = runtimeDirectory,
                UseShellExecute = false
            }) ?? throw new InvalidOperationException("Windows could not start FFTicket.");

            process.WaitForExit();
            return process.ExitCode;
        }
        catch (Exception exception)
        {
            MessageBox(
                IntPtr.Zero,
                $"FFTicket could not start.\n\n{exception.Message}",
                "FFTicket",
                0x00000010);
            return 1;
        }
    }

    private static Stream OpenPayload() =>
        Assembly.GetExecutingAssembly().GetManifestResourceStream(PayloadResourceName)
        ?? throw new InvalidOperationException("The embedded application payload is missing.");

    private static string GetPayloadId(Stream payload)
    {
        var hash = SHA256.HashData(payload);
        payload.Position = 0;
        return Convert.ToHexString(hash.AsSpan(0, 8)).ToLowerInvariant();
    }

    private static string EnsurePayloadExtracted(Stream payload, string payloadId)
    {
        var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);
        if (string.IsNullOrWhiteSpace(localAppData))
        {
            throw new InvalidOperationException("The local application-data folder is unavailable.");
        }

        var runtimeRoot = Path.Combine(localAppData, "FFTicket", "Runtime");
        var destination = Path.Combine(runtimeRoot, payloadId);
        var readyMarker = Path.Combine(destination, ".ready");
        Directory.CreateDirectory(runtimeRoot);

        using var extractionLock = new Mutex(false, $@"Local\FFTicket.Payload.{payloadId}");
        if (!extractionLock.WaitOne(TimeSpan.FromMinutes(5)))
        {
            throw new TimeoutException("Another FFTicket launch is still preparing the application.");
        }

        try
        {
            if (File.Exists(readyMarker) && File.Exists(Path.Combine(destination, AppExecutableName)))
            {
                return destination;
            }

            var staging = Path.Combine(runtimeRoot, $".extract-{payloadId}-{Environment.ProcessId}");
            DeleteDirectoryIfPresent(staging, runtimeRoot);
            Directory.CreateDirectory(staging);

            try
            {
                ExtractPayload(payload, staging);
                File.WriteAllText(Path.Combine(staging, ".ready"), payloadId);

                DeleteDirectoryIfPresent(destination, runtimeRoot);
                Directory.Move(staging, destination);
            }
            catch
            {
                DeleteDirectoryIfPresent(staging, runtimeRoot);
                throw;
            }

            return destination;
        }
        finally
        {
            extractionLock.ReleaseMutex();
        }
    }

    private static void ExtractPayload(Stream payload, string destination)
    {
        var destinationRoot = Path.GetFullPath(destination)
            .TrimEnd(Path.DirectorySeparatorChar) + Path.DirectorySeparatorChar;

        using var archive = new ZipArchive(payload, ZipArchiveMode.Read, leaveOpen: true);
        foreach (var entry in archive.Entries)
        {
            var normalizedName = entry.FullName.Replace('/', Path.DirectorySeparatorChar);
            var targetPath = Path.GetFullPath(Path.Combine(destinationRoot, normalizedName));
            if (!targetPath.StartsWith(destinationRoot, StringComparison.OrdinalIgnoreCase))
            {
                throw new InvalidDataException("The embedded application payload contains an invalid path.");
            }

            if (string.IsNullOrEmpty(entry.Name))
            {
                Directory.CreateDirectory(targetPath);
                continue;
            }

            Directory.CreateDirectory(Path.GetDirectoryName(targetPath)!);
            entry.ExtractToFile(targetPath, overwrite: true);
        }
    }

    private static void DeleteDirectoryIfPresent(string path, string expectedParent)
    {
        var fullPath = Path.GetFullPath(path);
        var parentRoot = Path.GetFullPath(expectedParent)
            .TrimEnd(Path.DirectorySeparatorChar) + Path.DirectorySeparatorChar;
        if (!fullPath.StartsWith(parentRoot, StringComparison.OrdinalIgnoreCase))
        {
            throw new InvalidOperationException("Refusing to modify a directory outside the FFTicket runtime cache.");
        }

        if (Directory.Exists(fullPath))
        {
            Directory.Delete(fullPath, recursive: true);
        }
    }

    [DllImport("user32.dll", CharSet = CharSet.Unicode)]
    private static extern int MessageBox(IntPtr windowHandle, string text, string caption, uint type);
}
