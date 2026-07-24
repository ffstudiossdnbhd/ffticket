namespace FFTicket.Desktop.Services;

public interface IFilePickerService
{
    Task<string?> PickAttachmentAsync();
}
