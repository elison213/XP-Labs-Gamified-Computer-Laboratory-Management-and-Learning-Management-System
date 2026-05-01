param(
  [string] $ProjectPath = "",
  [string] $WindowsSourceDir = "",
  [string] $ServerBaseUrl = "http://local.xplabs.com/xplabs",
  [string] $ServerDnsName = "local.xplabs.com",
  [string] $ServerIp = "",
  [string] $DnsServerIp = "",
  [int] $FloorId = 0,
  [int] $StationId = 0,
  [switch] $ConfigureNetwork,
  [switch] $SetStaticClientIp,
  [string] $ClientIp = "",
  [int] $PrefixLength = 24,
  [string] $DefaultGateway = "",
  [switch] $SkipInstallAgent,
  [switch] $StartAgentNow,
  [string] $LockscreenExePath = "",
  [string] $WidgetExePath = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Assert-Admin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $p = New-Object Security.Principal.WindowsPrincipal($id)
  if (-not $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this script in an elevated PowerShell window."
  }
}

function Resolve-ProjectPath([string]$InputPath) {
  if ($InputPath -and $InputPath.Trim().Length -gt 0) { return $InputPath }
  $scriptRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
  return Split-Path -Parent (Split-Path -Parent $scriptRoot)
}

function Ensure-ConfigFile([string]$ConfigPath, [string]$SourceExamplePath) {
  $cfgDir = Split-Path -Parent $ConfigPath
  if (-not (Test-Path $cfgDir)) { New-Item -ItemType Directory -Path $cfgDir -Force | Out-Null }
  if (-not (Test-Path $ConfigPath)) {
    if (-not (Test-Path $SourceExamplePath)) { throw "Missing config template: $SourceExamplePath" }
    Copy-Item -Path $SourceExamplePath -Destination $ConfigPath -Force
  }
}

function Update-AgentConfig(
  [string] $ConfigPath,
  [string] $BaseUrl,
  [int] $CfgFloorId,
  [int] $CfgStationId
) {
  $raw = Get-Content -Raw -Path $ConfigPath
  $obj = $raw | ConvertFrom-Json
  $obj.server_base_url = $BaseUrl.TrimEnd('/')
  if ($CfgFloorId -gt 0) { $obj.floor_id = $CfgFloorId } else { $obj.floor_id = $null }
  if ($CfgStationId -gt 0) { $obj.station_id = $CfgStationId } else { $obj.station_id = $null }
  ($obj | ConvertTo-Json -Depth 10) | Out-File -FilePath $ConfigPath -Encoding UTF8 -Force
}

Assert-Admin

$ProjectPath = Resolve-ProjectPath -InputPath $ProjectPath
if (-not (Test-Path $ProjectPath)) { throw "Project path not found: $ProjectPath" }

if (-not $WindowsSourceDir -or $WindowsSourceDir.Trim().Length -eq 0) {
  $WindowsSourceDir = Join-Path $ProjectPath 'windows'
}
if (-not (Test-Path $WindowsSourceDir)) { throw "Windows source dir not found: $WindowsSourceDir" }

$configScript = Join-Path $ProjectPath 'windows\config\Configure-ClientMachine.ps1'
$installScript = Join-Path $ProjectPath 'windows\agent\Install-Agent.ps1'
$exampleConfig = Join-Path $ProjectPath 'windows\agent\agent.config.json.example'
$agentConfigPath = Join-Path $env:ProgramData 'XPLabsAgent\agent.config.json'
$targetLockscreenDir = Join-Path $env:ProgramFiles 'XPLabsAgent\LockScreen'
$targetLockscreenExe = Join-Path $targetLockscreenDir 'XPLabs.LockScreen.exe'
$targetWidgetDir = Join-Path $env:ProgramFiles 'XPLabsAgent\Widget'
$targetWidgetExe = Join-Path $targetWidgetDir 'XPLabs.Widget.exe'

Write-Host "== XPLabs Client PowerShell App Deployment ==" -ForegroundColor Cyan
Write-Host "ProjectPath: $ProjectPath"
Write-Host "WindowsSourceDir: $WindowsSourceDir"
Write-Host "ServerBaseUrl: $ServerBaseUrl"
Write-Host "FloorId/StationId: $FloorId/$StationId"
if ($FloorId -le 0 -or $StationId -le 0) {
  Write-Host "Tip: deploying as unassigned (FloorId/StationId not set). Assign later from dashboard_lab_pcs.php." -ForegroundColor Yellow
}

