Set-StrictMode -Version Latest

function Get-XplabsPaths {
  $programDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
  $dataDir = Join-Path $env:ProgramData 'XPLabsAgent'
  $logDir = Join-Path $dataDir 'logs'
  [pscustomobject]@{
    ProgramDir = $programDir
    DataDir    = $dataDir
    LogDir     = $logDir
    ConfigPath = Join-Path $dataDir 'agent.config.json'
    KeyPath    = Join-Path $dataDir 'machine_key.txt'
    StatePath  = Join-Path $dataDir 'state.json'
    OverrideRequestPath = Join-Path $dataDir 'override_request.json'
    LockRequestPath = Join-Path $dataDir 'lock_request.json'
    HeartbeatSpoolDir = Join-Path $dataDir 'heartbeat-spool'
    DebugLogPath = Join-Path $logDir 'agent-debug.log'
    LogPath    = Join-Path $logDir 'agent.log'
  }
}

function Initialize-XplabsAgentDirs {
  $p = Get-XplabsPaths
  foreach ($dir in @($p.ProgramDir, $p.DataDir, $p.LogDir, $p.HeartbeatSpoolDir)) {
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
  }
}

function Get-XplabsConfigValue {
  param(
    [Parameter(Mandatory)] [string] $Name,
    $Default = $null
  )
  try {
    $cfg = Get-XplabsConfig
    if ($cfg -and ($cfg.PSObject.Properties.Name -contains $Name)) {
      return $cfg.$Name
    }
  } catch {}
  return $Default
}

function Write-XplabsLog {
  param(
    [Parameter(Mandatory)] [string] $Message,
    [ValidateSet('debug','info','warn','error')] [string] $Level = 'info'
  )
  $p = Get-XplabsPaths
  $ts = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss.fff')
  $line = "$ts [$Level] $Message"
  try {
    Add-Content -Path $p.LogPath -Value $line -Encoding UTF8
  } catch {
    # swallow
  }
}

function Test-XplabsDebugEnabled {
  $enabled = Get-XplabsConfigValue -Name 'debug_enabled' -Default $false
  return [bool]$enabled
}

function Get-XplabsDebugLevel {
  $lvl = [string](Get-XplabsConfigValue -Name 'debug_level' -Default 'normal')
  $lvl = $lvl.Trim().ToLowerInvariant()
  if ($lvl -notin @('normal', 'verbose', 'trace')) { return 'normal' }
  return $lvl
}

function Get-XplabsProtocolVersion {
  $v = [string](Get-XplabsConfigValue -Name 'heartbeat_protocol_version' -Default 'v2')
  $v = $v.Trim().ToLowerInvariant()
  if ($v -notin @('v1', 'v2')) { return 'v2' }
  return $v
}

function Write-XplabsDebugEvent {
  param(
    [Parameter(Mandatory)] [string] $EventType,
    [ValidateSet('debug','info','warn','error')] [string] $Severity = 'info',
    [hashtable] $Data = @{},
    [ValidateSet('normal','verbose','trace')] [string] $MinLevel = 'normal'
  )
  if (-not (Test-XplabsDebugEnabled)) { return }

  $levelRank = @{ normal = 1; verbose = 2; trace = 3 }
  $current = Get-XplabsDebugLevel
  if ($levelRank[$current] -lt $levelRank[$MinLevel]) { return }

  $p = Get-XplabsPaths
  $evt = @{
    ts = (Get-Date).ToString('o')
    event_type = $EventType
    severity = $Severity
    protocol_version = Get-XplabsProtocolVersion
    data = $Data
  }
  try {
    Add-Content -Path $p.DebugLogPath -Value ($evt | ConvertTo-Json -Depth 10 -Compress) -Encoding UTF8
  } catch {}
}

function Invoke-XplabsDebugRetention {
  if (-not (Test-XplabsDebugEnabled)) { return }
  $days = 7
  try {
    $cfgVal = Get-XplabsConfigValue -Name 'debug_retention_days' -Default 7
    $days = [int]$cfgVal
  } catch {}
  if ($days -lt 1) { $days = 1 }
  $p = Get-XplabsPaths
  if (-not (Test-Path $p.DebugLogPath)) { return }
  try {
    $cutoff = (Get-Date).AddDays(-1 * $days)
    $item = Get-Item -Path $p.DebugLogPath -ErrorAction SilentlyContinue
    if ($item -and $item.LastWriteTime -lt $cutoff) {
      Clear-Content -Path $p.DebugLogPath -ErrorAction SilentlyContinue
      Write-XplabsDebugEvent -EventType 'debug_retention_prune' -Severity 'info' -Data @{ retention_days = $days } -MinLevel 'normal'
    }
  } catch {}
}

