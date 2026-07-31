using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class CustomizeTicketViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private TicketOption? _selectedCategory;
    private TicketOption? _selectedUrgencyType;
    private TicketOption? _selectedLocation;
    private string _newCategoryName = "";
    private string _newCategoryDescription = "";
    private string _newUrgencyTypeName = "";
    private string _newUrgencyTypeDescription = "";
    private string _newLocationName = "";
    private string _newLocationDescription = "";

    public CustomizeTicketViewModel(IApiService apiService)
    {
        _apiService = apiService;
        RefreshCommand = new AsyncRelayCommand(LoadAsync);
        AddCategoryCommand = new AsyncRelayCommand(() => AddOptionAsync("categories/crud.php", NewCategoryName, NewCategoryDescription, () =>
        {
            NewCategoryName = "";
            NewCategoryDescription = "";
        }, "Category saved."));
        SaveCategoryCommand = new AsyncRelayCommand(() => SaveOptionAsync("categories/crud.php", SelectedCategory, "Category updated."));
        DeleteCategoryCommand = new AsyncRelayCommand(() => DeleteOptionAsync("categories/crud.php", SelectedCategory, "Category deactivated."));
        AddUrgencyTypeCommand = new AsyncRelayCommand(() => AddOptionAsync("urgency-types/crud.php", NewUrgencyTypeName, NewUrgencyTypeDescription, () =>
        {
            NewUrgencyTypeName = "";
            NewUrgencyTypeDescription = "";
        }, "Urgency type saved."));
        SaveUrgencyTypeCommand = new AsyncRelayCommand(() => SaveOptionAsync("urgency-types/crud.php", SelectedUrgencyType, "Urgency type updated."));
        DeleteUrgencyTypeCommand = new AsyncRelayCommand(() => DeleteOptionAsync("urgency-types/crud.php", SelectedUrgencyType, "Urgency type deactivated."));
        AddLocationCommand = new AsyncRelayCommand(() => AddOptionAsync("locations/crud.php", NewLocationName, NewLocationDescription, () =>
        {
            NewLocationName = "";
            NewLocationDescription = "";
        }, "Location saved."));
        SaveLocationCommand = new AsyncRelayCommand(() => SaveOptionAsync("locations/crud.php", SelectedLocation, "Location updated."));
        DeleteLocationCommand = new AsyncRelayCommand(() => DeleteOptionAsync("locations/crud.php", SelectedLocation, "Location deactivated."));
        _ = LoadAsync();
    }

    public ObservableCollection<TicketOption> Categories { get; } = [];
    public ObservableCollection<TicketOption> UrgencyTypes { get; } = [];
    public ObservableCollection<TicketOption> Locations { get; } = [];
    public IAsyncRelayCommand RefreshCommand { get; }
    public IAsyncRelayCommand AddCategoryCommand { get; }
    public IAsyncRelayCommand SaveCategoryCommand { get; }
    public IAsyncRelayCommand DeleteCategoryCommand { get; }
    public IAsyncRelayCommand AddUrgencyTypeCommand { get; }
    public IAsyncRelayCommand SaveUrgencyTypeCommand { get; }
    public IAsyncRelayCommand DeleteUrgencyTypeCommand { get; }
    public IAsyncRelayCommand AddLocationCommand { get; }
    public IAsyncRelayCommand SaveLocationCommand { get; }
    public IAsyncRelayCommand DeleteLocationCommand { get; }

    public TicketOption? SelectedCategory
    {
        get => _selectedCategory;
        set => SetProperty(ref _selectedCategory, value);
    }

    public TicketOption? SelectedUrgencyType
    {
        get => _selectedUrgencyType;
        set => SetProperty(ref _selectedUrgencyType, value);
    }

    public TicketOption? SelectedLocation
    {
        get => _selectedLocation;
        set => SetProperty(ref _selectedLocation, value);
    }

    public string NewCategoryName
    {
        get => _newCategoryName;
        set => SetProperty(ref _newCategoryName, value);
    }

    public string NewCategoryDescription
    {
        get => _newCategoryDescription;
        set => SetProperty(ref _newCategoryDescription, value);
    }

    public string NewUrgencyTypeName
    {
        get => _newUrgencyTypeName;
        set => SetProperty(ref _newUrgencyTypeName, value);
    }

    public string NewUrgencyTypeDescription
    {
        get => _newUrgencyTypeDescription;
        set => SetProperty(ref _newUrgencyTypeDescription, value);
    }

    public string NewLocationName
    {
        get => _newLocationName;
        set => SetProperty(ref _newLocationName, value);
    }

    public string NewLocationDescription
    {
        get => _newLocationDescription;
        set => SetProperty(ref _newLocationDescription, value);
    }

    public async Task LoadAsync()
    {
        ClearMessages();
        await LoadOptionsAsync("categories/index.php?include_inactive=1", Categories);
        await LoadOptionsAsync("urgency-types/index.php?include_inactive=1", UrgencyTypes);
        await LoadOptionsAsync("locations/index.php?include_inactive=1", Locations);
    }

    private async Task LoadOptionsAsync(string path, ObservableCollection<TicketOption> target)
    {
        var response = await _apiService.GetAsync<List<TicketOption>>(path);
        target.Clear();
        if (response.IsSuccess && response.Data != null)
        {
            foreach (var option in response.Data)
            {
                target.Add(option);
            }
            return;
        }

        ErrorMessage = response.Message;
    }

    private async Task AddOptionAsync(string path, string name, string description, Action clearForm, string successMessage)
    {
        ClearMessages();
        if (string.IsNullOrWhiteSpace(name))
        {
            ErrorMessage = "Name is required.";
            return;
        }

        var response = await _apiService.PostJsonAsync<object>(path, new
        {
            name = name.Trim(),
            description = description.Trim()
        });
        await FinishMutationAsync(response, successMessage);
        if (response.IsSuccess)
        {
            clearForm();
        }
    }

    private async Task SaveOptionAsync(string path, TicketOption? option, string successMessage)
    {
        ClearMessages();
        if (option == null || string.IsNullOrWhiteSpace(option.Name))
        {
            ErrorMessage = "Select an option and enter a name.";
            return;
        }

        var response = await _apiService.PutJsonAsync<object>(path, new
        {
            id = option.Id,
            name = option.Name.Trim(),
            description = option.Description?.Trim() ?? "",
            is_active = option.IsActive
        });
        await FinishMutationAsync(response, successMessage);
    }

    private async Task DeleteOptionAsync(string path, TicketOption? option, string successMessage)
    {
        ClearMessages();
        if (option == null)
        {
            return;
        }

        var response = await _apiService.DeleteJsonAsync<object>(path, new { id = option.Id });
        await FinishMutationAsync(response, successMessage);
    }

    private async Task FinishMutationAsync(ApiResponse<object> response, string successMessage)
    {
        if (!response.IsSuccess)
        {
            ErrorMessage = response.Message;
            return;
        }

        await LoadAsync();
        SuccessMessage = successMessage;
    }
}
