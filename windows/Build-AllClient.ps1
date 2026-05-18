# Build lockscreen + widget for lab PC deployment (Windows 10/11, .NET Framework 4.8 SDK).
param(
  [ValidateSet('Debug', 'Release')]
  [string] $Configuration = 'Release'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$windowsRoot = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
$lockProj = Join-Path $windowsRoot 'lockscreen\XPLabs.LockScreen\XPLabs.LockScreen.csproj'
$widgetProj = Join-Path $windowsRoot 'widget\XPLabs.Widget\XPLabs.Widget.csproj'

function Invoke-DotnetBuild([string]$ProjectPath) {
  if (-not (Get-Command dotnet -ErrorAction SilentlyContinue)) {
    throw "dotnet SDK not found. Install .NET SDK with net48 targeting pack."
  }
  Write-Host "Building $ProjectPath ($Configuration)..." -ForegroundColor Cyan
  & dotnet build $ProjectPath -c $Configuration
  if ($LASTEXITCODE -ne 0) { throw "Build failed: $ProjectPath" }
}

Invoke-DotnetBuild -ProjectPath $lockProj
Invoke-DotnetBuild -ProjectPath $widgetProj

$lockOut = Join-Path $windowsRoot "lockscreen\XPLabs.LockScreen\bin\$Configuration\net48"
$widgetOut = Join-Path $windowsRoot "widget\XPLabs.Widget\bin\$Configuration\net48"

Write-Host ""
Write-Host "Build complete." -ForegroundColor Green
Write-Host "  Lockscreen: $lockOut\XPLabs.LockScreen.exe"
Write-Host "  Widget:     $widgetOut\XPLabs.Widget.exe"
Write-Host ""
Write-Host "Deploy on client (elevated PowerShell from project root):"
Write-Host "  .\windows\config\Deploy-ClientPowerShellApp.ps1 ``"
Write-Host "    -ServerBaseUrl 'http://YOUR-SERVER/xplabs' ``"
Write-Host "    -LockscreenExePath '$lockOut' ``"
Write-Host "    -WidgetExePath '$widgetOut' ``"
Write-Host "    -StartAgentNow"
