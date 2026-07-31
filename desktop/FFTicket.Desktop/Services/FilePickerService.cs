using Microsoft.UI.Xaml;
using Windows.Storage.Pickers;
using WinRT.Interop;

namespace FFTicket.Desktop.Services;

public sealed class FilePickerService(Func<Window> windowProvider) : IFilePickerService
{
    public async Task<string?> PickAttachmentAsync()
    {
        var picker = new FileOpenPicker
        {
            SuggestedStartLocation = PickerLocationId.DocumentsLibrary,
            ViewMode = PickerViewMode.List
        };
        picker.FileTypeFilter.Add(".png");
        picker.FileTypeFilter.Add(".jpg");
        picker.FileTypeFilter.Add(".jpeg");
        picker.FileTypeFilter.Add(".pdf");

        InitializeWithWindow.Initialize(picker, WindowNative.GetWindowHandle(windowProvider()));
        var file = await picker.PickSingleFileAsync();
        return file?.Path;
    }

    public async Task<string?> PickCsvSavePathAsync(string suggestedFileName)
    {
        var picker = new FileSavePicker
        {
            SuggestedStartLocation = PickerLocationId.DocumentsLibrary,
            SuggestedFileName = Path.GetFileNameWithoutExtension(suggestedFileName)
        };
        picker.FileTypeChoices.Add("CSV file", [".csv"]);

        InitializeWithWindow.Initialize(picker, WindowNative.GetWindowHandle(windowProvider()));
        var file = await picker.PickSaveFileAsync();
        return file?.Path;
    }
}
