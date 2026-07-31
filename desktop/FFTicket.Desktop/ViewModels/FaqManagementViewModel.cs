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
    private int _newCategoryId;
    private int _selectedCategoryId;

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
    public ObservableCollection<Category> Categories { get; } = [];
    public IAsyncRelayCommand RefreshCommand { get; }
    public IAsyncRelayCommand CreateCommand { get; }
    public IAsyncRelayCommand UpdateCommand { get; }
    public IAsyncRelayCommand DeleteCommand { get; }

    public Faq? SelectedFaq
    {
        get => _selectedFaq;
        set
        {
            if (!SetProperty(ref _selectedFaq, value))
            {
                return;
            }

            SelectedCategoryId = _selectedFaq?.CategoryId ?? 0;
        }
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

    public int NewCategoryId
    {
        get => _newCategoryId;
        set => SetProperty(ref _newCategoryId, value);
    }

    public int SelectedCategoryId
    {
        get => _selectedCategoryId;
        set => SetProperty(ref _selectedCategoryId, value);
    }

    public async Task LoadAsync()
    {
        ClearMessages();
        var selectedFaqId = SelectedFaq?.Id;
        SelectedFaq = null;
        Faqs.Clear();
        Categories.Clear();

        var faqsResponse = await _apiService.GetAsync<List<Faq>>("faqs/index.php");
        if (!faqsResponse.IsSuccess || faqsResponse.Data == null)
        {
            ErrorMessage = faqsResponse.Message;
            return;
        }

        foreach (var faq in faqsResponse.Data)
        {
            Faqs.Add(faq);
        }

        var categoriesResponse = await _apiService.GetAsync<List<Category>>("categories/index.php?include_inactive=1");
        Categories.Clear();
        Categories.Add(new Category { Id = 0, Name = "Uncategorized" });
        if (categoriesResponse.IsSuccess && categoriesResponse.Data != null)
        {
            foreach (var category in categoriesResponse.Data)
            {
                Categories.Add(category);
            }
        }
        else if (!categoriesResponse.IsSuccess)
        {
            ErrorMessage = categoriesResponse.Message;
        }

        if (selectedFaqId > 0)
        {
            SelectedFaq = Faqs.FirstOrDefault(faq => faq.Id == selectedFaqId);
        }

        if (SelectedFaq == null)
        {
            SelectedCategoryId = 0;
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
            category_id = NewCategoryId > 0 ? NewCategoryId : (int?)null,
        });
        await FinishMutationAsync(response, "FAQ created.");
        if (response.IsSuccess)
        {
            NewTitle = "";
            NewDescription = "";
            NewCategoryId = 0;
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
            category_id = SelectedCategoryId > 0 ? SelectedCategoryId : (int?)null,
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
