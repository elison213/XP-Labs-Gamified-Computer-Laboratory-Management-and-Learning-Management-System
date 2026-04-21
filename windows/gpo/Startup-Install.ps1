param(
  [Parameter(Mandatory)] [string] $WindowsShareRoot
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$installer = Join-Path $WindowsShareRoot 'agent\Install-Agent.ps1'
if (-not (Test-Path $installer)) {
  throw "Installer not found at: $installer"
}

& $installer -SourceDir $WindowsShareRoot

