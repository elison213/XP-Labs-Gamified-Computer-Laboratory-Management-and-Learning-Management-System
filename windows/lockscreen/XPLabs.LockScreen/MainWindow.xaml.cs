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
        private readonly string _studentLoginRequestPath =
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "XPLabsAgent", "student_login_request.json");
        private readonly string _adminHotkeyRequestPath =
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "XPLabsAgent", "admin_hotkey_request.json");

        private readonly DispatcherTimer _timer = new DispatcherTimer();
        private KeyboardBlocker _blocker;
        private AdminHotKeyHelper _adminHotKey;
        private bool _lastLocked = true;
        private bool _allowClose;
        private int _stateReadFailures = 0;
        private static readonly string WidgetExePath = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles),
            "XPLabsAgent", "Widget", "XPLabs.Widget.exe");

        public MainWindow()
        {
            InitializeComponent();

            Loaded += (_, __) =>
            {
                MakeFullscreen();
                _blocker = new KeyboardBlocker();
                _adminHotKey = new AdminHotKeyHelper(this);
                _adminHotKey.HotkeyPressed += (_, __) => SubmitAdminHotkeyBypass();
                _adminHotKey.Register();
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
                if (!_allowClose)
                {
                    e.Cancel = true;
                }
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
            var overrideStatus = "";
            var overrideMessage = "";
            var studentLoginStatus = "";
            var studentLoginMessage = "";

            try
            {
                if (File.Exists(_statePath))
                {
                    var json = File.ReadAllText(_statePath, Encoding.UTF8);
                    locked = JsonTiny.TryGetBool(json, "locked", defaultValue: true);
                    lastLrn = JsonTiny.TryGetString(json, "last_lrn", defaultValue: "");
                    lastUnlockAt = JsonTiny.TryGetString(json, "last_unlock_at", defaultValue: "");
                    overrideStatus = JsonTiny.TryGetString(json, "last_override_status", defaultValue: "");
                    overrideMessage = JsonTiny.TryGetString(json, "last_override_message", defaultValue: "");
                    studentLoginStatus = JsonTiny.TryGetString(json, "last_student_login_status", defaultValue: "");
                    studentLoginMessage = JsonTiny.TryGetString(json, "last_student_login_message", defaultValue: "");
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

            // Only dismiss when agent has unlocked; ignore stale "success" while locked=true (e.g. after Logon-Lockscreen.cmd).
            if (!locked && string.Equals(studentLoginStatus, "success", StringComparison.OrdinalIgnoreCase))
            {
                DismissLockscreenUi();
                return;
            }

            if (locked)
            {
                if (!_lastLocked)
                {
                    Show();
                    MakeFullscreen();
                }
                _blocker.Enable();
                if (File.Exists(_studentLoginRequestPath))
                {
                    var pendingFor = DateTime.UtcNow - File.GetLastWriteTimeUtc(_studentLoginRequestPath);
                    if (pendingFor.TotalSeconds > 45)
                    {
                        try { File.Delete(_studentLoginRequestPath); } catch { }
                        StatusText.Text = "Sign-in timed out. Check agent is running and server URL in agent.config.json.";
                        InfoText.Text = "Run Test-StudentLogin.ps1 on this PC or ask your instructor.";
                        StudentSignInButton.IsEnabled = true;
                    }
                    else
                    {
                        StatusText.Text = "Signing in...";
                    }
                }
                else if (!string.IsNullOrWhiteSpace(studentLoginMessage))
                {
                    StatusText.Text = studentLoginMessage;
                }
                else if (!string.IsNullOrWhiteSpace(overrideMessage))
                {
                    StatusText.Text = overrideMessage;
                }
                else
                {
                    StatusText.Text = "Sign in with your website LRN and password, or ask your instructor to unlock this PC.";
                }

                InfoText.Text = string.IsNullOrWhiteSpace(lastLrn)
                    ? ""
                    : $"Last LRN: {lastLrn}  (last unlock: {lastUnlockAt})";
                if (!string.IsNullOrWhiteSpace(overrideMessage) && string.IsNullOrWhiteSpace(studentLoginMessage))
                {
                    var prefix = string.Equals(overrideStatus, "success", StringComparison.OrdinalIgnoreCase)
                        ? "Admin: "
                        : "Admin: ";
                    InfoText.Text = (InfoText.Text + " " + prefix + overrideMessage).Trim();
                }
                if (IsExplorerRunning())
                {
                    InfoText.Text = (InfoText.Text + " Explorer shell detected; lockscreen enforcing foreground.").Trim();
                }
                StudentSignInButton.IsEnabled = !File.Exists(_studentLoginRequestPath);
                OverrideButton.Visibility = Visibility.Visible;
                KeepForeground();
            }
            else
            {
                DismissLockscreenUi();
                return;
            }

            _lastLocked = locked;
        }

        private void DismissLockscreenUi()
        {
            _blocker?.Disable();
            _lastLocked = false;
            _allowClose = true;
            _timer.Stop();
            _adminHotKey?.Dispose();
            Topmost = false;
            Hide();
            try { Application.Current.Shutdown(); } catch { Close(); }
        }

        private void StudentSignInButton_Click(object sender, RoutedEventArgs e)
        {
            SubmitStudentLogin();
        }

        private void StudentPasswordInput_KeyDown(object sender, System.Windows.Input.KeyEventArgs e)
        {
            if (e.Key == System.Windows.Input.Key.Enter)
            {
                SubmitStudentLogin();
                e.Handled = true;
            }
        }

        private void SubmitStudentLogin()
        {
            var lrn = (StudentLrnInput.Text ?? string.Empty).Trim();
            var password = StudentPasswordInput.Password ?? string.Empty;

            if (string.IsNullOrWhiteSpace(lrn) || string.IsNullOrWhiteSpace(password))
            {
                StatusText.Text = "Enter your LRN and password.";
                return;
            }

            try
            {
                var payload = "{\"lrn\":\"" + EscapeJson(lrn) + "\",\"password\":\"" + EscapeJson(password) + "\"}";
                Directory.CreateDirectory(Path.GetDirectoryName(_studentLoginRequestPath) ?? ".");
                File.WriteAllText(_studentLoginRequestPath, payload, Encoding.UTF8);
                StatusText.Text = "Signing in...";
                InfoText.Text = "Verifying your account with XPLabs.";
                StudentPasswordInput.Password = "";
                StudentSignInButton.IsEnabled = false;
            }
            catch
            {
                StatusText.Text = "Unable to submit sign-in request.";
            }
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
                InfoText.Text = "Checking admin credentials...";
                OverridePasswordInput.Password = "";
                OverridePanel.Visibility = Visibility.Collapsed;
            }
            catch
            {
                StatusText.Text = "Unable to submit override request.";
            }
        }

        private void SubmitAdminHotkeyBypass()
        {
            try
            {
                ApplyLocalUnlock(
                    "Admin hotkey unlock (Ctrl+Shift+X).",
                    DateTime.Now.AddMinutes(30).ToString("s"));

                var payload = "{\"source\":\"lockscreen_hotkey\",\"requested_at\":\"" + DateTime.UtcNow.ToString("o") + "\"}";
                Directory.CreateDirectory(Path.GetDirectoryName(_adminHotkeyRequestPath) ?? ".");
                File.WriteAllText(_adminHotkeyRequestPath, payload, Encoding.UTF8);

                TryStartWidget();
                StatusText.Text = "Unlocked.";
                InfoText.Text = "Ctrl+Shift+X — desktop unlocked.";
                RefreshState();
            }
            catch
            {
                StatusText.Text = "Unable to submit admin bypass.";
            }
        }

        private void ApplyLocalUnlock(string message, string overrideUntil)
        {
            var dir = Path.GetDirectoryName(_statePath) ?? ".";
            Directory.CreateDirectory(dir);

            var json = File.Exists(_statePath) ? File.ReadAllText(_statePath, Encoding.UTF8) : "{}";
            json = JsonTiny.SetBool(json, "locked", false);
            json = JsonTiny.SetString(json, "last_unlock_at", DateTime.Now.ToString("s"));
            json = JsonTiny.SetString(json, "override_unlock_until", overrideUntil);
            json = JsonTiny.SetString(json, "last_override_status", "success");
            json = JsonTiny.SetString(json, "last_override_message", message);

            var tmp = _statePath + ".tmp";
            File.WriteAllText(tmp, json, Encoding.UTF8);
            if (File.Exists(_statePath))
            {
                File.Delete(_statePath);
            }
            File.Move(tmp, _statePath);
        }

        private static void TryStartWidget()
        {
            try
            {
                if (!File.Exists(WidgetExePath)) return;
                Process.Start(new ProcessStartInfo(WidgetExePath) { UseShellExecute = true });
            }
            catch { /* agent may start widget later */ }
        }

        private static string EscapeJson(string value)
        {
            return value.Replace("\\", "\\\\").Replace("\"", "\\\"");
        }
    }
}

