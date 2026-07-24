using Microsoft.UI.Xaml.Data;

namespace FFTicket.Desktop.Converters;

public sealed class NullToBooleanConverter : IValueConverter
{
    public object Convert(object value, Type targetType, object parameter, string language) => value != null;

    public object ConvertBack(object value, Type targetType, object parameter, string language) =>
        throw new NotSupportedException();
}
