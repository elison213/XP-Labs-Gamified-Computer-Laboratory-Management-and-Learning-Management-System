using System;
using System.IO;
using System.Text;
using System.Windows;
using System.Windows.Threading;

namespace XPLabs.LockScreen
{
    public partial class MainWindow : Window
    {
        private readonly string _statePath =
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "XPLabsAgent", "state.json");

        private readonly DispatcherTimer _timer = new DispatcherTimer();
        private KeyboardBlocker _blocker;
        private bool _lastLocked = true;

        public MainWindow()
        {
            InitializeComponent();

            Loaded += (_, __) =>
            {
                MakeFullscreen();
                _blocker = new KeyboardBlocker();
                _timer.Interval = TimeSpan.FromSeconds(1);
                _timer.Tick += (_, __2) => RefreshState();
                _timer.Start();
                RefreshState();
            };

            Closing += (_, e) =>
            {
                // Resist simple Alt+F4 close attempts; the scheduled task should keep it running anyway.
                e.Cancel = true;
            };
        }

        private void MakeFullscreen()
        {
            WindowState = WindowState.Maximized;
            Left = 0;
            Top = 0;
            Topmost = true;
            Focus();
        }

        private void RefreshState()
        {
            var locked = true;
            var lastLrn = "";
            var lastUnlockAt = "";

            try
            {
                if (File.Exists(_statePath))
                {
                    var json = File.ReadAllText(_statePath, Encoding.UTF8);
                    locked = JsonTiny.TryGetBool(json, "locked", defaultValue: true);
                    lastLrn = JsonTiny.TryGetString(json, "last_lrn", defaultValue: "");
                    lastUnlockAt = JsonTiny.TryGetString(json, "last_unlock_at", defaultValue: "");
                }
            }
            catch
            {
                // ignore
            }

            if (locked)
            {
                if (!_lastLocked)
                {
                    Show();
                    MakeFullscreen();
                }
                _blocker.Enable();
                StatusText.Text = "Waiting for unlock...";
                InfoText.Text = string.IsNullOrWhiteSpace(lastLrn)
                    ? ""
                    : $"Last LRN: {lastLrn}  (last unlock: {lastUnlockAt})";
            }
            else
            {
                _blocker.Disable();
                StatusText.Text = "Unlocked";
                InfoText.Text = "";
                Hide();
            }

            _lastLocked = locked;
        }
    }
}

