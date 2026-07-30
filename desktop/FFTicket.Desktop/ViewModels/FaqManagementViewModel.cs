using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class FaqManagementViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private Faq? _selectedFaq;
    private string _newTitle = "";
    private string _newDescription = "";

    public FaqManagementViewModel(IApiService apiService)
    {
        _apiService = apiService;
        RefreshCommand = new AsyncRelayCommand(LoadAsync);
        CreateCommand = new AsyncRelayCommand(CreateAsync);
        UpdateCommand = new AsyncRelayCommand(UpdateAsync);
        DeleteCommand = new AsyncRelayCommand(DeleteAsync);
        _ = LoadAsync();
    }

    public ObservableCollection<Faq> Faqs { get; } = [];
    public IAsyncRelayCommand RefreshCommand { get; }
    public IAsyncRelayCommand CreateCommand { get; }
    public IAsyncRelayCommand UpdateCommand { get; }
    public IAsyncRelayCommand DeleteCommand { get; }

    public Faq? SelectedFaq
    {
        get => _selectedFaq;
        set => SetProperty(ref _selectedFaq, value);
    }

    public string NewTitle
    {
        get => _newTitle;
        set => SetProperty(ref _newTitle, value);
    }

    public string NewDescription
    {
        get => _newDescription;
        set => SetProperty(ref _newDescription, value);
    }

    public async Task LoadAsync()
    {
        ClearMessages();
        var response = await _apiService.GetAsync<List<Faq>>("faqs/index.php");
        Faqs.Clear();
        if (!response.IsSuccess || response.Data == null)
        {
            ErrorMessage = response.Message;
            return;
        }

        foreach (var faq in response.Data)
        {
            Faqs.Add(faq);
        }
    }

    private async Task CreateAsync()
    {
        ClearMessages();
        if (string.IsNullOrWhiteSpace(NewTitle) || string.IsNullOrWhiteSpace(NewDescription))
        {
            ErrorMessage = "Enter an FAQ title and description.";
            return;
        }

        var response = await _apiService.PostJsonAsync<object>("faqs/crud.php", new
        {
            title = NewTitle.Trim(),
            description = NewDescription.Trim(),
        });
        await FinishMutationAsync(response, "FAQ created.");
        if (response.IsSuccess)
        {
            NewTitle = "";
            NewDescription = "";
        }
    }

    private async Task UpdateAsync()
    {
        if (SelectedFaq == null)
        {
            return;
        }

        var response = await _apiService.PutJsonAsync<object>("faqs/crud.php", new
        {
            id = SelectedFaq.Id,
            title = SelectedFaq.Title.Trim(),
            description = SelectedFaq.Description.Trim(),
        });
        await FinishMutationAsync(response, "FAQ updated.");
    }

    private async Task DeleteAsync()
    {
        if (SelectedFaq == null)
        {
            return;
        }

        var response = await _apiService.DeleteJsonAsync<object>("faqs/crud.php", new { id = SelectedFaq.Id });
        await FinishMutationAsync(response, "FAQ deleted.");
    }

    private async Task FinishMutationAsync(ApiResponse<object> response, string success)
    {
        ClearMessages();
        if (!response.IsSuccess)
        {
            ErrorMessage = response.Message;
            return;
        }

        SuccessMessage = success;
        await LoadAsync();
    }
}
