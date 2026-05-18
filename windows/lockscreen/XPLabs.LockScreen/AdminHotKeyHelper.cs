using System;
using System.Runtime.InteropServices;
using System.Windows;
using System.Windows.Interop;

namespace XPLabs.LockScreen
{
    /// <summary>
    /// Registers Ctrl+Shift+X for local admin bypass (no password).
    /// </summary>
    public sealed class AdminHotKeyHelper : IDisposable
    {
        private const int WmHotkey = 0x0312;
        private const int HotkeyId = 0x5850;
        private readonly Window _window;
        private HwndSource _source;
        private bool _registered;

        public event EventHandler HotkeyPressed;

        public AdminHotKeyHelper(Window window)
        {
            _window = window ?? throw new ArgumentNullException(nameof(window));
        }

        public void Register()
        {
            if (_registered) return;
            _window.SourceInitialized += OnSourceInitialized;
            if (_window.IsLoaded)
            {
                TryAttach();
            }
        }

        private void OnSourceInitialized(object sender, EventArgs e)
        {
            TryAttach();
        }

        private void TryAttach()
        {
            if (_registered) return;
            var helper = new WindowInteropHelper(_window);
            if (helper.Handle == IntPtr.Zero) return;

            _source = HwndSource.FromHwnd(helper.Handle);
            if (_source == null) return;
            _source.AddHook(WndProc);

            // MOD_CONTROL | MOD_SHIFT = 0x0002 | 0x0004 = 6, VK_X = 0x58
            if (!RegisterHotKey(helper.Handle, HotkeyId, 0x0002 | 0x0004, 0x58))
            {
                return;
            }
            _registered = true;
        }

        private IntPtr WndProc(IntPtr hwnd, int msg, IntPtr wParam, IntPtr lParam, ref bool handled)
        {
            if (msg == WmHotkey && wParam.ToInt32() == HotkeyId)
            {
                HotkeyPressed?.Invoke(this, EventArgs.Empty);
                handled = true;
            }
            return IntPtr.Zero;
        }

        public void Dispose()
        {
            if (_source != null)
            {
                _source.RemoveHook(WndProc);
                _source = null;
            }
            if (_registered)
            {
                try
                {
                    var helper = new WindowInteropHelper(_window);
                    if (helper.Handle != IntPtr.Zero)
                    {
                        UnregisterHotKey(helper.Handle, HotkeyId);
                    }
                }
                catch { /* ignore */ }
                _registered = false;
            }
        }

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool RegisterHotKey(IntPtr hWnd, int id, uint fsModifiers, uint vk);

        [DllImport("user32.dll", SetLastError = true)]
        private static extern bool UnregisterHotKey(IntPtr hWnd, int id);
    }
}
