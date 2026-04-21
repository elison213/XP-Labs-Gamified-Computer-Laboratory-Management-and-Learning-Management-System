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
    LogPath    = Join-Path $logDir 'agent.log'
  }
}

function Initialize-XplabsAgentDirs {
  $p = Get-XplabsPaths
  foreach ($dir in @($p.ProgramDir, $p.DataDir, $p.LogDir)) {
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
  }
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
  $uri = "$base$Path"

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
  return Invoke-RestMethod @params
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

Export-ModuleMember -Function *-Xplabs*, Get-HostIdentity, Get-SystemInfo, Get-AgentState, Set-AgentState

