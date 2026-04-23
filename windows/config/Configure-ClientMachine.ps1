param(
  [string] $ServerDnsName = "lab.local.xplabs.com",
  [string] $ServerIp = "",
  [string] $DnsServerIp = "",
  [switch] $SetStaticClientIp,
  [string] $ClientIp = "",
  [int] $PrefixLength = 24,
  [string] $DefaultGateway = "",
  [switch] $SetKioskModeHints
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

function Get-PrimaryAdapterName {
  $up = @(Get-NetAdapter -ErrorAction SilentlyContinue | Where-Object { $_.Status -eq 'Up' })
  if ($up.Count -eq 1) { return $up[0].Name }
  if ($up.Count -gt 1) {
    Write-Host "Multiple active adapters found:" -ForegroundColor Yellow
    $up | ForEach-Object { Write-Host " - $($_.Name)" }
    return (Read-Host "Enter adapter name")
  }
  throw "No active network adapter found."
}

function Set-ClientStaticIp([string]$Alias, [string]$Ip, [int]$Prefix, [string]$Gateway) {
  if (-not $Ip) { throw "ClientIp is required when -SetStaticClientIp is used." }
  if (-not $Gateway) { throw "DefaultGateway is required when -SetStaticClientIp is used." }
  $current = @(Get-NetIPAddress -InterfaceAlias $Alias -AddressFamily IPv4 -ErrorAction SilentlyContinue)
  foreach ($item in $current) {
    if ($item.IPAddress -ne $Ip -and $item.IPAddress -notlike '169.254.*' -and $item.IPAddress -ne '127.0.0.1') {
      try { Remove-NetIPAddress -InterfaceAlias $Alias -IPAddress $item.IPAddress -Confirm:$false -ErrorAction SilentlyContinue } catch {}
    }
  }
  $exists = Get-NetIPAddress -InterfaceAlias $Alias -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -eq $Ip }
  if (-not $exists) {
    New-NetIPAddress -InterfaceAlias $Alias -IPAddress $Ip -PrefixLength $Prefix -DefaultGateway $Gateway | Out-Null
  }
}

function Set-DnsServer([string]$Alias, [string]$DnsIp) {
  if (-not $DnsIp) { return }
  Set-DnsClientServerAddress -InterfaceAlias $Alias -ServerAddresses @($DnsIp) | Out-Null
}

function Ensure-HostsFallback([string]$DnsName, [string]$Ip) {
  if (-not $Ip) { return }
  $hostsPath = "$env:windir\System32\drivers\etc\hosts"
  $content = Get-Content -Path $hostsPath -ErrorAction SilentlyContinue
  $line = "$Ip`t$DnsName"
  if ($content -notcontains $line) {
    Add-Content -Path $hostsPath -Value $line
    Write-Host "Added hosts fallback: $line"
  }
}

Assert-Admin

$adapter = Get-PrimaryAdapterName
Write-Host "Using adapter: $adapter" -ForegroundColor Cyan

if ($SetStaticClientIp) {
  Set-ClientStaticIp -Alias $adapter -Ip $ClientIp -Prefix $PrefixLength -Gateway $DefaultGateway
}

Set-DnsServer -Alias $adapter -DnsIp $DnsServerIp
Ensure-HostsFallback -DnsName $ServerDnsName -Ip $ServerIp

Write-Host ""
Write-Host "Client network configuration applied." -ForegroundColor Green
Write-Host "Validation:"
Write-Host " - nslookup $ServerDnsName"
Write-Host " - Test-NetConnection $ServerDnsName -Port 80"
Write-Host " - Open http://$ServerDnsName/xplabs/"

if ($SetKioskModeHints) {
  Write-Host ""
  Write-Host "Kiosk prep hints:" -ForegroundColor Cyan
  Write-Host " - Set kiosk device static IP and add it to config/app.php kiosk.allowed_ips on the server."
  Write-Host " - Use windows/agent/Install-Agent.ps1 for lab PC agent deployment."
}

