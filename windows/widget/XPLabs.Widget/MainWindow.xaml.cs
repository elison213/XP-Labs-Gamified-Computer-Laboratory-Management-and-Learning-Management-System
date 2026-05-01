using System;
using System.IO;
using System.Linq;
using System.Net;
using System.Security.Principal;
using System.Text;
using System.Windows;
using System.Windows.Threading;
using Forms = System.Windows.Forms;

namespace XPLabs.Widget
{
    public partial class MainWindow : Window
    {
        private readonly DispatcherTimer _refreshTimer = new DispatcherTimer();
        private readonly string _dataDir = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.CommonApplicationData), "XPLabsAgent");
        private readonly Forms.NotifyIcon _trayIcon = new Forms.NotifyIcon();
        private bool _logsVisible;
        private bool _isAdmin;
        private bool _allowExit;

        public MainWindow()
        {
            InitializeComponent();
            Loaded += MainWindow_Loaded;
            Closing += MainWindow_Closing;
        }

        private string StatePath => Path.Combine(_dataDir, "state.json");
        private string LogPath => Path.Combine(_dataDir, "logs", "agent.log");
        private string ConfigPath => Path.Combine(_dataDir, "agent.config.json");
        private string KeyPath => Path.Combine(_dataDir, "machine_key.txt");
        private string LockRequestPath => Path.Combine(_dataDir, "lock_request.json");

        private void MainWindow_Loaded(object sender, RoutedEventArgs e)
        {
            PositionBottomRight();
            _isAdmin = IsCurrentUserAdmin();
            ConfigureAccess();
            ConfigureTrayIcon();

            _refreshTimer.Interval = TimeSpan.FromSeconds(10);
            _refreshTimer.Tick += (_, __) => RefreshWidget();
            _refreshTimer.Start();

            RefreshWidget();
        }

        private void MainWindow_Closing(object sender, System.ComponentModel.CancelEventArgs e)
        {
            // Keep widget available from tray unless explicit exit command is used.
            if (_trayIcon.Visible && !_allowExit)
            {
                e.Cancel = true;
                Hide();
            }
        }

        private void ConfigureAccess()
        {
            if (_isAdmin)
            {
                AdminBanner.Visibility = Visibility.Collapsed;
                ToggleLogsButton.Visibility = Visibility.Visible;
            }
            else
            {
                AdminBanner.Visibility = Visibility.Visible;
                AdminBannerText.Text = "Logs are admin-only. Run as administrator to view and export logs.";
                ToggleLogsButton.Visibility = Visibility.Collapsed;
                LogsPanel.Visibility = Visibility.Collapsed;
            }
        }

        private void ConfigureTrayIcon()
        {
            var menu = new Forms.ContextMenuStrip();
            menu.Items.Add("Show Widget", null, (_, __) => ShowFromTray());
            menu.Items.Add("Open Website", null, (_, __) => OpenWebsite());
            menu.Items.Add("Refresh", null, (_, __) => RefreshWidget());
            menu.Items.Add("Exit", null, (_, __) => ExitApplication());

            _trayIcon.Icon = System.Drawing.SystemIcons.Application;
            _trayIcon.Text = "XPLabs Agent Widget";
            _trayIcon.Visible = true;
            _trayIcon.ContextMenuStrip = menu;
            _trayIcon.DoubleClick += (_, __) => ShowFromTray();
        }

        private void PositionBottomRight()
        {
            var work = SystemParameters.WorkArea;
            Left = work.Right - Width - 16;
            Top = work.Bottom - Height - 16;
        }

        private static bool IsCurrentUserAdmin()
        {
            try
            {
                var identity = WindowsIdentity.GetCurrent();
                var principal = new WindowsPrincipal(identity);
                return principal.IsInRole(WindowsBuiltInRole.Administrator);
            }
            catch
            {
                return false;
            }
        }

        private void RefreshWidget()
        {
            var statusBuilder = new StringBuilder();
            var lockedState = "Unknown";
            var lastServerTime = "-";
            var hostname = Environment.MachineName;
            var userDisplay = "-";
            var subjectDisplay = "-";

            try
            {
                if (File.Exists(StatePath))
                {
                    var stateJson = File.ReadAllText(StatePath, Encoding.UTF8);
                    var isLocked = JsonTiny.TryGetBool(stateJson, "locked", true);
                    var lastLrn = JsonTiny.TryGetString(stateJson, "last_lrn", "");
                    var lastUnlockAt = JsonTiny.TryGetString(stateJson, "last_unlock_at", "");
                    var serverTime = JsonTiny.TryGetString(stateJson, "last_server_time", "");
                    lockedState = isLocked ? "Locked" : "Unlocked";
                    if (!string.IsNullOrWhiteSpace(serverTime))
                    {
                        lastServerTime = serverTime;
                    }

                    if (!string.IsNullOrWhiteSpace(lastLrn))
                    {
                        userDisplay = lastLrn;
                    }

                    if (!string.IsNullOrWhiteSpace(lastUnlockAt))
                    {
                        statusBuilder.Append("Last unlock: ").Append(lastUnlockAt);
                    }
                }
                else
                {
                    statusBuilder.Append("State file not found.");
                }
            }
            catch (Exception ex)
            {
                statusBuilder.Append("State read failed: ").Append(ex.Message);
            }

            TryRefreshFromApi(ref hostname, ref userDisplay, ref subjectDisplay, ref lastServerTime, statusBuilder);

            StatusText.Text = $"Agent: {lockedState}";
            PcText.Text = $"PC: {hostname}";
            UserText.Text = $"User: {userDisplay}";
            SubjectText.Text = $"Subject: {subjectDisplay}";
            HeartbeatText.Text = $"Last server time: {lastServerTime}";
            if (statusBuilder.Length > 0)
            {
                StatusText.Text += $" ({statusBuilder})";
            }

            _trayIcon.Text = $"XPLabs Agent Widget - {lockedState}";

            if (_logsVisible && _isAdmin)
            {
                LoadLogs();
            }
        }

        private void TryRefreshFromApi(ref string hostname, ref string userDisplay, ref string subjectDisplay, ref string lastServerTime, StringBuilder statusBuilder)
        {
            var baseUrl = ReadConfigValue("server_base_url");
            var machineKey = ReadFirstLine(KeyPath);
            if (string.IsNullOrWhiteSpace(baseUrl) || string.IsNullOrWhiteSpace(machineKey))
            {
                return;
            }

            try
            {
                var configJson = ApiGet(baseUrl.TrimEnd('/') + "/api/pc/config.php", machineKey);
                var apiHostname = JsonTiny.TryGetString(configJson, "hostname", "");
                var floorName = JsonTiny.TryGetString(configJson, "name", "");
                var stationCode = JsonTiny.TryGetString(configJson, "station_code", "");
                if (!string.IsNullOrWhiteSpace(apiHostname))
                {
                    hostname = apiHostname;
                }
                if (!string.IsNullOrWhiteSpace(stationCode))
                {
                    subjectDisplay = stationCode;
                }
                else if (!string.IsNullOrWhiteSpace(floorName))
                {
                    subjectDisplay = floorName;
                }

                var hbJson = ApiPost(baseUrl.TrimEnd('/') + "/api/pc/heartbeat.php", machineKey, "{\"status\":\"online\",\"active_users\":[],\"system_info\":{}}");
                var userName = JsonTiny.TryGetString(hbJson, "user_name", "");
                var lrn = JsonTiny.TryGetString(hbJson, "lrn", "");
                var courseName = JsonTiny.TryGetString(hbJson, "course_name", "");
                var courseId = JsonTiny.TryGetString(hbJson, "course_id", "");
                var serverTime = JsonTiny.TryGetString(hbJson, "server_time", "");
                if (!string.IsNullOrWhiteSpace(userName))
                {
                    userDisplay = userName;
                }
                else if (!string.IsNullOrWhiteSpace(lrn))
                {
                    userDisplay = lrn;
                }

                if (!string.IsNullOrWhiteSpace(courseName))
                {
                    subjectDisplay = courseName;
                }
                else if (!string.IsNullOrWhiteSpace(courseId))
                {
                    subjectDisplay = "Course #" + courseId;
                }

                if (!string.IsNullOrWhiteSpace(serverTime))
                {
                    lastServerTime = serverTime;
                }
            }
            catch (Exception ex)
            {
                statusBuilder.Append(statusBuilder.Length > 0 ? " | " : "");
                statusBuilder.Append("API: ").Append(ex.Message);
            }
        }

        private string ReadConfigValue(string key)
        {
            try
            {
                if (!File.Exists(ConfigPath))
                {
                    return "";
                }
                var json = File.ReadAllText(ConfigPath, Encoding.UTF8);
                return JsonTiny.TryGetString(json, key, "");
            }
            catch
            {
                return "";
            }
        }

        private static string ReadFirstLine(string path)
        {
            try
            {
                if (!File.Exists(path))
                {
                    return "";
                }
                return File.ReadLines(path).FirstOrDefault()?.Trim() ?? "";
            }
            catch
            {
                return "";
            }
        }

        private static string ApiGet(string url, string machineKey)
        {
            var req = (HttpWebRequest)WebRequest.Create(url);
            req.Method = "GET";
            req.Timeout = 10000;
            req.ReadWriteTimeout = 10000;
            req.Headers["X-Machine-Key"] = machineKey;
            using (var resp = (HttpWebResponse)req.GetResponse())
            using (var stream = resp.GetResponseStream())
            using (var reader = new StreamReader(stream ?? throw new InvalidOperationException("No response stream"), Encoding.UTF8))
            {
                return reader.ReadToEnd();
            }
        }

        private static string ApiPost(string url, string machineKey, string bodyJson)
        {
            var req = (HttpWebRequest)WebRequest.Create(url);
            req.Method = "POST";
            req.ContentType = "application/json";
            req.Timeout = 10000;
            req.ReadWriteTimeout = 10000;
            req.Headers["X-Machine-Key"] = machineKey;
            var payload = Encoding.UTF8.GetBytes(bodyJson);
            using (var reqStream = req.GetRequestStream())
            {
                reqStream.Write(payload, 0, payload.Length);
            }
            using (var resp = (HttpWebResponse)req.GetResponse())
            using (var stream = resp.GetResponseStream())
            using (var reader = new StreamReader(stream ?? throw new InvalidOperationException("No response stream"), Encoding.UTF8))
            {
                return reader.ReadToEnd();
            }
        }

        private void LoadLogs()
        {
            try
            {
                if (!File.Exists(LogPath))
                {
                    LogsTextBox.Text = "Log file not found: " + LogPath;
                    return;
                }
                var lines = File.ReadAllLines(LogPath);
                var recent = lines.Skip(Math.Max(0, lines.Length - 200));
                LogsTextBox.Text = string.Join(Environment.NewLine, recent);
                LogsTextBox.ScrollToEnd();
            }
            catch (Exception ex)
            {
                LogsTextBox.Text = "Failed to read logs: " + ex.Message;
            }
        }

        private void OpenWebsite()
        {
            var url = ReadConfigValue("server_base_url");
            if (string.IsNullOrWhiteSpace(url))
            {
                MessageBox.Show("server_base_url is not configured in agent.config.json", "XPLabs Widget", MessageBoxButton.OK, MessageBoxImage.Warning);
                return;
            }

            try
            {
                System.Diagnostics.Process.Start(url);
            }
            catch
            {
                try
                {
                    System.Diagnostics.Process.Start("explorer.exe", url);
                }
                catch (Exception ex)
                {
                    MessageBox.Show("Unable to open website: " + ex.Message, "XPLabs Widget", MessageBoxButton.OK, MessageBoxImage.Error);
                }
            }
        }

        private void ShowFromTray()
        {
            Show();
            WindowState = WindowState.Normal;
            Activate();
            Topmost = AlwaysOnTopCheck.IsChecked == true;
        }

        private void ExitApplication()
        {
            RequestReturnToLockscreen();
            _allowExit = true;
            _trayIcon.Visible = false;
            _trayIcon.Dispose();
            _refreshTimer.Stop();
            Application.Current.Shutdown();
        }

        private void RequestReturnToLockscreen()
        {
            try
            {
                Directory.CreateDirectory(_dataDir);
                var payload = "{\"reason\":\"widget_exit\",\"requested_at\":\"" + DateTime.UtcNow.ToString("o") + "\"}";
                File.WriteAllText(LockRequestPath, payload, Encoding.UTF8);
            }
            catch
            {
                // best effort only; lockscreen will still be managed by normal server flow
            }
        }

        private void OpenSiteButton_Click(object sender, RoutedEventArgs e)
        {
            OpenWebsite();
        }

        private void RefreshButton_Click(object sender, RoutedEventArgs e)
        {
            RefreshWidget();
        }

        private void ToggleLogsButton_Click(object sender, RoutedEventArgs e)
        {
            if (!_isAdmin)
            {
                MessageBox.Show("Log viewer is admin-only.", "XPLabs Widget", MessageBoxButton.OK, MessageBoxImage.Information);
                return;
            }

            _logsVisible = !_logsVisible;
            LogsPanel.Visibility = _logsVisible ? Visibility.Visible : Visibility.Collapsed;
            ToggleLogsButton.Content = _logsVisible ? "Hide Logs" : "Show Logs";
            if (_logsVisible)
            {
                LoadLogs();
            }
        }

        private void ExportLogsButton_Click(object sender, RoutedEventArgs e)
        {
            if (!_isAdmin)
            {
                return;
            }

            try
            {
                if (!File.Exists(LogPath))
                {
                    MessageBox.Show("No log file found.", "XPLabs Widget", MessageBoxButton.OK, MessageBoxImage.Warning);
                    return;
                }
                var exportPath = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.DesktopDirectory), "xplabs-agent-log-" + DateTime.Now.ToString("yyyyMMdd-HHmmss") + ".log");
                File.Copy(LogPath, exportPath, true);
                MessageBox.Show("Logs exported to " + exportPath, "XPLabs Widget", MessageBoxButton.OK, MessageBoxImage.Information);
            }
            catch (Exception ex)
            {
                MessageBox.Show("Export failed: " + ex.Message, "XPLabs Widget", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void CopyErrorsButton_Click(object sender, RoutedEventArgs e)
        {
            if (!_isAdmin)
            {
                return;
            }
            try
            {
                var errors = LogsTextBox.Text
                    .Split(new[] { "\r\n", "\n" }, StringSplitOptions.None)
                    .Where(x => x.IndexOf("[warn]", StringComparison.OrdinalIgnoreCase) >= 0 || x.IndexOf("[error]", StringComparison.OrdinalIgnoreCase) >= 0);
                Clipboard.SetText(string.Join(Environment.NewLine, errors));
            }
            catch (Exception ex)
            {
                MessageBox.Show("Copy failed: " + ex.Message, "XPLabs Widget", MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }

        private void HideButton_Click(object sender, RoutedEventArgs e)
        {
            Hide();
        }

        private void AlwaysOnTopCheck_Changed(object sender, RoutedEventArgs e)
        {
            Topmost = AlwaysOnTopCheck.IsChecked == true;
        }
    }
}

