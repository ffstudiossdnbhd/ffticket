using System.Globalization;
using System.Windows;
using System.Windows.Data;

namespace FFTicket.Desktop.Converters;

public sealed class RoleToVisibilityConverter : IValueConverter
{
    public object Convert(object value, Type targetType, object parameter, CultureInfo culture)
    {
        var role = value?.ToString();
        var allowed = parameter?.ToString()?.Split(',', StringSplitOptions.TrimEntries | StringSplitOptions.RemoveEmptyEntries) ?? [];
        return allowed.Contains(role) ? Visibility.Visible : Visibility.Collapsed;
    }

    public object ConvertBack(object value, Type targetType, object parameter, CultureInfo culture) =>
        Binding.DoNothing;
}