function Read-XplabsJsonFile {
  param([Parameter(Mandatory)] [string] $Path)
  if (-not (Test-Path $Path)) { return $null }
  $raw = Get-Content -Path $Path -Raw -ErrorAction SilentlyContinue
  if (-not $raw) { return $null }
  try { return $raw | ConvertFrom-Json -ErrorAction Stop } catch { return $null }
}

function Write-XplabsJsonFile {
  param(
    [Parameter(Mandatory)] [string] $Path,
    [Parameter(Mandatory)] $Object
  )
  $json = $Object | ConvertTo-Json -Depth 10
  $tmp = "$Path.tmp"
  $json | Out-File -FilePath $tmp -Encoding UTF8 -Force
  Move-Item -Path $tmp -Destination $Path -Force
}

function Get-XplabsConfig {
  $p = Get-XplabsPaths
  $cfg = Read-XplabsJsonFile -Path $p.ConfigPath
  if (-not $cfg) {
    throw "Missing config file: $($p.ConfigPath). Copy agent.config.json.example to ProgramData and edit."
  }
  return $cfg
}

function Get-XplabsMachineKey {
  $p = Get-XplabsPaths
  if (-not (Test-Path $p.KeyPath)) { return $null }
  $key = (Get-Content -Path $p.KeyPath -ErrorAction SilentlyContinue | Select-Object -First 1)
  if ($key) { return $key.Trim() }
  return $null
}

function Set-XplabsMachineKey {
  param([Parameter(Mandatory)] [string] $MachineKey)
  $p = Get-XplabsPaths
  $MachineKey.Trim() | Out-File -FilePath $p.KeyPath -Encoding ASCII -Force
}

function Invoke-XplabsApi {
  param(
    [Parameter(Mandatory)] [string] $Method,
    [Parameter(Mandatory)] [string] $Path,
    [object] $Body = $null,
    [string] $MachineKey = $null
  )
  $cfg = Get-XplabsConfig
  $base = $cfg.server_base_url
  if ($null -eq $base) { $base = '' }
  $base = ([string]$base).TrimEnd('/')
  if (-not $base) { throw "server_base_url missing in agent config" }
  function Convert-ToPhpRoute([string]$InputPath) {
    if ([string]::IsNullOrWhiteSpace($InputPath)) { return $InputPath }
    if ($InputPath -match '\.php(?:\?|$)') { return $InputPath }
    $parts = $InputPath -split '\?', 2
    $p = $parts[0]
    $qs = if ($parts.Count -gt 1) { $parts[1] } else { $null }
    if ($p -match '^/api/.+') {
      $p = "$p.php"
    }
    if ($null -ne $qs -and $qs -ne '') {
      return "$p?$qs"
    }
    return $p
  }

  $uri = "$base$Path"
  $fallbackPath = Convert-ToPhpRoute $Path
  $fallbackUri = "$base$fallbackPath"

  $headers = @{ 'Content-Type' = 'application/json' }
  if ($MachineKey) { $headers['X-Machine-Key'] = $MachineKey }

  $params = @{
    Method      = $Method
    Uri         = $uri
    Headers     = $headers
    TimeoutSec  = 15
    ErrorAction = 'Stop'
  }
  if ($Body -ne $null) {
    $params.Body = ($Body | ConvertTo-Json -Depth 10)
  }
  try {
    return Invoke-RestMethod @params
  } catch {
    $firstError = $_.Exception.Message
    if ($fallbackPath -ne $Path) {
      try {
        $params.Uri = $fallbackUri
        Write-XplabsLog -Level warn -Message "API call fallback to .php route: path=$Path fallback=$fallbackPath reason=$firstError"
        return Invoke-RestMethod @params
      } catch {
        $secondError = $_.Exception.Message
        throw "API call failed for '$Path' and fallback '$fallbackPath': $secondError"
      }
    }
    throw "API call failed for '$Path': $firstError"
  }
}

