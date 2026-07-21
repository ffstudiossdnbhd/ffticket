using System.Globalization;
using System.Windows.Data;

namespace FFTicket.Desktop.Converters;

public sealed class NullToBooleanConverter : IValueConverter
{
    public object Convert(object value, Type targetType, object parameter, CultureInfo culture) => value != null;

    public object ConvertBack(object value, Type targetType, object parameter, CultureInfo culture) =>
        Binding.DoNothing;
}

