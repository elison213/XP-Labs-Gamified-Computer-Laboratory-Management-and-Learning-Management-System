using System;
using System.Diagnostics;
using System.Runtime.InteropServices;
using System.Text;

namespace XPLabs.Widget
{
    internal sealed class DesktopActivitySampler
    {
        private string _lastTitle = "";
        private string _lastProcess = "";
        private DateTime _lastEmitUtc = DateTime.MinValue;

        public ActivitySample Sample()
        {
            var title = GetForegroundTitle();
            var process = GetForegroundProcessName();
            var idleSeconds = GetIdleSeconds();

            var changed = !string.Equals(title, _lastTitle, StringComparison.OrdinalIgnoreCase)
                || !string.Equals(process, _lastProcess, StringComparison.OrdinalIgnoreCase);
            var heartbeatDue = (DateTime.UtcNow - _lastEmitUtc).TotalSeconds >= 90;

            if (!changed && !heartbeatDue && idleSeconds < 120)
            {
                return null;
            }

            _lastTitle = title;
            _lastProcess = process;
            _lastEmitUtc = DateTime.UtcNow;

            var eventType = idleSeconds >= 120 ? "idle" : "app_focus";
            return new ActivitySample
            {
                EventType = eventType,
                Title = title,
                Process = process,
                IdleSeconds = idleSeconds,
                SampledAtUtc = DateTime.UtcNow
            };
        }

        private static string GetForegroundTitle()
        {
            try
            {
                var hwnd = GetForegroundWindow();
                if (hwnd == IntPtr.Zero)
                {
                    return "";
                }
                var sb = new StringBuilder(512);
                GetWindowText(hwnd, sb, sb.Capacity);
                return sb.ToString().Trim();
            }
            catch
            {
                return "";
            }
        }

        private static string GetForegroundProcessName()
        {
            try
            {
                var hwnd = GetForegroundWindow();
                if (hwnd == IntPtr.Zero)
                {
                    return "";
                }
                GetWindowThreadProcessId(hwnd, out var pid);
                if (pid == 0)
                {
                    return "";
                }
                using (var proc = Process.GetProcessById((int)pid))
                {
                    return proc.ProcessName ?? "";
                }
            }
            catch
            {
                return "";
            }
        }

        private static int GetIdleSeconds()
        {
            try
            {
                var info = new LastInputInfo { cbSize = (uint)Marshal.SizeOf(typeof(LastInputInfo)) };
                if (!GetLastInputInfo(ref info))
                {
                    return 0;
                }
                var idleMs = unchecked((uint)Environment.TickCount - info.dwTime);
                return (int)(idleMs / 1000);
            }
            catch
            {
                return 0;
            }
        }

        [StructLayout(LayoutKind.Sequential)]
        private struct LastInputInfo
        {
            public uint cbSize;
            public uint dwTime;
        }

        [DllImport("user32.dll")]
        private static extern IntPtr GetForegroundWindow();

        [DllImport("user32.dll", CharSet = CharSet.Unicode)]
        private static extern int GetWindowText(IntPtr hWnd, StringBuilder text, int count);

        [DllImport("user32.dll")]
        private static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint lpdwProcessId);

        [DllImport("user32.dll")]
        private static extern bool GetLastInputInfo(ref LastInputInfo plii);
    }

    internal sealed class ActivitySample
    {
        public string EventType { get; set; }
        public string Title { get; set; }
        public string Process { get; set; }
        public int IdleSeconds { get; set; }
        public DateTime SampledAtUtc { get; set; }
    }
}