function Get-XplabsRetryDelaySeconds {
  param(
    [int] $Attempt,
    [double] $BaseDelaySeconds = 1.5,
    [double] $MaxDelaySeconds = 45
  )
  if ($Attempt -lt 1) { $Attempt = 1 }
  $raw = $BaseDelaySeconds * [Math]::Pow(2, ($Attempt - 1))
  if ($raw -gt $MaxDelaySeconds) { $raw = $MaxDelaySeconds }
  $jitterMin = [Math]::Max(0.1, $raw * 0.5)
  $jitterMax = [Math]::Max($jitterMin, $raw * 1.25)
  return Get-Random -Minimum $jitterMin -Maximum $jitterMax
}

function Test-XplabsRetryableError {
  param([string] $Message)
  if ([string]::IsNullOrWhiteSpace($Message)) { return $false }
  $m = $Message.ToLowerInvariant()
  $needles = @(
    'timed out',
    'timeout',
    'temporarily unavailable',
    'name could not be resolved',
    'no such host',
    'connection was closed',
    'unable to connect',
    '502',
    '503',
    '504',
    '429'
  )
  foreach ($n in $needles) {
    if ($m.Contains($n)) { return $true }
  }
  return $false
}

function Invoke-XplabsApiWithRetry {
  param(
    [Parameter(Mandatory)] [string] $Method,
    [Parameter(Mandatory)] [string] $Path,
    [object] $Body = $null,
    [string] $MachineKey = $null,
    [int] $MaxAttempts = 4
  )
  if ($MaxAttempts -lt 1) { $MaxAttempts = 1 }
  $last = $null
  for ($attempt = 1; $attempt -le $MaxAttempts; $attempt++) {
    try {
      $res = Invoke-XplabsApi -Method $Method -Path $Path -Body $Body -MachineKey $MachineKey
      if ($attempt -gt 1) {
        Write-XplabsLog -Level info -Message "checkin_state=recovered path=$Path attempts=$attempt"
      }
      return $res
    } catch {
      $last = $_
      $msg = $_.Exception.Message
      $retryable = Test-XplabsRetryableError -Message $msg
      if (-not $retryable -or $attempt -ge $MaxAttempts) { break }
      $delay = Get-XplabsRetryDelaySeconds -Attempt $attempt
      Write-XplabsLog -Level warn -Message "checkin_state=backoff path=$Path attempt=$attempt delay_sec=$([math]::Round($delay,2)) error=$msg"
      Start-Sleep -Milliseconds ([int]([Math]::Ceiling($delay * 1000)))
    }
  }
  throw $last
}

function Queue-XplabsHeartbeatPayload {
  param([Parameter(Mandatory)] $Payload)
  $p = Get-XplabsPaths
  if (-not (Test-Path $p.HeartbeatSpoolDir)) {
    New-Item -ItemType Directory -Path $p.HeartbeatSpoolDir -Force | Out-Null
  }
  $name = "{0:yyyyMMddHHmmssfff}-{1}.json" -f (Get-Date), ([guid]::NewGuid().ToString('N').Substring(0, 10))
  $path = Join-Path $p.HeartbeatSpoolDir $name
  Write-XplabsJsonFile -Path $path -Object $Payload
  return $path
}

function Get-XplabsQueuedHeartbeats {
  $p = Get-XplabsPaths
  if (-not (Test-Path $p.HeartbeatSpoolDir)) { return @() }
  return @(Get-ChildItem -Path $p.HeartbeatSpoolDir -Filter '*.json' -File -ErrorAction SilentlyContinue | Sort-Object Name)
}

function Read-XplabsQueuedHeartbeat {
  param([Parameter(Mandatory)] [string] $Path)
  return Read-XplabsJsonFile -Path $Path
}

function Remove-XplabsQueuedHeartbeat {
  param([Parameter(Mandatory)] [string] $Path)
  Remove-Item -Path $Path -Force -ErrorAction SilentlyContinue
}

function Get-HostIdentity {
  $hostname = $env:COMPUTERNAME
  $ip = $null
  try {
    $ip = (Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias '*' -ErrorAction Stop |
      Where-Object { $_.IPAddress -and $_.IPAddress -notlike '169.254*' -and $_.IPAddress -ne '127.0.0.1' } |
      Sort-Object -Property PrefixLength -Descending |
      Select-Object -First 1).IPAddress
  } catch {}

  $mac = $null
  try {
    $mac = (Get-NetAdapter -Physical -ErrorAction Stop |
      Where-Object { $_.MacAddress -and $_.Status -eq 'Up' } |
      Select-Object -First 1).MacAddress
  } catch {}

  [pscustomobject]@{
    hostname    = $hostname
    ip_address  = $ip
    mac_address = $mac
  }
}

