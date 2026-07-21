using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;

namespace FFTicket.Desktop.ViewModels;

public sealed class TicketDetailViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private readonly int _ticketId;
    private string _newInternalNote = "";

    public TicketDetailViewModel(IApiService apiService, int ticketId, bool canManageInternalNotes)
    {
        _apiService = apiService;
        _ticketId = ticketId;
        CanManageInternalNotes = canManageInternalNotes;
        LoadCommand = new AsyncRelayCommand(LoadAsync);
        AddNoteCommand = new AsyncRelayCommand(AddNoteAsync);
        MarkClosedCommand = new AsyncRelayCommand(MarkClosedAsync);
    }

    public Ticket? Ticket { get; private set; }
    public ObservableCollection<Attachment> Attachments { get; } = [];
    public ObservableCollection<AuditLog> AuditLogs { get; } = [];
    public ObservableCollection<TicketComment> Comments { get; } = [];
    public bool CanManageInternalNotes { get; }
    public IAsyncRelayCommand LoadCommand { get; }
    public IAsyncRelayCommand AddNoteCommand { get; }
    public IAsyncRelayCommand MarkClosedCommand { get; }

    public string NewInternalNote
    {
        get => _newInternalNote;
        set => SetProperty(ref _newInternalNote, value);
    }

    public async Task LoadAsync()
    {
        ClearMessages();
        var response = await _apiService.GetAsync<TicketDetail>($"tickets/detail.php?id={_ticketId}");
        if (!response.IsSuccess || response.Data == null)
        {
            ErrorMessage = response.Message;
            return;
        }

        Ticket = response.Data.Ticket;
        OnPropertyChanged(nameof(Ticket));
        Attachments.Clear();
        AuditLogs.Clear();
        Comments.Clear();

        foreach (var attachment in response.Data.Attachments)
        {
            Attachments.Add(attachment);
        }
        foreach (var log in response.Data.AuditLogs)
        {
            AuditLogs.Add(log);
        }
        foreach (var comment in response.Data.Comments)
        {
            Comments.Add(comment);
        }
    }

    private async Task AddNoteAsync()
    {
        if (!CanManageInternalNotes || string.IsNullOrWhiteSpace(NewInternalNote))
        {
            return;
        }

        var response = await _apiService.PostJsonAsync<object>("comments/create.php", new
        {
            ticket_id = _ticketId,
            body = NewInternalNote.Trim()
        });

        if (!response.IsSuccess)
        {
            ErrorMessage = response.Message;
            return;
        }

        NewInternalNote = "";
        await LoadAsync();
    }

    private async Task MarkClosedAsync()
    {
        var response = await _apiService.PutJsonAsync<object>("tickets/update.php", new
        {
            id = _ticketId,
            status = "Closed"
        });

        if (!response.IsSuccess)
        {
            ErrorMessage = response.Message;
            return;
        }

        await LoadAsync();
    }
}

