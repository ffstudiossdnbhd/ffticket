using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Controls;
using Microsoft.UI.Xaml.Media;

namespace FFTicket.Desktop.Controls;

public enum SemanticBadgeKind
{
    Status,
    Urgency
}

public sealed partial class SemanticBadge : UserControl
{
    public static readonly DependencyProperty TextProperty = DependencyProperty.Register(
        nameof(Text),
        typeof(string),
        typeof(SemanticBadge),
        new PropertyMetadata("", OnBadgePropertyChanged));

    public static readonly DependencyProperty KindProperty = DependencyProperty.Register(
        nameof(Kind),
        typeof(SemanticBadgeKind),
        typeof(SemanticBadge),
        new PropertyMetadata(SemanticBadgeKind.Status, OnBadgePropertyChanged));

    public SemanticBadge()
    {
        InitializeComponent();
        Loaded += (_, _) => ApplyPalette();
        ActualThemeChanged += (_, _) => ApplyPalette();
    }

    public string Text
    {
        get => (string)GetValue(TextProperty);
        set => SetValue(TextProperty, value);
    }

    public SemanticBadgeKind Kind
    {
        get => (SemanticBadgeKind)GetValue(KindProperty);
        set => SetValue(KindProperty, value);
    }

    private static void OnBadgePropertyChanged(DependencyObject dependencyObject, DependencyPropertyChangedEventArgs args)
    {
        if (dependencyObject is SemanticBadge badge)
        {
            badge.ApplyPalette();
        }
    }

    private void ApplyPalette()
    {
        if (BadgeSurface == null || BadgeText == null)
        {
            return;
        }

        var token = Kind == SemanticBadgeKind.Status
            ? Text switch
            {
                "Open" => "Open",
                "In Progress" => "Progress",
                "Pending User Input" => "Pending",
                "Closed" => "Closed",
                _ => "Default"
            }
            : Text switch
            {
                "Critical" => "Critical",
                "High" => "High",
                "Medium" => "Medium",
                "Low" => "Low",
                _ => "Default"
            };

        BadgeSurface.Background = ResolveBrush($"Badge{token}BackgroundBrush");
        BadgeText.Foreground = ResolveBrush($"Badge{token}ForegroundBrush");
        BadgeText.Text = Text;
        Visibility = string.IsNullOrWhiteSpace(Text) ? Visibility.Collapsed : Visibility.Visible;
    }

    private Brush ResolveBrush(string key)
    {
        if (Resources.TryGetValue(key, out var local) && local is Brush localBrush)
        {
            return localBrush;
        }

        if (Application.Current.Resources.TryGetValue(key, out var appResource) && appResource is Brush appBrush)
        {
            return appBrush;
        }

        return (Brush)Application.Current.Resources["TextPrimaryBrush"];
    }
}
