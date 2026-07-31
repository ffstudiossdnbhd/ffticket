using System.Collections.ObjectModel;
using System.IO;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class TicketOverviewViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private readonly IFilePickerService _filePickerService;
    private readonly string _collaborationClientId;
    private Ticket? _selectedTicket;
    private User? _selectedAssignee;
    private string _statusFilter = "All";
    private string _urgencyFilter = "All";
    private string _search = "";
    private string _selectedStatus = "Open";
    private UrgencyType? _selectedUrgencyType;
    private TicketDetailViewModel? _detail;
    private bool _isDetailPaneOpen;
    private int _detailLoadVersion;
    private int _ticketLoadVersion;
    private bool _optionsLoaded;

    public TicketOverviewViewModel(
        IApiService apiService,
        IAuthService authService,
        IFilePickerService filePickerService)
    {
        _apiService = apiService;
        _filePickerService = filePickerService;
        _collaborationClientId = authService.DeviceId;
        CanManageInternalNotes = authService.CurrentUser?.Role is "admin" or "it_staff";
        RefreshCommand = new AsyncRelayCommand(RefreshAsync);
        UpdateSelectedCommand = new AsyncRelayCommand(UpdateSelectedAsync);
        OpenDetailCommand = new AsyncRelayCommand(OpenDetailAsync);
        CloseDetailCommand = new RelayCommand(CloseDetail);
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
    public DateTimeOffset ReportFrom { get; set; } = DateTimeOffset.Now.Date.AddDays(-30);
    public DateTimeOffset ReportTo { get; set; } = DateTimeOffset.Now.Date;
    public IAsyncRelayCommand RefreshCommand { get; }
    public IAsyncRelayCommand UpdateSelectedCommand { get; }
    public IAsyncRelayCommand OpenDetailCommand { get; }
    public IRelayCommand CloseDetailCommand { get; }
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

    public TicketDetailViewModel? Detail
    {
        get => _detail;
        private set => SetProperty(ref _detail, value);
    }

    public bool IsDetailPaneOpen
    {
        get => _isDetailPaneOpen;
        set => SetProperty(ref _isDetailPaneOpen, value);
    }

    public async Task LoadAsync()
    {
        var requestVersion = Interlocked.Increment(ref _ticketLoadVersion);
        ClearMessages();
        IsBusy = true;
        if (!_optionsLoaded)
        {
            var usersLoaded = await LoadUsersAsync();
            var urgencyTypesLoaded = await LoadUrgencyTypesAsync();
            _optionsLoaded = usersLoaded && urgencyTypesLoaded;
        }
        if (!TryValidateDateRange())
        {
            if (requestVersion == _ticketLoadVersion)
            {
                IsBusy = false;
            }
            return;
        }

        var query = BuildTicketQuery(includeDateFilters: true);
        var response = await FetchTicketsAsync(query);
        var retriedWithoutDates = false;
        if (
            !response.IsSuccess
            && query.Any(q => q.StartsWith("from=", StringComparison.Ordinal))
            && query.Any(q => q.StartsWith("to=", StringComparison.Ordinal))
            && IsDateValidationFailure(response.Message)
        )
        {
            ErrorMessage = "Date filters were not valid. Retrying without date constraints.";
            query = BuildTicketQuery(includeDateFilters: false);
            response = await FetchTicketsAsync(query);
            retriedWithoutDates = true;
        }
        if (requestVersion != _ticketLoadVersion)
        {
            return;
        }

        IsBusy = false;
        Tickets.Clear();
        if (response.IsSuccess && response.Data != null)
        {
            if (retriedWithoutDates)
            {
                ErrorMessage = "";
                SuccessMessage = "Tickets were loaded without date filters because the provided dates were invalid.";
            }
            foreach (var ticket in response.Data)
            {
                Tickets.Add(ticket);
            }
            return;
        }
        ErrorMessage = response.Message;
    }

    public Task ApplyDateFilterAsync() => LoadAsync();

    private async Task<bool> LoadUsersAsync()
    {
        var response = await _apiService.GetAsync<List<User>>("users/assignable.php");
        Users.Clear();
        if (response.IsSuccess && response.Data != null)
        {
            foreach (var user in response.Data)
            {
                Users.Add(user);
            }
            return true;
        }

        ErrorMessage = response.Message;
        return false;
    }

    private async Task<bool> LoadUrgencyTypesAsync()
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
            return true;
        }

        ErrorMessage = response.Message;
        return false;
    }

    private List<string> BuildTicketQuery(bool includeDateFilters)
    {
        var query = new List<string>();
        if (!string.IsNullOrWhiteSpace(StatusFilter) && StatusFilter != "All")
        {
            query.Add($"status={Uri.EscapeDataString(StatusFilter)}");
        }

        if (!string.IsNullOrWhiteSpace(UrgencyFilter) && UrgencyFilter != "All")
        {
            query.Add($"urgency={Uri.EscapeDataString(UrgencyFilter)}");
        }

        if (!string.IsNullOrWhiteSpace(Search))
        {
            query.Add($"search={Uri.EscapeDataString(Search)}");
        }

        if (includeDateFilters && ReportFrom != default && ReportTo != default && ReportFrom.Date <= ReportTo.Date)
        {
            query.Add($"from={ReportFrom:yyyy-MM-dd}");
            query.Add($"to={ReportTo:yyyy-MM-dd}");
        }

        return query;
    }

    private Task<ApiResponse<List<Ticket>>> FetchTicketsAsync(List<string> query) =>
        _apiService.GetAsync<List<Ticket>>("tickets/index.php" + (query.Count == 0 ? "" : "?" + string.Join("&", query)));

    private static bool IsDateValidationFailure(string message)
    {
        return message.Contains("Both from and to dates are required", StringComparison.OrdinalIgnoreCase)
            || message.Contains("Invalid date range", StringComparison.OrdinalIgnoreCase)
            || message.Contains("The from date must not be after the to date", StringComparison.OrdinalIgnoreCase);
    }

    private Task RefreshAsync()
    {
        _optionsLoaded = false;
        return LoadAsync();
    }

    private async Task UpdateSelectedAsync()
    {
        if (SelectedTicket == null)
        {
            return;
        }

        var selectedId = SelectedTicket.Id;
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
        var refreshed = Tickets.FirstOrDefault(ticket => ticket.Id == selectedId);
        if (refreshed != null)
        {
            await OpenTicketAsync(refreshed);
        }
    }

    public Task OpenTicketAsync(Ticket? ticket)
    {
        SelectedTicket = ticket;
        return OpenDetailAsync();
    }

    private async Task OpenDetailAsync()
    {
        if (SelectedTicket == null)
        {
            return;
        }

        var ticketId = SelectedTicket.Id;
        var requestVersion = Interlocked.Increment(ref _detailLoadVersion);
        IsDetailPaneOpen = true;
        var vm = new TicketDetailViewModel(_apiService, ticketId, CanManageInternalNotes, _collaborationClientId);
        vm.TicketChanged += () => _ = RefreshAfterDetailMutationAsync(ticketId);
        await vm.LoadAsync();
        if (requestVersion == _detailLoadVersion)
        {
            Detail = vm;
        }
    }

    private void CloseDetail()
    {
        Interlocked.Increment(ref _detailLoadVersion);
        IsDetailPaneOpen = false;
        Detail = null;
    }

    private async Task RefreshAfterDetailMutationAsync(int ticketId)
    {
        await LoadAsync();
        var refreshed = Tickets.FirstOrDefault(ticket => ticket.Id == ticketId);
        if (refreshed != null && IsDetailPaneOpen)
        {
            await OpenTicketAsync(refreshed);
        }
    }

    private async Task ExportAsync()
    {
        ClearMessages();
        if (!TryValidateDateRange())
        {
            return;
        }

        var path = $"reports/export.php?from={ReportFrom:yyyy-MM-dd}&to={ReportTo:yyyy-MM-dd}";
        var response = await _apiService.GetAsync<string>(path);
        if (!response.IsSuccess || response.Data == null)
        {
            ErrorMessage = response.Message;
            return;
        }

        var suggestedName = $"ffticket-report-{ReportFrom:yyyyMMdd}-{ReportTo:yyyyMMdd}.csv";
        var file = await _filePickerService.PickCsvSavePathAsync(suggestedName);
        if (string.IsNullOrWhiteSpace(file))
        {
            return;
        }

        try
        {
            await File.WriteAllTextAsync(file, response.Data, new System.Text.UTF8Encoding(encoderShouldEmitUTF8Identifier: true));
            SuccessMessage = $"CSV exported to {file}.";
        }
        catch (IOException)
        {
            ErrorMessage = "Unable to save the CSV to the selected location.";
        }
        catch (UnauthorizedAccessException)
        {
            ErrorMessage = "FFTicket does not have permission to save to the selected location.";
        }
    }

    private bool TryValidateDateRange()
    {
        if (ReportFrom.Date <= ReportTo.Date)
        {
            return true;
        }

        ErrorMessage = "Created From must not be after Created To.";
        return false;
    }
}
