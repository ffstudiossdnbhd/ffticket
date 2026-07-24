using Microsoft.UI;
using Microsoft.UI.Xaml.Data;
using Microsoft.UI.Xaml.Media;

namespace FFTicket.Desktop.Converters;

public sealed class StatusBrushConverter : IValueConverter
{
    public object Convert(object value, Type targetType, object parameter, string language)
    {
        return new SolidColorBrush(value?.ToString() switch
        {
            "Open" => ColorHelper.FromArgb(0xFF, 0x1E, 0x88, 0xE5),
            "In Progress" => ColorHelper.FromArgb(0xFF, 0xFB, 0x8C, 0x00),
            "Pending User Input" => ColorHelper.FromArgb(0xFF, 0x8E, 0x24, 0xAA),
            "Closed" => ColorHelper.FromArgb(0xFF, 0x43, 0xA0, 0x47),
            _ => ColorHelper.FromArgb(0xFF, 0x75, 0x75, 0x75)
        });
    }

    public object ConvertBack(object value, Type targetType, object parameter, string language) =>
        throw new NotSupportedException();
}
