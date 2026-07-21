using System.Globalization;
using System.Windows.Data;
using System.Windows.Media;

namespace FFTicket.Desktop.Converters;

public sealed class StatusBrushConverter : IValueConverter
{
    public object Convert(object value, Type targetType, object parameter, CultureInfo culture)
    {
        return new SolidColorBrush(value?.ToString() switch
        {
            "Open" => Color.FromRgb(0x1E, 0x88, 0xE5),
            "In Progress" => Color.FromRgb(0xFB, 0x8C, 0x00),
            "Pending User Input" => Color.FromRgb(0x8E, 0x24, 0xAA),
            "Closed" => Color.FromRgb(0x43, 0xA0, 0x47),
            _ => Color.FromRgb(0x75, 0x75, 0x75)
        });
    }

    public object ConvertBack(object value, Type targetType, object parameter, CultureInfo culture) =>
        Binding.DoNothing;
}

