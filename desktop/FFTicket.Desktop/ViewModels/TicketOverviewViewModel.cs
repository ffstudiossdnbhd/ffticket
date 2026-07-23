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
    private string _statusFilter = "All";
    private string _urgencyFilter = "All";
    private string _search = "";
    private string _selectedStatus = "Open";
    private UrgencyType? _selectedUrgencyType;

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
    public ObservableCollection<UrgencyType> UrgencyTypes { get; } = [];
    public ObservableCollection<string> UrgencyFilterOptions { get; } = [];
    public IReadOnlyList<string> FilterStatuses { get; } = ["All", "Open", "In Progress", "Pending User Input", "Closed"];
    public IReadOnlyList<string> Statuses { get; } = ["Open", "In Progress", "Pending User Input", "Closed"];
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
                SelectedUrgencyType = UrgencyTypes.FirstOrDefault(u => u.Id == value.UrgencyTypeId)
                    ?? UrgencyTypes.FirstOrDefault(u => u.Name == value.Urgency);
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

    public UrgencyType? SelectedUrgencyType
    {
        get => _selectedUrgencyType;
        set => SetProperty(ref _selectedUrgencyType, value);
    }

    public async Task LoadAsync()
    {
        ClearMessages();
        IsBusy = true;
        await LoadUsersAsync();
        await LoadUrgencyTypesAsync();
        var query = new List<string>();
        if (!string.IsNullOrWhiteSpace(StatusFilter) && StatusFilter != "All") query.Add($"status={Uri.EscapeDataString(StatusFilter)}");
        if (!string.IsNullOrWhiteSpace(UrgencyFilter) && UrgencyFilter != "All") query.Add($"urgency={Uri.EscapeDataString(UrgencyFilter)}");
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
        var response = await _apiService.GetAsync<List<User>>("users/assignable.php");
        Users.Clear();
        if (response.IsSuccess && response.Data != null)
        {
            foreach (var user in response.Data)
            {
                Users.Add(user);
            }
        }
    }

    private async Task LoadUrgencyTypesAsync()
    {
        var response = await _apiService.GetAsync<List<UrgencyType>>("urgency-types/index.php?include_inactive=1");
        UrgencyTypes.Clear();
        UrgencyFilterOptions.Clear();
        UrgencyFilterOptions.Add("All");

        if (response.IsSuccess && response.Data != null)
        {
            foreach (var urgency in response.Data)
            {
                UrgencyTypes.Add(urgency);
                UrgencyFilterOptions.Add(urgency.Name);
            }

            if (!UrgencyFilterOptions.Contains(UrgencyFilter))
            {
                UrgencyFilter = "All";
            }
            return;
        }

        ErrorMessage = response.Message;
    }

    private async Task UpdateSelectedAsync()
    {
        if (SelectedTicket == null)
        {
            return;
        }

        IsBusy = true;
        var payload = new Dictionary<string, object?>
        {
            ["id"] = SelectedTicket.Id,
            ["status"] = SelectedStatus,
            ["assigned_to"] = SelectedAssignee?.Id
        };
        if (SelectedUrgencyType != null && SelectedUrgencyType.Id != SelectedTicket.UrgencyTypeId)
        {
            payload["urgency_type_id"] = SelectedUrgencyType.Id;
        }

        var response = await _apiService.PutJsonAsync<object>("tickets/update.php", payload);
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
