using FFTicket.Desktop.Helpers;
using FFTicket.Desktop.Models;
using FFTicket.Desktop.Services;
using FFTicket.Desktop.ViewModels;
using Microsoft.UI.Windowing;
using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Media.Imaging;

namespace FFTicket.Desktop.Views;

public sealed partial class MainWindow : Window
{
    private readonly IAuthService _authService;
    private readonly IApiService _apiService;
    private readonly MainWindowViewModel _viewModel;
    private readonly DesktopSettingsService _settings;
    private readonly DesktopNotificationService _notifications;
    private readonly DesktopPresenceService _presence;
    private readonly AppWindow _appWindow;
    private readonly H.NotifyIcon.TaskbarIcon _trayIcon;
    private readonly bool _startMinimized;
    private bool _allowClose;
    private bool _disposed;
    private bool _timeoutWarningVisible;

    public MainWindow(
        IAuthService authService,
        IApiService apiService,
        IFilePickerService filePickerService,
        bool startMinimized)
    {
        InitializeComponent();
        _authService = authService;
        _apiService = apiService;
        _startMinimized = startMinimized;
        _settings = new DesktopSettingsService();
        _notifications = new DesktopNotificationService(authService, apiService, _settings, DispatcherQueue);
        _notifications.TicketRequested += Notification_TicketRequested;
        _presence = new DesktopPresenceService(authService, apiService);
        _presence.TimeoutWarning += Presence_TimeoutWarning;
        _trayIcon = new H.NotifyIcon.TaskbarIcon
        {
            ToolTipText = "FFTicket",
            IconSource = new BitmapImage(new Uri("ms-appx:///Assets/AppIconTaskbar.png")),
        };
        _trayIcon.ContextFlyout = CreateTrayMenu();
        _trayIcon.ForceCreate();

        _viewModel = new MainWindowViewModel(authService, apiService, filePickerService);
        _viewModel.LogoutRequested += OpenLoginWindow;
        _viewModel.ChangePasswordRequested += ShowChangePasswordDialog;
        _authService.SessionInvalidated += AuthService_SessionInvalidated;
        Root.DataContext = _viewModel;
        Root.Loaded += Root_Loaded;

        ExtendsContentIntoTitleBar = true;
        SetTitleBar(TitleBarDragRegion);
        _appWindow = WindowHelper.Configure(this, 1360, 860, 980, 640);
        _appWindow.Closing += AppWindow_Closing;

        if (_viewModel.CurrentView is StaffDashboardViewModel staff)
        {
            MainContent.Content = new StaffDashboardView { DataContext = staff };
        }
        else if (_viewModel.CurrentView is AdminDashboardViewModel admin)
        {
            var console = new AdminDashboardView { DataContext = admin };
            console.SearchAvailabilityChanged += enabled => SearchBox.IsEnabled = enabled;
            MainContent.Content = console;
        }
    }

    public async Task OpenTicketAsync(int ticketId)
    {
        if (ticketId < 1 || _disposed)
        {
            return;
        }

        WindowHelper.RestoreAndActivate(this);
        var canManageTicket = _authService.CurrentUser?.Role is "admin" or "it_staff";
        var detail = new TicketDetailViewModel(_apiService, ticketId, canManageTicket, _authService.DeviceId);
        await detail.LoadAsync();
        if (detail.Ticket == null)
        {
            return;
        }

        var dialog = new TicketDetailWindow
        {
            DataContext = detail,
            XamlRoot = Root.XamlRoot,
        };
        await dialog.ShowAsync();
    }

    public void HandleNotificationActivation(IReadOnlyDictionary<string, string> arguments) =>
        _notifications.HandleActivation(arguments);

    public void DisposeForNavigation()
    {
        if (_disposed)
        {
            return;
        }

        _disposed = true;
        _allowClose = true;
        _appWindow.Closing -= AppWindow_Closing;
        _notifications.TicketRequested -= Notification_TicketRequested;
        _notifications.Dispose();
        _presence.TimeoutWarning -= Presence_TimeoutWarning;
        _presence.Dispose();
        _trayIcon.Dispose();
        _authService.SessionInvalidated -= AuthService_SessionInvalidated;
    }

    private async void Root_Loaded(object sender, RoutedEventArgs e)
    {
        await _notifications.StartAsync();
        await _presence.StartAsync();
        if (_startMinimized && !_disposed)
        {
            WindowHelper.Hide(this);
        }
    }

    private void AppWindow_Closing(AppWindow sender, AppWindowClosingEventArgs args)
    {
        if (_allowClose)
        {
            return;
        }

        args.Cancel = true;
        WindowHelper.Hide(this);
    }

    private void Notification_TicketRequested(int ticketId) =>
        DispatcherQueue.TryEnqueue(() => _ = OpenTicketAsync(ticketId));

    private void TrayShow_Click(object sender, RoutedEventArgs e) =>
        WindowHelper.RestoreAndActivate(this);

