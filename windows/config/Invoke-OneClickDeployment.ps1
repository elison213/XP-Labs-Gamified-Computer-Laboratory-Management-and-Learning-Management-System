param(
  [ValidateSet('server', 'client')]
  [string] $Mode = '',
  [string] $ProjectPath = '',
  [string] $XamppPath = 'C:\xampp',
  [string] $DatabaseName = 'xplabs',
  [string] $DbUser = 'root',
  [string] $DbPassword = '',
  [string] $ServerBaseUrl = 'http://local.xplabs.com/xplabs',
  [int] $FloorId = 0,
  [int] $StationId = 0,
  [switch] $StartAgentNow,
  [string] $LockscreenExePath = '',
  [string] $WidgetExePath = '',
  [switch] $ConfigureNetwork,
  [string] $ServerDnsName = 'local.xplabs.com',
  [string] $ServerIp = '',
  [string] $DnsServerIp = '',
  [switch] $SetStaticClientIp,
  [string] $ClientIp = '',
  [int] $PrefixLength = 24,
  [string] $DefaultGateway = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Assert-Admin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $p = New-Object Security.Principal.WindowsPrincipal($id)
  if (-not $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Run this launcher in an elevated PowerShell window.'
  }
}

function Resolve-ProjectPath([string] $InputPath) {
  if ($InputPath -and $InputPath.Trim().Length -gt 0) { return $InputPath }
  $scriptRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
  return Split-Path -Parent (Split-Path -Parent $scriptRoot)
}

function Ask-ModeInteractive {
  Write-Host ''
  Write-Host 'XPLabs One-Click Deployment' -ForegroundColor Cyan
  Write-Host '  [1] Server Setup/Update (Windows Server + XAMPP)'
  Write-Host '  [2] Client Deploy (Windows 10/11 client)'
  $choice = Read-Host 'Choose mode (1 or 2)'
  if ($choice -eq '1') { return 'server' }
  if ($choice -eq '2') { return 'client' }
  throw "Invalid selection '$choice'."
}

function Run-ServerMode {
  param([string] $ResolvedProjectPath)
  $serverScript = Join-Path $ResolvedProjectPath 'windows\config\Apply-LabStabilityUpdate.ps1'
  if (-not (Test-Path $serverScript)) { throw "Missing script: $serverScript" }

  Write-Host ''
  Write-Host 'Running server setup/update...' -ForegroundColor Cyan
  & $serverScript -ProjectPath $ResolvedProjectPath -XamppPath $XamppPath -DatabaseName $DatabaseName -DbUser $DbUser -DbPassword $DbPassword

  Write-Host ''
  Write-Host 'Server smoke checks:' -ForegroundColor Cyan
  Write-Host "  php migrate check: C:\xampp\php\php.exe `"$ResolvedProjectPath\database\migrate.php`""
  Write-Host "  web check: $ServerBaseUrl"
  Write-Host '  service check: Get-Service Apache2.4,mysql,mariadb,mysql80'
}

function Run-ClientMode {
  param([string] $ResolvedProjectPath)
  $clientScript = Join-Path $ResolvedProjectPath 'windows\config\Deploy-ClientPowerShellApp.ps1'
  if (-not (Test-Path $clientScript)) { throw "Missing script: $clientScript" }

  Write-Host ''
  Write-Host 'Running client deployment...' -ForegroundColor Cyan
  $args = @{
    ProjectPath = $ResolvedProjectPath
    ServerBaseUrl = $ServerBaseUrl
    FloorId = $FloorId
    StationId = $StationId
  }
  if ($StartAgentNow) { $args.StartAgentNow = $true }
  if ($LockscreenExePath) { $args.LockscreenExePath = $LockscreenExePath }
  if ($WidgetExePath) { $args.WidgetExePath = $WidgetExePath }
  if ($ConfigureNetwork) {
    $args.ConfigureNetwork = $true
    $args.ServerDnsName = $ServerDnsName
    $args.ServerIp = $ServerIp
    $args.DnsServerIp = $DnsServerIp
    if ($SetStaticClientIp) {
      $args.SetStaticClientIp = $true
      $args.ClientIp = $ClientIp
      $args.PrefixLength = $PrefixLength
      $args.DefaultGateway = $DefaultGateway
    }
  }
  & $clientScript @args

  Write-Host ''
  Write-Host 'Client smoke checks:' -ForegroundColor Cyan
  Write-Host '  Get-ScheduledTask -TaskName XPLabsAgentLoop,XPLabsLockScreen,XPLabsWidget -ErrorAction SilentlyContinue'
  Write-Host '  powershell -NoProfile -ExecutionPolicy Bypass -File "C:\Program Files\XPLabsAgent\Run-AgentLoop.ps1" -Once'
  Write-Host '  Get-Content "$env:ProgramData\XPLabsAgent\logs\agent.log" -Tail 80'
}

Assert-Admin
$resolvedProjectPath = Resolve-ProjectPath -InputPath $ProjectPath
if (-not (Test-Path $resolvedProjectPath)) { throw "Project path not found: $resolvedProjectPath" }

if ([string]::IsNullOrWhiteSpace($Mode)) {
  $Mode = Ask-ModeInteractive
}

switch ($Mode) {
  'server' { Run-ServerMode -ResolvedProjectPath $resolvedProjectPath }
  'client' { Run-ClientMode -ResolvedProjectPath $resolvedProjectPath }
  default { throw "Unsupported mode: $Mode" }
}

Write-Host ''
Write-Host 'One-click deployment flow completed.' -ForegroundColor Green
