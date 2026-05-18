# Wrapper for check-pc-commands.php using XAMPP PHP.
param(
  [Parameter(Mandatory)]
  [string] $Hostname,
  [string] $PhpExe = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'
$script = Join-Path (Split-Path -Parent $PSScriptRoot) 'tools\check-pc-commands.php'
if (-not (Test-Path $PhpExe)) {
  throw "php.exe not found at $PhpExe. Pass -PhpExe or add XAMPP php to PATH."
}
& $PhpExe $script "--hostname=$Hostname"
