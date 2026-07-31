using Microsoft.UI.Xaml;
using Microsoft.UI.Xaml.Data;

namespace FFTicket.Desktop.Converters;

public sealed class RoleToVisibilityConverter : IValueConverter
{
    public object Convert(object value, Type targetType, object parameter, string language)
    {
        var role = value?.ToString();
        var allowed = parameter?.ToString()?.Split(',', StringSplitOptions.TrimEntries | StringSplitOptions.RemoveEmptyEntries) ?? [];
        return allowed.Contains(role) ? Visibility.Visible : Visibility.Collapsed;
    }

    public object ConvertBack(object value, Type targetType, object parameter, string language) =>
        throw new NotSupportedException();
}
