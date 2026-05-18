using System;
using System.IO;
using System.Text;
using System.Windows;
using System.Windows.Threading;
using System.Windows.Controls;
using System.Diagnostics;

namespace XPLabs.LockScreen
{
    public partial class MainWindow : Window
    {
        private readonly string _statePath =
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "XPLabsAgent", "state.json");
        private readonly string _overrideRequestPath =
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "XPLabsAgent", "override_request.json");

        private readonly DispatcherTimer _timer = new DispatcherTimer();
        private KeyboardBlocker _blocker;
        private bool _lastLocked = true;
        private int _stateReadFailures = 0;

        public MainWindow()
        {
            InitializeComponent();

            Loaded += (_, __) =>
            {
                MakeFullscreen();
                _blocker = new KeyboardBlocker();
                _timer.Interval = TimeSpan.FromMilliseconds(500);
                _timer.Tick += (_, __2) => RefreshState();
                _timer.Start();
                RefreshState();
            };

            Deactivated += (_, __) =>
            {
                if (_lastLocked)
                {
                    KeepForeground();
                }
            };

            StateChanged += (_, __) =>
            {
                if (_lastLocked && WindowState != WindowState.Maximized)
                {
                    WindowState = WindowState.Maximized;
                }
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
            Activate();
            KeepForeground();
        }

        private void KeepForeground()
        {
            Topmost = false;
            Topmost = true;
            Activate();
            Focus();
        }

        private static bool IsExplorerRunning()
        {
            try
            {
                return Process.GetProcessesByName("explorer").Length > 0;
            }
            catch
            {
                return false;
            }
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
                    _stateReadFailures = 0;
                }
            }
            catch
            {
                _stateReadFailures++;
                if (_stateReadFailures > 3)
                {
                    // Fail-safe: remain locked when state cannot be read repeatedly.
                    locked = true;
                }
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
                if (IsExplorerRunning())
                {
                    InfoText.Text = (InfoText.Text + " Explorer shell detected; lockscreen enforcing foreground.").Trim();
                }
                OverrideButton.Visibility = Visibility.Visible;
                KeepForeground();
            }
            else
            {
                _blocker.Disable();
                StatusText.Text = "Unlocked";
                InfoText.Text = "";
                OverridePanel.Visibility = Visibility.Collapsed;
                OverrideButton.Visibility = Visibility.Collapsed;
                Hide();
            }

            _lastLocked = locked;
        }

        private void OverrideButton_Click(object sender, RoutedEventArgs e)
        {
            OverridePanel.Visibility = OverridePanel.Visibility == Visibility.Visible
                ? Visibility.Collapsed
                : Visibility.Visible;
            OverrideIdentifierInput.Focus();
        }

        private void SubmitOverrideButton_Click(object sender, RoutedEventArgs e)
        {
            var identifier = (OverrideIdentifierInput.Text ?? string.Empty).Trim();
            var password = OverridePasswordInput.Password ?? string.Empty;

            if (string.IsNullOrWhiteSpace(identifier) || string.IsNullOrWhiteSpace(password))
            {
                StatusText.Text = "Provide admin ID/email and password.";
                return;
            }

            try
            {
                var payload = "{\"identifier\":\"" + EscapeJson(identifier) + "\",\"password\":\"" + EscapeJson(password) + "\"}";
                Directory.CreateDirectory(Path.GetDirectoryName(_overrideRequestPath) ?? ".");
                File.WriteAllText(_overrideRequestPath, payload, Encoding.UTF8);
                StatusText.Text = "Admin override request submitted. Waiting for verification...";
                OverridePasswordInput.Password = "";
                OverridePanel.Visibility = Visibility.Collapsed;
            }
            catch
            {
                StatusText.Text = "Unable to submit override request.";
            }
        }

        private static string EscapeJson(string value)
        {
            return value.Replace("\\", "\\\\").Replace("\"", "\\\"");
        }
    }
}