if ($ConfigureNetwork) {
  if (-not (Test-Path $configScript)) { throw "Missing network config script: $configScript" }
  Write-Host "Configuring client network..." -ForegroundColor Cyan

  $networkArgs = @{
    ServerDnsName = $ServerDnsName
    ServerIp = $ServerIp
    DnsServerIp = $DnsServerIp
  }
  if ($SetStaticClientIp) {
    $networkArgs.SetStaticClientIp = $true
    $networkArgs.ClientIp = $ClientIp
    $networkArgs.PrefixLength = $PrefixLength
    $networkArgs.DefaultGateway = $DefaultGateway
  }
  & $configScript @networkArgs
}

if ($LockscreenExePath -and $LockscreenExePath.Trim().Length -gt 0) {
  if (-not (Test-Path $LockscreenExePath)) { throw "Lockscreen EXE path not found: $LockscreenExePath" }
  if (-not (Test-Path $targetLockscreenDir)) { New-Item -ItemType Directory -Path $targetLockscreenDir -Force | Out-Null }
  Copy-Item -Path $LockscreenExePath -Destination $targetLockscreenExe -Force
  Write-Host "Copied lockscreen EXE to: $targetLockscreenExe" -ForegroundColor Green
} else {
  Write-Host "Lockscreen EXE not provided. Pass -LockscreenExePath to enable lock UI deployment." -ForegroundColor Yellow
}

if ($WidgetExePath -and $WidgetExePath.Trim().Length -gt 0) {
  if (-not (Test-Path $WidgetExePath)) { throw "Widget EXE path not found: $WidgetExePath" }
  if (-not (Test-Path $targetWidgetDir)) { New-Item -ItemType Directory -Path $targetWidgetDir -Force | Out-Null }
  Copy-Item -Path $WidgetExePath -Destination $targetWidgetExe -Force
  Write-Host "Copied widget EXE to: $targetWidgetExe" -ForegroundColor Green
} else {
  Write-Host "Widget EXE not provided. Pass -WidgetExePath to enable desktop agent widget deployment." -ForegroundColor Yellow
}

if (-not $SkipInstallAgent) {
  if (-not (Test-Path $installScript)) { throw "Missing agent install script: $installScript" }
  Write-Host "Installing agent app and scheduled tasks..." -ForegroundColor Cyan
  & $installScript -SourceDir $WindowsSourceDir
} else {
  if ((Test-Path $targetLockscreenExe) -or (Test-Path $targetWidgetExe)) {
    Write-Warning "SkipInstallAgent was set. UI binaries were copied but scheduled tasks were not refreshed."
  }
}

Ensure-ConfigFile -ConfigPath $agentConfigPath -SourceExamplePath $exampleConfig
Update-AgentConfig -ConfigPath $agentConfigPath -BaseUrl $ServerBaseUrl -CfgFloorId $FloorId -CfgStationId $StationId

if ($StartAgentNow) {
  $taskNames = @('XPLabsAgentLoop', 'XPLabs-AgentLoop')
  foreach ($taskName in $taskNames) {
    try {
      $task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
      if ($task) {
        Start-ScheduledTask -TaskName $taskName
        Write-Host "Started scheduled task: $taskName" -ForegroundColor Green
      }
    } catch {}
  }
}

Write-Host ""
Write-Host "Deployment complete." -ForegroundColor Green
Write-Host "Config file: $agentConfigPath"
Write-Host "Validation:"
Write-Host " - Get-Content `"$agentConfigPath`""
Write-Host " - Get-ScheduledTask -TaskName XPLabsAgentLoop -ErrorAction SilentlyContinue"
Write-Host " - Get-ScheduledTask -TaskName XPLabsLockScreen -ErrorAction SilentlyContinue"
Write-Host " - Get-ScheduledTask -TaskName XPLabsWidget -ErrorAction SilentlyContinue"
Write-Host " - Get-Content `"$env:ProgramData\XPLabsAgent\logs\agent.log`" -Tail 80"
