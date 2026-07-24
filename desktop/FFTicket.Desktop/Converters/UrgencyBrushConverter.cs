using Microsoft.UI;
using Microsoft.UI.Xaml.Data;
using Microsoft.UI.Xaml.Media;

namespace FFTicket.Desktop.Converters;

public sealed class UrgencyBrushConverter : IValueConverter
{
    private static readonly SolidColorBrush CriticalBrush = CreateBrush(0xDC, 0x26, 0x26);
    private static readonly SolidColorBrush HighBrush = CreateBrush(0xEA, 0x58, 0x0C);
    private static readonly SolidColorBrush MediumBrush = CreateBrush(0x25, 0x63, 0xEB);
    private static readonly SolidColorBrush LowBrush = CreateBrush(0x16, 0xA3, 0x4A);
    private static readonly SolidColorBrush DefaultBrush = CreateBrush(0x6B, 0x72, 0x80);

    public object Convert(object value, Type targetType, object parameter, string language)
    {
        return value?.ToString() switch
        {
            "Critical" => CriticalBrush,
            "High" => HighBrush,
            "Medium" => MediumBrush,
            "Low" => LowBrush,
            _ => DefaultBrush
        };
    }

    public object ConvertBack(object value, Type targetType, object parameter, string language) =>
        throw new NotSupportedException();

    private static SolidColorBrush CreateBrush(byte red, byte green, byte blue)
    {
        return new SolidColorBrush(ColorHelper.FromArgb(0xFF, red, green, blue));
    }
}
