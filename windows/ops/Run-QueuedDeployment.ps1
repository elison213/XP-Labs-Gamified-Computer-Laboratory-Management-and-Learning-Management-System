param(
  [Parameter(Mandatory)] [string] $TargetHost,
  [Parameter(Mandatory)] [string] $ServerBaseUrl,
  [string] $WindowsSourceShare = "",
  [int] $FloorId = 0,
  [int] $StationId = 0
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function New-Result([bool]$Success, [string]$Message, [hashtable]$Data = @{}) {
  return @{
    success = $Success
    message = $Message
    data = $Data
    timestamp = (Get-Date).ToString('s')
  }
}

try {
  if ([string]::IsNullOrWhiteSpace($WindowsSourceShare)) {
    throw "WindowsSourceShare is required for remote installation."
  }
  if (-not (Test-Path $WindowsSourceShare)) {
    throw "WindowsSourceShare not found: $WindowsSourceShare"
  }

  $installer = Join-Path $WindowsSourceShare 'agent\Install-Agent.ps1'
  if (-not (Test-Path $installer)) {
    throw "Install-Agent.ps1 not found in share: $installer"
  }

  $cfgScript = @'
param($baseUrl, $floorId, $stationId)
$configPath = Join-Path $env:ProgramData 'XPLabsAgent\agent.config.json'
if (-not (Test-Path $configPath)) { throw "agent.config.json missing at $configPath" }
$cfg = Get-Content -Raw -Path $configPath | ConvertFrom-Json
$cfg.server_base_url = $baseUrl.TrimEnd('/')
if ($floorId -gt 0) { $cfg.floor_id = $floorId } else { $cfg.floor_id = $null }
if ($stationId -gt 0) { $cfg.station_id = $stationId } else { $cfg.station_id = $null }
$cfg | ConvertTo-Json -Depth 10 | Set-Content -Path $configPath -Encoding UTF8
Start-ScheduledTask -TaskName 'XPLabsAgentLoop' -ErrorAction SilentlyContinue
'@

  Invoke-Command -ComputerName $TargetHost -ScriptBlock {
    param($srcShare, $cfgCmd, $baseUrl, $floorId, $stationId)
    & (Join-Path $srcShare 'agent\Install-Agent.ps1') -SourceDir $srcShare
    $sb = [scriptblock]::Create($cfgCmd)
    & $sb -baseUrl $baseUrl -floorId $floorId -stationId $stationId
  } -ArgumentList $WindowsSourceShare, $cfgScript, $ServerBaseUrl, $FloorId, $StationId -ErrorAction Stop | Out-Null

  $result = New-Result -Success $true -Message "Deployment completed for $TargetHost" -Data @{
    target_host = $TargetHost
    server_base_url = $ServerBaseUrl
    floor_id = $FloorId
    station_id = $StationId
  }
  $result | ConvertTo-Json -Depth 10 -Compress
  exit 0
} catch {
  $err = New-Result -Success $false -Message $_.Exception.Message -Data @{
    target_host = $TargetHost
    server_base_url = $ServerBaseUrl
  }
  $err | ConvertTo-Json -Depth 10 -Compress
  exit 1
}
