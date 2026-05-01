param(
  [string] $ProjectPath = "",
  [string] $OutDir = "",
  [string] $Version = "",
  [string] $LockscreenExePath = "",
  [string] $WidgetExePath = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Resolve-ProjectPath([string]$InputPath) {
  if ($InputPath -and $InputPath.Trim().Length -gt 0) { return $InputPath }
  $scriptRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
  return Split-Path -Parent (Split-Path -Parent $scriptRoot)
}

function Ensure-Dir([string]$Path) {
  if (-not (Test-Path $Path)) { New-Item -ItemType Directory -Path $Path -Force | Out-Null }
}

function Safe-RemoveDir([string]$Path) {
  if ($Path -and (Test-Path $Path)) {
    try { Remove-Item -Path $Path -Recurse -Force -ErrorAction Stop } catch {}
  }
}

function Copy-Tree([string]$Source, [string]$Dest) {
  if (-not (Test-Path $Source)) { throw "Source not found: $Source" }
  Ensure-Dir $Dest
  Copy-Item -Path (Join-Path $Source '*') -Destination $Dest -Recurse -Force
}

$ProjectPath = Resolve-ProjectPath -InputPath $ProjectPath
if (-not (Test-Path $ProjectPath)) { throw "ProjectPath not found: $ProjectPath" }

if (-not $Version -or $Version.Trim().Length -eq 0) {
  $Version = (Get-Date).ToString('yyyyMMdd-HHmm')
}

if (-not $OutDir -or $OutDir.Trim().Length -eq 0) {
  $OutDir = Join-Path $ProjectPath 'dist\client-agent'
}
Ensure-Dir $OutDir

$windowsDir = Join-Path $ProjectPath 'windows'
if (-not (Test-Path $windowsDir)) { throw "windows/ directory not found under ProjectPath: $windowsDir" }

$stageRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("xplabs-client-agent-installer-" + $Version)
Safe-RemoveDir $stageRoot
Ensure-Dir $stageRoot

try {
  $stageWindows = Join-Path $stageRoot 'windows'
  Ensure-Dir $stageWindows

  foreach ($sub in @('agent','config','gpo','ops','widget')) {
    $src = Join-Path $windowsDir $sub
    $dst = Join-Path $stageWindows $sub
    if (Test-Path $src) {
      Copy-Tree -Source $src -Dest $dst
    }
  }

  foreach ($doc in @('README.md','VALIDATION.md')) {
    $srcDoc = Join-Path $windowsDir $doc
    if (Test-Path $srcDoc) {
      Copy-Item -Path $srcDoc -Destination (Join-Path $stageWindows $doc) -Force
    }
  }

  # Include lockscreen README; include built EXE if provided.
  $lockReadme = Join-Path $windowsDir 'lockscreen\README.md'
  if (Test-Path $lockReadme) {
    Ensure-Dir (Join-Path $stageWindows 'lockscreen')
    Copy-Item -Path $lockReadme -Destination (Join-Path $stageWindows 'lockscreen\README.md') -Force
  }
  if ($LockscreenExePath -and $LockscreenExePath.Trim().Length -gt 0) {
    if (-not (Test-Path $LockscreenExePath)) { throw "LockscreenExePath not found: $LockscreenExePath" }
    $lockDstDir = Join-Path $stageWindows 'lockscreen'
    Ensure-Dir $lockDstDir
    Copy-Item -Path $LockscreenExePath -Destination (Join-Path $lockDstDir 'XPLabs.LockScreen.exe') -Force
  }

  if ($WidgetExePath -and $WidgetExePath.Trim().Length -gt 0) {
    if (-not (Test-Path $WidgetExePath)) { throw "WidgetExePath not found: $WidgetExePath" }
    $widgetDstDir = Join-Path $stageWindows 'widget'
    Ensure-Dir $widgetDstDir
    Copy-Item -Path $WidgetExePath -Destination (Join-Path $widgetDstDir 'XPLabs.Widget.exe') -Force
  }

  # Root entrypoint that runs on a client PC.
  $entry = @'
param(
  [string] $ServerBaseUrl = "http://local.xplabs.com/xplabs",
  [int] $FloorId = 0,
  [int] $StationId = 0,
  [switch] $ConfigureNetwork,
  [string] $ServerDnsName = "local.xplabs.com",
  [string] $ServerIp = "",
  [string] $DnsServerIp = "",
  [switch] $SetStaticClientIp,
  [string] $ClientIp = "",
  [int] $PrefixLength = 24,
  [string] $DefaultGateway = "",
  [switch] $SkipInstallAgent,
  [switch] $StartAgentNow
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Assert-Admin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $p = New-Object Security.Principal.WindowsPrincipal($id)
  if (-not $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this installer in an elevated PowerShell window."
  }
}

function Resolve-InstallerRoot {
  if ($PSScriptRoot -and $PSScriptRoot.Trim().Length -gt 0) { return $PSScriptRoot }
  try {
    if ($MyInvocation -and $MyInvocation.MyCommand -and $MyInvocation.MyCommand.Path) {
      return (Split-Path -Parent $MyInvocation.MyCommand.Path)
    }
  } catch {}
  throw "Unable to determine installer folder. Run this as a script file: .\Install-XPLabsClient.ps1"
}

Assert-Admin

$projectPath = Resolve-InstallerRoot
$deploy = Join-Path $projectPath 'windows\config\Deploy-ClientPowerShellApp.ps1'
if (-not (Test-Path $deploy)) { throw "Missing deploy script: $deploy" }

$lockExe = Join-Path $projectPath 'windows\lockscreen\XPLabs.LockScreen.exe'
$lockArg = ""
if (Test-Path $lockExe) { $lockArg = $lockExe }
$widgetExe = Join-Path $projectPath 'windows\widget\XPLabs.Widget.exe'
$widgetArg = ""
if (Test-Path $widgetExe) { $widgetArg = $widgetExe }

$args = @{
  ProjectPath = $projectPath
  WindowsSourceDir = (Join-Path $projectPath 'windows')
  ServerBaseUrl = $ServerBaseUrl
  FloorId = $FloorId
  StationId = $StationId
}
if ($ConfigureNetwork) {
  $args.ConfigureNetwork = $true
  $args.ServerDnsName = $ServerDnsName
  $args.ServerIp = $ServerIp
  $args.DnsServerIp = $DnsServerIp
}
if ($SetStaticClientIp) {
  $args.SetStaticClientIp = $true
  $args.ClientIp = $ClientIp
  $args.PrefixLength = $PrefixLength
  $args.DefaultGateway = $DefaultGateway
}
if ($SkipInstallAgent) { $args.SkipInstallAgent = $true }
if ($StartAgentNow) { $args.StartAgentNow = $true }
if ($lockArg -and $lockArg.Trim().Length -gt 0) { $args.LockscreenExePath = $lockArg }
if ($widgetArg -and $widgetArg.Trim().Length -gt 0) { $args.WidgetExePath = $widgetArg }

& $deploy @args
'@

Set-Content -Path (Join-Path $stageRoot 'Install-XPLabsClient.ps1') -Value $entry -Encoding UTF8

  $zipName = "XPLabsClientAgent-$Version.zip"
  $zipPath = Join-Path $OutDir $zipName
  if (Test-Path $zipPath) { Remove-Item -Path $zipPath -Force -ErrorAction SilentlyContinue }

  Compress-Archive -Path (Join-Path $stageRoot '*') -DestinationPath $zipPath -CompressionLevel Optimal -Force
  Write-Host "Built installer artifact: $zipPath" -ForegroundColor Green
} finally {
  Safe-RemoveDir $stageRoot
}

