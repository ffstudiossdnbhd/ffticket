using Microsoft.UI.Windowing;
using Microsoft.UI.Xaml;
using Windows.Graphics;
using WinRT.Interop;

namespace FFTicket.Desktop.Helpers;

public static class WindowHelper
{
    public static AppWindow Configure(Window window, int width, int height, int minWidth, int minHeight)
    {
        window.Title = "FFTicket";
        var handle = WindowNative.GetWindowHandle(window);
        var windowId = Microsoft.UI.Win32Interop.GetWindowIdFromWindow(handle);
        var appWindow = AppWindow.GetFromWindowId(windowId);
        appWindow.Title = "FFTicket";
        appWindow.Resize(new SizeInt32(width, height));

        var displayArea = DisplayArea.GetFromWindowId(windowId, DisplayAreaFallback.Primary);
        if (displayArea != null)
        {
            var workArea = displayArea.WorkArea;
            appWindow.Move(new PointInt32(
                workArea.X + Math.Max(0, (workArea.Width - width) / 2),
                workArea.Y + Math.Max(0, (workArea.Height - height) / 2)));
        }

        appWindow.Changed += (_, args) =>
        {
            if (!args.DidSizeChange)
            {
                return;
            }

            var current = appWindow.Size;
            if (current.Width < minWidth || current.Height < minHeight)
            {
                appWindow.Resize(new SizeInt32(
                    Math.Max(current.Width, minWidth),
                    Math.Max(current.Height, minHeight)));
            }
        };

        var iconPath = Path.Combine(AppContext.BaseDirectory, "Assets", "AppIconTaskbar.ico");
        if (File.Exists(iconPath))
        {
            appWindow.SetIcon(iconPath);
        }

        return appWindow;
    }
}