    private void TraySettings_Click(object sender, RoutedEventArgs e) =>
        _ = ShowSettingsDialogAsync();

    private void TrayExit_Click(object sender, RoutedEventArgs e) =>
        ExitApplication();

    private MenuFlyout CreateTrayMenu()
    {
        var menu = new MenuFlyout();
        var show = new MenuFlyoutItem { Text = "Show FFTicket" };
        show.Click += TrayShow_Click;
        var settings = new MenuFlyoutItem { Text = "Settings" };
        settings.Click += TraySettings_Click;
        var exit = new MenuFlyoutItem { Text = "Exit" };
        exit.Click += TrayExit_Click;
        menu.Items.Add(show);
        menu.Items.Add(settings);
        menu.Items.Add(new MenuFlyoutSeparator());
        menu.Items.Add(exit);
        return menu;
    }

    private void AuthService_SessionInvalidated() =>
        DispatcherQueue.TryEnqueue(OpenLoginWindow);

    private void OpenLoginWindow()
    {
        if (_disposed)
        {
            return;
        }

        DisposeForNavigation();
        ((App)Application.Current).ShowLoginWindow();
    }

    private async void ShowChangePasswordDialog()
    {
        var dialog = new ChangePasswordWindow
        {
            DataContext = new ChangePasswordViewModel(_apiService),
            XamlRoot = Root.XamlRoot
        };
        await dialog.ShowAsync();
    }

    private void ShowFaqDialog(object sender, RoutedEventArgs e) => _ = ShowFaqDialogAsync();

    private async Task ShowFaqDialogAsync()
    {
        var faqSearch = new TextBox
        {
            PlaceholderText = "Search FAQs",
            IsEnabled = false,
        };
        var faqGroups = new StackPanel
        {
            Spacing = 8,
            HorizontalAlignment = HorizontalAlignment.Stretch,
        };
        var faqItems = new List<Faq>();
        var groupedContainer = new StackPanel
        {
            Spacing = 10,
            HorizontalAlignment = HorizontalAlignment.Stretch,
        };
        groupedContainer.Children.Add(new TextBlock { Text = "Loading FAQs...", TextWrapping = TextWrapping.Wrap });
        groupedContainer.Children.Add(faqGroups);
        var faqScrollViewer = new ScrollViewer
        {
            VerticalScrollBarVisibility = ScrollBarVisibility.Auto,
            HorizontalScrollBarVisibility = ScrollBarVisibility.Disabled,
            Content = groupedContainer,
        };
        var content = new Grid
        {
            Width = 480,
            Height = 640,
            RowDefinitions =
            {
                new RowDefinition { Height = GridLength.Auto },
                new RowDefinition { Height = new GridLength(1, GridUnitType.Star) },
            },
        };
        faqSearch.Margin = new Thickness(0, 0, 0, 12);
        Grid.SetRow(faqSearch, 0);
        Grid.SetRow(faqScrollViewer, 1);
        content.Children.Add(faqSearch);
        content.Children.Add(faqScrollViewer);

        var faqCategoryName = static string (Faq faq) =>
            string.IsNullOrWhiteSpace(faq.CategoryName) ? "Uncategorized" : faq.CategoryName!.Trim();
        var showMessage = (string message) =>
        {
            faqGroups.Children.Clear();
            faqGroups.Children.Add(new TextBlock
            {
                Text = message,
                TextWrapping = TextWrapping.Wrap,
            });
        };
        var renderFaqList = (string query) =>
        {
            faqGroups.Children.Clear();
            var normalized = query.Trim();
            var hasSearch = normalized.Length > 0;

            var filtered = normalized.Length > 0
                ? faqItems.Where((faq) => $"{faq.Title ?? ""}\n{faq.Description ?? ""}".Contains(normalized, StringComparison.OrdinalIgnoreCase))
                : faqItems;

            if (!filtered.Any())
            {
                showMessage(normalized.Length > 0 ? "No FAQs match your search." : "No FAQs have been published yet.");
                return;
            }

            var grouped = filtered
                .GroupBy(faqCategoryName, StringComparer.OrdinalIgnoreCase)
                .OrderBy(g => g.Key, StringComparer.OrdinalIgnoreCase);

            foreach (var group in grouped)
            {
                var cardList = new StackPanel
                {
                    Spacing = 6,
                    HorizontalAlignment = HorizontalAlignment.Stretch,
                };
                foreach (var item in group.OrderBy(faq => faq.Title).Select((faq, index) => new { faq, Number = index + 1 }))
                {
                    cardList.Children.Add(new StackPanel
                    {
                        Spacing = 4,
                        HorizontalAlignment = HorizontalAlignment.Stretch,
                        Children =
                        {
                            new TextBlock
                            {
                                Text = $"{item.Number}. {item.faq.Title}",
                                FontWeight = Microsoft.UI.Text.FontWeights.Bold,
                                TextWrapping = TextWrapping.Wrap,
                            },
                            new TextBlock
                            {
                                Text = item.faq.Description,
                                FontWeight = Microsoft.UI.Text.FontWeights.Normal,
                                TextWrapping = TextWrapping.Wrap,
                            },
                        },
                    });
                }

                var expander = new Expander
                {
                    Header = new TextBlock
                    {
                        Text = $"{group.Key} ({group.Count()})",
                        TextWrapping = TextWrapping.Wrap,
                    },
                    IsExpanded = hasSearch,
                    Margin = new Thickness(0, 0, 0, 4),
                    HorizontalAlignment = HorizontalAlignment.Stretch,
                    HorizontalContentAlignment = HorizontalAlignment.Stretch,
                    Content = cardList,
                };
                faqGroups.Children.Add(expander);
            }
        };
        faqSearch.TextChanged += (_, __) => renderFaqList(faqSearch.Text);

        var dialog = new ContentDialog
        {
            Title = "Frequently Asked Questions",
            Content = content,
            CloseButtonText = "Close",
            XamlRoot = Root.XamlRoot,
        };

        var response = await _apiService.GetAsync<List<Faq>>("faqs/index.php");
        faqSearch.IsEnabled = response.IsSuccess;
        if (response.IsSuccess && response.Data is { Count: > 0 })
        {
            faqItems.AddRange(response.Data);
            groupedContainer.Children.RemoveAt(0);
            renderFaqList("");
        }
        else if (response.IsSuccess)
        {
            groupedContainer.Children.RemoveAt(0);
            showMessage("No FAQs have been published yet.");
        }
        else
        {
            groupedContainer.Children.RemoveAt(0);
            showMessage(response.Message);
        }
        await dialog.ShowAsync();
    }

