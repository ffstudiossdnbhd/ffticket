using System.Collections.ObjectModel;
using System.IO;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class TicketOverviewViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private Ticket? _selectedTicket;
    private User? _selectedAssignee;
    private string _statusFilter = "";
    private string _urgencyFilter = "";
    private string _search = "";
    private string _selectedStatus = "Open";
    private string _selectedUrgency = "Medium";

    public TicketOverviewViewModel(IApiService apiService, IAuthService authService)
    {
        _apiService = apiService;
        CanManageInternalNotes = authService.CurrentUser?.Role is "admin" or "it_staff";
        RefreshCommand = new AsyncRelayCommand(LoadAsync);
        UpdateSelectedCommand = new AsyncRelayCommand(UpdateSelectedAsync);
        OpenDetailCommand = new AsyncRelayCommand(OpenDetailAsync);
        ExportCommand = new AsyncRelayCommand(ExportAsync);
        _ = LoadAsync();
    }

    public ObservableCollection<Ticket> Tickets { get; } = [];
    public ObservableCollection<User> Users { get; } = [];
    public IReadOnlyList<string> Statuses { get; } = ["Open", "In Progress", "Pending User Input", "Closed"];
    public IReadOnlyList<string> Urgencies { get; } = ["Low", "Medium", "High", "Critical"];
    public bool CanManageInternalNotes { get; }
    public DateTime ReportFrom { get; set; } = DateTime.Today.AddDays(-30);
    public DateTime ReportTo { get; set; } = DateTime.Today;
    public IAsyncRelayCommand RefreshCommand { get; }
    public IAsyncRelayCommand UpdateSelectedCommand { get; }
    public IAsyncRelayCommand OpenDetailCommand { get; }
    public IAsyncRelayCommand ExportCommand { get; }

    public Ticket? SelectedTicket
    {
        get => _selectedTicket;
        set
        {
            if (SetProperty(ref _selectedTicket, value) && value != null)
            {
                SelectedStatus = value.Status;
                SelectedUrgency = value.Urgency;
                SelectedAssignee = Users.FirstOrDefault(u => u.Id == value.AssignedTo);
            }
        }
    }

    public User? SelectedAssignee
    {
        get => _selectedAssignee;
        set => SetProperty(ref _selectedAssignee, value);
    }

    public string StatusFilter
    {
        get => _statusFilter;
        set => SetProperty(ref _statusFilter, value);
    }

    public string UrgencyFilter
    {
        get => _urgencyFilter;
        set => SetProperty(ref _urgencyFilter, value);
    }

    public string Search
    {
        get => _search;
        set => SetProperty(ref _search, value);
    }

    public string SelectedStatus
    {
        get => _selectedStatus;
        set => SetProperty(ref _selectedStatus, value);
    }

    public string SelectedUrgency
    {
        get => _selectedUrgency;
        set => SetProperty(ref _selectedUrgency, value);
    }

    public async Task LoadAsync()
    {
        ClearMessages();
        IsBusy = true;
        await LoadUsersAsync();
        var query = new List<string>();
        if (!string.IsNullOrWhiteSpace(StatusFilter)) query.Add($"status={Uri.EscapeDataString(StatusFilter)}");
        if (!string.IsNullOrWhiteSpace(UrgencyFilter)) query.Add($"urgency={Uri.EscapeDataString(UrgencyFilter)}");
        if (!string.IsNullOrWhiteSpace(Search)) query.Add($"search={Uri.EscapeDataString(Search)}");

        var response = await _apiService.GetAsync<List<Ticket>>("tickets/index.php" + (query.Count == 0 ? "" : "?" + string.Join("&", query)));
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

    private async Task LoadUsersAsync()
    {
        var response = await _apiService.GetAsync<List<User>>("users/index.php");
        Users.Clear();
        if (response.IsSuccess && response.Data != null)
        {
            foreach (var user in response.Data.Where(u => u.Role is "admin" or "it_staff"))
            {
                Users.Add(user);
            }
        }
    }

    private async Task UpdateSelectedAsync()
    {
        if (SelectedTicket == null)
        {
            return;
        }

        IsBusy = true;
        var response = await _apiService.PutJsonAsync<object>("tickets/update.php", new
        {
            id = SelectedTicket.Id,
            status = SelectedStatus,
            urgency = SelectedUrgency,
            assigned_to = SelectedAssignee?.Id
        });
        IsBusy = false;

        if (!response.IsSuccess)
        {
            ErrorMessage = response.Message;
            return;
        }

        SuccessMessage = "Ticket updated.";
        await LoadAsync();
    }

    private async Task OpenDetailAsync()
    {
        if (SelectedTicket == null)
        {
            return;
        }

        var vm = new TicketDetailViewModel(_apiService, SelectedTicket.Id, CanManageInternalNotes);
        await vm.LoadAsync();
        new Views.TicketDetailWindow { DataContext = vm, Owner = App.Current.MainWindow }.ShowDialog();
        await LoadAsync();
    }

    private async Task ExportAsync()
    {
        ClearMessages();
        var path = $"reports/export.php?from={ReportFrom:yyyy-MM-dd}&to={ReportTo:yyyy-MM-dd}";
        var response = await _apiService.GetAsync<string>(path);
        if (!response.IsSuccess || response.Data == null)
        {
            ErrorMessage = response.Message;
            return;
        }

        var file = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), $"ffticket-report-{ReportFrom:yyyyMMdd}-{ReportTo:yyyyMMdd}.csv");
        await File.WriteAllTextAsync(file, response.Data);
        SuccessMessage = $"CSV exported to {file}.";
    }
}
