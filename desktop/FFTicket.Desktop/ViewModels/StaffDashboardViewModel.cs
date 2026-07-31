using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class StaffDashboardViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private Ticket? _selectedTicket;
    private string _search = "";

    public StaffDashboardViewModel(IApiService apiService, IFilePickerService filePickerService)
    {
        _apiService = apiService;
        CreateForm = new TicketCreateViewModel(apiService, filePickerService);
        CreateForm.TicketCreated += async () => await LoadTicketsAsync();
        RefreshCommand = new AsyncRelayCommand(LoadAsync);
        OpenDetailCommand = new AsyncRelayCommand(OpenDetailAsync);
        _ = LoadAsync();
    }

    public TicketCreateViewModel CreateForm { get; }
    internal IApiService ApiService => _apiService;
    public ObservableCollection<Ticket> Tickets { get; } = [];
    public ObservableCollection<Ticket> VisibleTickets { get; } = [];
    public event Action<Ticket>? DetailRequested;
    public IAsyncRelayCommand RefreshCommand { get; }
    public IAsyncRelayCommand OpenDetailCommand { get; }

    public Ticket? SelectedTicket
    {
        get => _selectedTicket;
        set => SetProperty(ref _selectedTicket, value);
    }

    public string Search
    {
        get => _search;
        set
        {
            if (SetProperty(ref _search, value))
            {
                ApplyFilter();
            }
        }
    }

    public bool HasVisibleTickets => VisibleTickets.Count > 0;

    public async Task LoadAsync()
    {
        await CreateForm.LoadOptionsAsync();
        await LoadTicketsAsync();
    }

    private async Task LoadTicketsAsync()
    {
        ClearMessages();
        IsBusy = true;
        var response = await _apiService.GetAsync<List<Ticket>>("tickets/index.php");
        IsBusy = false;
        Tickets.Clear();

        if (response.IsSuccess && response.Data != null)
        {
            foreach (var ticket in response.Data)
            {
                Tickets.Add(ticket);
            }
            ApplyFilter();
            return;
        }

        ApplyFilter();
        ErrorMessage = response.Message;
    }

    private async Task OpenDetailAsync()
    {
        if (SelectedTicket == null)
        {
            return;
        }

        DetailRequested?.Invoke(SelectedTicket);
        await Task.CompletedTask;
    }

    public Task RefreshTicketsAsync() => LoadTicketsAsync();

    private void ApplyFilter()
    {
        var query = Search.Trim();
        VisibleTickets.Clear();
        foreach (var ticket in Tickets.Where(ticket =>
                     string.IsNullOrWhiteSpace(query) ||
                     ticket.TicketNumber.Contains(query, StringComparison.OrdinalIgnoreCase) ||
                     ticket.Subject.Contains(query, StringComparison.OrdinalIgnoreCase) ||
                     ticket.LocationName.Contains(query, StringComparison.OrdinalIgnoreCase) ||
                     ticket.Status.Contains(query, StringComparison.OrdinalIgnoreCase)))
        {
            VisibleTickets.Add(ticket);
        }

        if (SelectedTicket != null && !VisibleTickets.Contains(SelectedTicket))
        {
            SelectedTicket = null;
        }
        OnPropertyChanged(nameof(HasVisibleTickets));
    }
}