    private void ShowSettingsDialog(object sender, RoutedEventArgs e) =>
        _ = ShowSettingsDialogAsync();

    private async Task ShowSettingsDialogAsync()
    {
        var startupEnabled = await StartupTaskService.IsEnabledAsync();
        var notificationsToggle = new ToggleSwitch
        {
            Header = "Enable ticket notifications",
            IsOn = _settings.NotificationsEnabled,
        };
        var startupToggle = new ToggleSwitch
        {
            Header = "Start FFTicket when I sign in",
            IsOn = startupEnabled,
        };
        var message = new TextBlock
        {
            Text = "FFTicket starts minimized to the system tray after you sign in.",
            TextWrapping = TextWrapping.Wrap,
            Foreground = (Microsoft.UI.Xaml.Media.Brush)Application.Current.Resources["TextSecondaryBrush"],
        };
        var dialog = new ContentDialog
        {
            Title = "Desktop Settings",
            PrimaryButtonText = "Save",
            CloseButtonText = "Cancel",
            Content = new StackPanel
            {
                Spacing = 14,
                Children = { notificationsToggle, startupToggle, message },
            },
            XamlRoot = Root.XamlRoot,
        };

        if (await dialog.ShowAsync() != ContentDialogResult.Primary)
        {
            return;
        }

        await _notifications.SetEnabledAsync(notificationsToggle.IsOn);
        var startupWasEnabled = await StartupTaskService.SetEnabledAsync(startupToggle.IsOn);
        if (startupToggle.IsOn && !startupWasEnabled)
        {
            await ShowSettingsStatusAsync("FFTicket could not configure Windows startup. Try enabling it again from this screen.");
        }
    }

    private async Task ShowSettingsStatusAsync(string text)
    {
        var dialog = new ContentDialog
        {
            Title = "Desktop Settings",
            Content = text,
            CloseButtonText = "Close",
            XamlRoot = Root.XamlRoot,
        };
        await dialog.ShowAsync();
    }

    private async void ExitApplication()
    {
        if (_disposed)
        {
            return;
        }

        await _notifications.StopAsync();
        await _presence.StopAsync();
        DisposeForNavigation();
        Close();
    }

    private async void SearchBox_QuerySubmitted(AutoSuggestBox sender, AutoSuggestBoxQuerySubmittedEventArgs args)
    {
        await _viewModel.SubmitSearchAsync(args.QueryText);
    }

    private void Presence_TimeoutWarning(TimeoutState state)
    {
        DispatcherQueue.TryEnqueue(async () =>
        {
            if (_timeoutWarningVisible || _disposed)
            {
                return;
            }

            _timeoutWarningVisible = true;
            try
            {
                var dialog = new ContentDialog
                {
                    Title = "Account timeout scheduled",
                    Content = $"An administrator has scheduled a timeout. You will be signed out in one minute and remain blocked until {state.ReleaseAtMyt} MYT.",
                    CloseButtonText = "OK",
                    XamlRoot = Root.XamlRoot,
                };
                await dialog.ShowAsync();
            }
            finally
            {
                _timeoutWarningVisible = false;
            }
        });
    }
}
