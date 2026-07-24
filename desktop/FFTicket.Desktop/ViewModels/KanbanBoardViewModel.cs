using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class KanbanBoardViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private readonly HashSet<int> _movingTicketIds = [];

    public KanbanBoardViewModel(IApiService apiService)
    {
        _apiService = apiService;
        RefreshCommand = new AsyncRelayCommand(LoadAsync);
        MoveToOpenCommand = new AsyncRelayCommand<Ticket>(ticket => MoveTicketAsync(ticket, "Open"));
        MoveToInProgressCommand = new AsyncRelayCommand<Ticket>(ticket => MoveTicketAsync(ticket, "In Progress"));
        MoveToPendingCommand = new AsyncRelayCommand<Ticket>(ticket => MoveTicketAsync(ticket, "Pending User Input"));
        MoveToClosedCommand = new AsyncRelayCommand<Ticket>(ticket => MoveTicketAsync(ticket, "Closed"));
        OpenDetailCommand = new AsyncRelayCommand<Ticket>(OpenDetailAsync);
        _ = LoadAsync();
    }

    public ObservableCollection<Ticket> OpenTickets { get; } = [];
    internal IApiService ApiService => _apiService;
    public ObservableCollection<Ticket> InProgressTickets { get; } = [];
    public ObservableCollection<Ticket> PendingTickets { get; } = [];
    public ObservableCollection<Ticket> ClosedTickets { get; } = [];
    public IAsyncRelayCommand RefreshCommand { get; }
    public IAsyncRelayCommand<Ticket> MoveToOpenCommand { get; }
    public IAsyncRelayCommand<Ticket> MoveToInProgressCommand { get; }
    public IAsyncRelayCommand<Ticket> MoveToPendingCommand { get; }
    public IAsyncRelayCommand<Ticket> MoveToClosedCommand { get; }
    public IAsyncRelayCommand<Ticket> OpenDetailCommand { get; }

    public async Task LoadAsync()
    {
        ClearMessages();
        var response = await _apiService.GetAsync<List<Ticket>>("tickets/index.php");
        OpenTickets.Clear();
        InProgressTickets.Clear();
        PendingTickets.Clear();
        ClosedTickets.Clear();

        if (!response.IsSuccess || response.Data == null)
        {
            ErrorMessage = response.Message;
            return;
        }

        foreach (var ticket in response.Data)
        {
            switch (ticket.Status)
            {
                case "In Progress":
                    InProgressTickets.Add(ticket);
                    break;
                case "Pending User Input":
                    PendingTickets.Add(ticket);
                    break;
                case "Closed":
                    ClosedTickets.Add(ticket);
                    break;
                default:
                    OpenTickets.Add(ticket);
                    break;
            }
        }
    }

    public async Task MoveTicketAsync(Ticket? ticket, string status)
    {
        if (
            ticket == null ||
            ticket.Status == status ||
            status is not ("Open" or "In Progress" or "Pending User Input" or "Closed") ||
            !_movingTicketIds.Add(ticket.Id))
        {
            return;
        }

        ClearMessages();
        var source = GetStatusCollection(ticket.Status);
        var target = GetStatusCollection(status);
        source.Remove(ticket);
        ticket.Status = status;
        target.Add(ticket);

        try
        {
            var response = await _apiService.PutJsonAsync<object>("tickets/update.php", new
            {
                id = ticket.Id,
                status
            });

            if (!response.IsSuccess)
            {
                await LoadAsync();
                ErrorMessage = response.Message;
                return;
            }

            await LoadAsync();
        }
        finally
        {
            _movingTicketIds.Remove(ticket.Id);
        }
    }

    private async Task OpenDetailAsync(Ticket? ticket)
    {
        if (ticket == null)
        {
            return;
        }

        DetailRequested?.Invoke(ticket);
        await Task.CompletedTask;
    }

    public event Action<Ticket>? DetailRequested;

    private ObservableCollection<Ticket> GetStatusCollection(string status) =>
        status switch
        {
            "In Progress" => InProgressTickets,
            "Pending User Input" => PendingTickets,
            "Closed" => ClosedTickets,
            _ => OpenTickets
        };
}
