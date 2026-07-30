using System.Collections.ObjectModel;
using System.IO;
using CommunityToolkit.Mvvm.Input;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;
using Windows.Storage;
using Windows.System;

namespace FFTicket.Desktop.ViewModels;

public sealed class TicketDetailViewModel : ViewModelBase
{
    private readonly IApiService _apiService;
    private readonly int _ticketId;
    private readonly string _collaborationClientId;
    private string _newInternalNote = "";

    public TicketDetailViewModel(
        IApiService apiService,
        int ticketId,
        bool canManageInternalNotes,
        string? collaborationClientId = null)
    {
        _apiService = apiService;
        _ticketId = ticketId;
        _collaborationClientId = string.IsNullOrWhiteSpace(collaborationClientId)
            ? Guid.NewGuid().ToString("N")
            : collaborationClientId;
        CanManageInternalNotes = canManageInternalNotes;
        LoadCommand = new AsyncRelayCommand(LoadAsync);
        AddNoteCommand = new AsyncRelayCommand(AddNoteAsync);
        MarkClosedCommand = new AsyncRelayCommand(MarkClosedAsync);
        OpenAttachmentCommand = new AsyncRelayCommand<Attachment>(OpenAttachmentAsync);
    }

    public Ticket? Ticket { get; private set; }
    public ObservableCollection<Attachment> Attachments { get; } = [];
    public ObservableCollection<AuditLog> AuditLogs { get; } = [];
    public ObservableCollection<TicketComment> Comments { get; } = [];
    public ObservableCollection<TicketCollaborator> Collaborators { get; } = [];
    public bool CanManageInternalNotes { get; }
    public bool HasCollaborators => Collaborators.Count > 0;
    public event Action? TicketChanged;
    public IAsyncRelayCommand LoadCommand { get; }
    public IAsyncRelayCommand AddNoteCommand { get; }
    public IAsyncRelayCommand MarkClosedCommand { get; }
    public IAsyncRelayCommand<Attachment> OpenAttachmentCommand { get; }

    public string NewInternalNote
    {
        get => _newInternalNote;
        set => SetProperty(ref _newInternalNote, value);
    }

    public async Task LoadAsync()
    {
        ClearMessages();
        IsBusy = true;
        var response = await _apiService.GetAsync<TicketDetail>($"tickets/detail.php?id={_ticketId}");
        IsBusy = false;
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

        if (!CanManageInternalNotes)
        {
            // A detail that was successfully loaded is the point at which a ticket owner
            // has seen any IT/admin comments. A failed acknowledgement must keep the dot.
            var readResponse = await _apiService.PostJsonAsync<object>("comments/read.php", new { ticket_id = _ticketId });
            if (readResponse.IsSuccess && Ticket != null)
            {
                Ticket.HasUnreadTechComments = false;
            }
        }
    }

    public async Task RefreshCollaborationAsync(bool editing)
    {
        if (!CanManageInternalNotes)
        {
            return;
        }

        var response = await _apiService.PostJsonAsync<PresenceHeartbeat>("presence/heartbeat.php", new
        {
            client_id = _collaborationClientId,
            ticket_id = _ticketId,
            mode = editing ? "editing" : "viewing",
        });
        if (!response.IsSuccess || response.Data == null)
        {
            return;
        }

        Collaborators.Clear();
        foreach (var collaborator in response.Data.Collaborators)
        {
            Collaborators.Add(collaborator);
        }
        OnPropertyChanged(nameof(HasCollaborators));
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
        TicketChanged?.Invoke();
    }

    private async Task OpenAttachmentAsync(Attachment? attachment)
    {
        if (attachment == null)
        {
            return;
        }

        ErrorMessage = "";
        var response = await _apiService.DownloadAsync($"attachments/download.php?id={attachment.Id}");
        if (!response.IsSuccess || response.Data == null)
        {
            ErrorMessage = response.Message;
            return;
        }

        try
        {
            var originalName = Path.GetFileName(attachment.FileName);
            var sanitizedName = string.Concat(originalName.Select(character =>
                Path.GetInvalidFileNameChars().Contains(character) ? '_' : character));
            if (string.IsNullOrWhiteSpace(sanitizedName))
            {
                sanitizedName = "attachment";
            }

            var attachmentDirectory = Path.Combine(
                Path.GetTempPath(),
                "FFTicket",
                "Attachments",
                attachment.Id.ToString(System.Globalization.CultureInfo.InvariantCulture));
            Directory.CreateDirectory(attachmentDirectory);
            var attachmentPath = Path.Combine(attachmentDirectory, $"{attachment.Id}-{sanitizedName}");
            await File.WriteAllBytesAsync(attachmentPath, response.Data);

            var file = await StorageFile.GetFileFromPathAsync(attachmentPath);
            if (!await Launcher.LaunchFileAsync(file))
            {
                ErrorMessage = "Windows could not find an app that can open this attachment.";
            }
        }
        catch (Exception exception) when (
            exception is IOException or UnauthorizedAccessException or ArgumentException)
        {
            ErrorMessage = "Unable to open the downloaded attachment.";
        }
    }
}
