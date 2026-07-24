using System.Collections.ObjectModel;
using System.IO;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class TicketCreateViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private readonly IFilePickerService _filePickerService;
    private Category? _selectedCategory;
    private TicketLocation? _selectedLocation;
    private string _subject = "";
    private string _description = "";
    private string? _attachmentPath;

    private static readonly HashSet<string> AllowedExtensions = new(StringComparer.OrdinalIgnoreCase)
    {
        ".png", ".jpg", ".jpeg", ".pdf"
    };

    public TicketCreateViewModel(IApiService apiService, IFilePickerService filePickerService)
    {
        _apiService = apiService;
        _filePickerService = filePickerService;
        PickAttachmentCommand = new AsyncRelayCommand(PickAttachmentAsync);
        SubmitCommand = new AsyncRelayCommand(SubmitAsync);
    }

    public ObservableCollection<Category> Categories { get; } = [];
    public ObservableCollection<TicketLocation> Locations { get; } = [];
    public IAsyncRelayCommand PickAttachmentCommand { get; }
    public IAsyncRelayCommand SubmitCommand { get; }
    public event Action? TicketCreated;

    public Category? SelectedCategory
    {
        get => _selectedCategory;
        set => SetProperty(ref _selectedCategory, value);
    }

    public TicketLocation? SelectedLocation
    {
        get => _selectedLocation;
        set => SetProperty(ref _selectedLocation, value);
    }

    public string Subject
    {
        get => _subject;
        set => SetProperty(ref _subject, value);
    }

    public string Description
    {
        get => _description;
        set => SetProperty(ref _description, value);
    }

    public string? AttachmentPath
    {
        get => _attachmentPath;
        set => SetProperty(ref _attachmentPath, value);
    }

    public async Task LoadOptionsAsync()
    {
        var categoriesResponse = await _apiService.GetAsync<List<Category>>("categories/index.php");
        Categories.Clear();
        if (categoriesResponse.IsSuccess && categoriesResponse.Data != null)
        {
            foreach (var category in categoriesResponse.Data)
            {
                Categories.Add(category);
            }
            SelectedCategory ??= Categories.FirstOrDefault();
        }
        else
        {
            ErrorMessage = categoriesResponse.Message;
        }

        var locationsResponse = await _apiService.GetAsync<List<TicketLocation>>("locations/index.php");
        Locations.Clear();
        if (locationsResponse.IsSuccess && locationsResponse.Data != null)
        {
            foreach (var location in locationsResponse.Data)
            {
                Locations.Add(location);
            }
            SelectedLocation ??= Locations.FirstOrDefault();
        }
        else
        {
            ErrorMessage = locationsResponse.Message;
        }
    }

    private async Task PickAttachmentAsync()
    {
        var path = await _filePickerService.PickAttachmentAsync();
        if (path == null)
        {
            return;
        }

        if (!ValidateAttachment(path))
        {
            return;
        }

        AttachmentPath = path;
    }

    private async Task SubmitAsync()
    {
        ClearMessages();
        if (SelectedCategory == null || SelectedLocation == null || string.IsNullOrWhiteSpace(Subject) || string.IsNullOrWhiteSpace(Description))
        {
            ErrorMessage = "Category, location, subject, and description are required.";
            return;
        }

        if (AttachmentPath != null && !ValidateAttachment(AttachmentPath))
        {
            return;
        }

        IsBusy = true;
        var fields = new Dictionary<string, string>
        {
            ["category_id"] = SelectedCategory.Id.ToString(),
            ["location_id"] = SelectedLocation.Id.ToString(),
            ["subject"] = Subject.Trim(),
            ["description"] = Description.Trim()
        };

        var response = await _apiService.PostMultipartAsync<Ticket>("tickets/create.php", fields, AttachmentPath);
        IsBusy = false;

        if (!response.IsSuccess)
        {
            ErrorMessage = response.Message;
            return;
        }

        Subject = "";
        Description = "";
        AttachmentPath = null;
        SelectedLocation = Locations.FirstOrDefault();
        SuccessMessage = "Ticket submitted.";
        TicketCreated?.Invoke();
    }

    private bool ValidateAttachment(string path)
    {
        try
        {
            var extension = Path.GetExtension(path);
            if (!AllowedExtensions.Contains(extension))
            {
                ErrorMessage = "Attachments must be PNG, JPG, JPEG, or PDF files.";
                return false;
            }

            var info = new FileInfo(path);
            if (!info.Exists)
            {
                ErrorMessage = "The selected attachment no longer exists.";
                return false;
            }

            if (info.Length > 10 * 1024 * 1024)
            {
                ErrorMessage = "Attachments must be 10 MB or smaller.";
                return false;
            }

            return true;
        }
        catch (IOException)
        {
            ErrorMessage = "The selected attachment could not be read.";
            return false;
        }
        catch (UnauthorizedAccessException)
        {
            ErrorMessage = "FFTicket cannot access the selected attachment.";
            return false;
        }
    }
}