function Get-SystemInfo {
  $cpu = $null
  $ram = $null
  $disk = $null
  try {
    $cpu = (Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average
  } catch {}
  try {
    $os = Get-CimInstance Win32_OperatingSystem
    $total = [math]::Round($os.TotalVisibleMemorySize / 1024, 0)
    $free = [math]::Round($os.FreePhysicalMemory / 1024, 0)
    $used = $total - $free
    $ram = @{ total_mb = $total; used_mb = $used; free_mb = $free }
  } catch {}
  try {
    $c = Get-CimInstance Win32_LogicalDisk -Filter "DeviceID='C:'"
    if ($c) {
      $totalGb = [math]::Round($c.Size / 1GB, 2)
      $freeGb  = [math]::Round($c.FreeSpace / 1GB, 2)
      $disk = @{ drive = 'C:'; total_gb = $totalGb; free_gb = $freeGb }
    }
  } catch {}

  return @{
    cpu_load_percent = $cpu
    ram             = $ram
    disk            = $disk
  }
}

function Get-AgentState {
  $p = Get-XplabsPaths
  $state = Read-XplabsJsonFile -Path $p.StatePath
  if (-not $state) {
    $state = [pscustomobject]@{
      locked = $true
      last_lrn = $null
      last_unlock_at = $null
      last_validate_at = $null
      last_server_time = $null
    }
  }
  return $state
}

function Set-AgentState {
  param([Parameter(Mandatory)] $State)
  $p = Get-XplabsPaths
  Write-XplabsJsonFile -Path $p.StatePath -Object $State
}

function Get-OverrideRequest {
  $p = Get-XplabsPaths
  return Read-XplabsJsonFile -Path $p.OverrideRequestPath
}

function Clear-OverrideRequest {
  $p = Get-XplabsPaths
  if (Test-Path $p.OverrideRequestPath) {
    Remove-Item -Path $p.OverrideRequestPath -Force -ErrorAction SilentlyContinue
  }
}

function Get-LockRequest {
  $p = Get-XplabsPaths
  return Read-XplabsJsonFile -Path $p.LockRequestPath
}

function Clear-LockRequest {
  $p = Get-XplabsPaths
  if (Test-Path $p.LockRequestPath) {
    Remove-Item -Path $p.LockRequestPath -Force -ErrorAction SilentlyContinue
  }
}

function Get-XplabsDriveMappings {
  param(
    [Parameter(Mandatory)] [string] $MachineKey,
    [string] $Role = 'student',
    [string] $Username = '',
    [string] $LabName = ''
  )
  $query = "?role=$([uri]::EscapeDataString($Role))&username=$([uri]::EscapeDataString($Username))&lab_name=$([uri]::EscapeDataString($LabName))"
  $res = Invoke-XplabsApi -Method 'GET' -Path "/api/access/drive-maps$query" -MachineKey $MachineKey
  if ($res -and ($res.PSObject.Properties.Name -contains 'mappings')) { return @($res.mappings) }
  return @()
}

function Get-XplabsFolderRules {
  param(
    [Parameter(Mandatory)] [string] $MachineKey,
    [string] $Role = 'student'
  )
  $query = "?role=$([uri]::EscapeDataString($Role))"
  $res = Invoke-XplabsApi -Method 'GET' -Path "/api/access/folder-rules$query" -MachineKey $MachineKey
  if ($res -and ($res.PSObject.Properties.Name -contains 'rules')) { return @($res.rules) }
  return @()
}

function Clear-XplabsDriveMappings {
  $letters = @('H','S','L','T')
  foreach ($letter in $letters) {
    try { & cmd.exe /c "net use ${letter}: /delete /y >nul 2>&1" | Out-Null } catch {}
  }
}

function Apply-XplabsDriveMappings {
  param(
    [Parameter(Mandatory)] [array] $Mappings
  )
  foreach ($m in $Mappings) {
    $letter = ''
    if ($null -ne $m.drive_letter) { $letter = [string]$m.drive_letter }
    $path = ''
    if ($null -ne $m.network_path) { $path = [string]$m.network_path }
    if ([string]::IsNullOrWhiteSpace($letter) -or [string]::IsNullOrWhiteSpace($path)) { continue }
    try {
      & cmd.exe /c "net use ${letter}: /delete /y >nul 2>&1" | Out-Null
      & cmd.exe /c "net use ${letter}: `"$path`" /persistent:no" | Out-Null
      Write-XplabsLog -Level info -Message "Drive mapped: ${letter}: -> $path"
    } catch {
      Write-XplabsLog -Level warn -Message "Drive mapping failed for ${letter}: $($_.Exception.Message)"
    }
  }
}

function Apply-XplabsFolderRules {
  param(
    [Parameter(Mandatory)] [array] $Rules
  )
  # Restrict ACL enforcement to managed/safe roots.
  $safeRoots = @('C:\LabData', 'C:\Users\Public', 'C:\Temp')
  foreach ($r in $Rules) {
    $path = ''
    if ($null -ne $r.path) { $path = [string]$r.path }
    $principal = ''
    if ($null -ne $r.principal) { $principal = [string]$r.principal }
    $accessType = 'read'
    if ($null -ne $r.access_type) { $accessType = [string]$r.access_type }
    if ([string]::IsNullOrWhiteSpace($path) -or [string]::IsNullOrWhiteSpace($principal)) { continue }

    $allowedPath = $false
    foreach ($root in $safeRoots) {
      if ($path.StartsWith($root, [System.StringComparison]::OrdinalIgnoreCase)) {
        $allowedPath = $true
        break
      }
    }
    if (-not $allowedPath) {
      Write-XplabsLog -Level warn -Message "Skipping ACL rule outside managed roots: path=$path principal=$principal"
      continue
    }

    if (-not (Test-Path $path)) {
      try { New-Item -ItemType Directory -Path $path -Force | Out-Null } catch {}
    }

    $perm = 'R'
    if ($accessType -eq 'modify') { $perm = 'M' }
    elseif ($accessType -eq 'full') { $perm = 'F' }

    try {
      & icacls.exe $path /grant "${principal}:($perm)" /T /C | Out-Null
      Write-XplabsLog -Level info -Message "ACL applied: path=$path principal=$principal perm=$perm"
    } catch {
      Write-XplabsLog -Level warn -Message "ACL apply failed path=$path principal=$principal: $($_.Exception.Message)"
    }
  }
}

function Invoke-XplabsAccessApply {
  param(
    [Parameter(Mandatory)] [string] $MachineKey,
    [string] $Role = 'student',
    [string] $Username = '',
    [string] $LabName = ''
  )
  try {
    $mappings = Get-XplabsDriveMappings -MachineKey $MachineKey -Role $Role -Username $Username -LabName $LabName
    Apply-XplabsDriveMappings -Mappings $mappings
  } catch {
    Write-XplabsLog -Level warn -Message "Drive mapping apply failed: $($_.Exception.Message)"
  }
  try {
    $rules = Get-XplabsFolderRules -MachineKey $MachineKey -Role $Role
    Apply-XplabsFolderRules -Rules $rules
  } catch {
    Write-XplabsLog -Level warn -Message "Folder rule apply failed: $($_.Exception.Message)"
  }
}

function Invoke-XplabsAccessCleanup {
  try {
    Clear-XplabsDriveMappings
    Write-XplabsLog -Level info -Message "Session drive mappings cleaned up"
  } catch {
    Write-XplabsLog -Level warn -Message "Drive cleanup failed: $($_.Exception.Message)"
  }
}

Export-ModuleMember -Function *-Xplabs*, Get-HostIdentity, Get-SystemInfo, Get-AgentState, Set-AgentState, Get-OverrideRequest, Clear-OverrideRequest, Get-LockRequest, Clear-LockRequest, Invoke-XplabsApiWithRetry, Queue-XplabsHeartbeatPayload, Get-XplabsQueuedHeartbeats, Read-XplabsQueuedHeartbeat, Remove-XplabsQueuedHeartbeat, Test-XplabsRetryableError, Get-XplabsRetryDelaySeconds, Get-XplabsProtocolVersion, Write-XplabsDebugEvent, Invoke-XplabsDebugRetention, Test-XplabsDebugEnabled, Get-XplabsDebugLevel

