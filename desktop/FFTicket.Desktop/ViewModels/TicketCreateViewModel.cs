using System.Collections.ObjectModel;
using System.IO;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;
using Microsoft.Win32;

namespace FFTicket.Desktop.ViewModels;

public sealed class TicketCreateViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private Category? _selectedCategory;
    private string _subject = "";
    private string _description = "";
    private string _urgency = "Medium";
    private string? _attachmentPath;

    public TicketCreateViewModel(IApiService apiService)
    {
        _apiService = apiService;
        PickAttachmentCommand = new RelayCommand(PickAttachment);
        SubmitCommand = new AsyncRelayCommand(SubmitAsync);
    }

    public ObservableCollection<Category> Categories { get; } = [];
    public IReadOnlyList<string> Urgencies { get; } = ["Low", "Medium", "High", "Critical"];
    public IRelayCommand PickAttachmentCommand { get; }
    public IAsyncRelayCommand SubmitCommand { get; }
    public event Action? TicketCreated;

    public Category? SelectedCategory
    {
        get => _selectedCategory;
        set => SetProperty(ref _selectedCategory, value);
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

    public string Urgency
    {
        get => _urgency;
        set => SetProperty(ref _urgency, value);
    }

    public string? AttachmentPath
    {
        get => _attachmentPath;
        set => SetProperty(ref _attachmentPath, value);
    }

    public async Task LoadCategoriesAsync()
    {
        var response = await _apiService.GetAsync<List<Category>>("categories/index.php");
        Categories.Clear();
        if (response.IsSuccess && response.Data != null)
        {
            foreach (var category in response.Data)
            {
                Categories.Add(category);
            }
            SelectedCategory ??= Categories.FirstOrDefault();
        }
        else
        {
            ErrorMessage = response.Message;
        }
    }

    private void PickAttachment()
    {
        var dialog = new OpenFileDialog
        {
            Filter = "Allowed files (*.png;*.jpg;*.jpeg;*.pdf)|*.png;*.jpg;*.jpeg;*.pdf",
            CheckFileExists = true
        };

        if (dialog.ShowDialog() == true)
        {
            var info = new FileInfo(dialog.FileName);
            if (info.Length > 10 * 1024 * 1024)
            {
                ErrorMessage = "Attachments must be 10 MB or smaller.";
                return;
            }
            AttachmentPath = dialog.FileName;
        }
    }

    private async Task SubmitAsync()
    {
        ClearMessages();
        if (SelectedCategory == null || string.IsNullOrWhiteSpace(Subject) || string.IsNullOrWhiteSpace(Description))
        {
            ErrorMessage = "Category, subject, and description are required.";
            return;
        }

        IsBusy = true;
        var fields = new Dictionary<string, string>
        {
            ["category_id"] = SelectedCategory.Id.ToString(),
            ["subject"] = Subject.Trim(),
            ["description"] = Description.Trim(),
            ["urgency"] = Urgency
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
        Urgency = "Medium";
        SuccessMessage = "Ticket submitted.";
        TicketCreated?.Invoke();
    }
}
