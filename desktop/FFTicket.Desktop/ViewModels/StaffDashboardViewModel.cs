using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class StaffDashboardViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private Ticket? _selectedTicket;

    public StaffDashboardViewModel(IApiService apiService)
    {
        _apiService = apiService;
        CreateForm = new TicketCreateViewModel(apiService);
        CreateForm.TicketCreated += async () => await LoadTicketsAsync();
        RefreshCommand = new AsyncRelayCommand(LoadAsync);
        OpenDetailCommand = new AsyncRelayCommand(OpenDetailAsync);
        _ = LoadAsync();
    }

    public TicketCreateViewModel CreateForm { get; }
    public ObservableCollection<Ticket> Tickets { get; } = [];
    public IAsyncRelayCommand RefreshCommand { get; }
    public IAsyncRelayCommand OpenDetailCommand { get; }

    public Ticket? SelectedTicket
    {
        get => _selectedTicket;
        set => SetProperty(ref _selectedTicket, value);
    }

    public async Task LoadAsync()
    {
        await CreateForm.LoadCategoriesAsync();
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
            return;
        }

        ErrorMessage = response.Message;
    }

    private async Task OpenDetailAsync()
    {
        if (SelectedTicket == null)
        {
            return;
        }

        var vm = new TicketDetailViewModel(_apiService, SelectedTicket.Id, false);
        await vm.LoadAsync();
        new Views.TicketDetailWindow { DataContext = vm, Owner = App.Current.MainWindow }.ShowDialog();
        await LoadTicketsAsync();
    }
}

