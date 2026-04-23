param(
  [string] $SiteHost = "lab.local.xplabs.com",
  [string] $XamppPath = "C:\xampp",
  [string] $ProjectPath = "C:\xampp\htdocs\xplabs",
  [string] $DnsZone = "local.xplabs.com",
  [switch] $EnsureIisReverseProxy,
  [switch] $RunMigrations
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

function Ensure-Dir([string]$Path) {
  if (-not (Test-Path $Path)) { New-Item -Path $Path -ItemType Directory -Force | Out-Null }
}

function Ensure-FirewallRule([string]$Name, [string]$DisplayName, [string]$Protocol, [string]$LocalPort) {
  if (Get-NetFirewallRule -DisplayName $DisplayName -ErrorAction SilentlyContinue) { return }
  New-NetFirewallRule -Name $Name -DisplayName $DisplayName -Enabled True -Direction Inbound -Action Allow -Protocol $Protocol -LocalPort $LocalPort | Out-Null
}

function Ensure-ServiceRunning([string]$ServiceName) {
  $svc = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
  if (-not $svc) {
    Write-Host "Service '$ServiceName' not found. Skipping." -ForegroundColor Yellow
    return
  }
  if ($svc.StartType -ne 'Automatic') { Set-Service -Name $ServiceName -StartupType Automatic }
  if ($svc.Status -ne 'Running') { Start-Service -Name $ServiceName }
}

function Ensure-DnsRecord([string]$Zone, [string]$HostName) {
  $ip = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object { $_.IPAddress -notlike '169.254.*' -and $_.IPAddress -ne '127.0.0.1' } |
    Select-Object -First 1 -ExpandProperty IPAddress)
  if (-not $ip) { throw "Cannot determine server IPv4 address." }

  try { Import-Module DNSServer -ErrorAction Stop } catch {
    Write-Host "DNS module unavailable. Skipping DNS record step." -ForegroundColor Yellow
    return
  }

  $zoneObj = Get-DnsServerZone -Name $Zone -ErrorAction SilentlyContinue
  if (-not $zoneObj) {
    Write-Host "DNS zone '$Zone' does not exist on this server. Skipping DNS record step." -ForegroundColor Yellow
    return
  }

  $existing = Get-DnsServerResourceRecord -ZoneName $Zone -Name $HostName -RRType "A" -ErrorAction SilentlyContinue
  if (-not $existing) {
    Add-DnsServerResourceRecordA -ZoneName $Zone -Name $HostName -IPv4Address $ip | Out-Null
    Write-Host "Added DNS: $HostName.$Zone -> $ip"
  } else {
    Write-Host "DNS record already exists: $HostName.$Zone"
  }
}

function Ensure-IisReverseProxy([string]$HostHeader) {
  Import-Module WebAdministration -ErrorAction Stop

  if (-not (Get-WindowsFeature -Name Web-Server).Installed) {
    Install-WindowsFeature -Name Web-Server -IncludeManagementTools | Out-Null
  }

  if (-not (Get-Website -Name "XPLabs-Proxy" -ErrorAction SilentlyContinue)) {
    New-Website -Name "XPLabs-Proxy" -PhysicalPath "$env:SystemDrive\inetpub\wwwroot" -Port 80 -HostHeader $HostHeader | Out-Null
  }

  # URL Rewrite + ARR are not native features, so we print guidance if missing.
  $rewriteRulePath = "IIS:\Sites\XPLabs-Proxy"
  Write-Host "IIS site 'XPLabs-Proxy' ensured."
  Write-Host "Install IIS URL Rewrite + ARR manually, then create reverse-proxy to http://127.0.0.1/xplabs/ if needed." -ForegroundColor Yellow
}

Assert-Admin

Write-Host "== Integrating XPLabs website on this Windows Server ==" -ForegroundColor Cyan
Write-Host "Host: $SiteHost"
Write-Host "Project path: $ProjectPath"
Write-Host "XAMPP path: $XamppPath"

if (-not (Test-Path $ProjectPath)) { throw "Project path not found: $ProjectPath" }
if (-not (Test-Path $XamppPath)) { throw "XAMPP path not found: $XamppPath" }

Ensure-Dir (Join-Path $ProjectPath "storage\logs")

# Open web ports
Ensure-FirewallRule -Name "XPLabs-HTTP" -DisplayName "XPLabs HTTP (TCP 80)" -Protocol "TCP" -LocalPort "80"
Ensure-FirewallRule -Name "XPLabs-HTTPS" -DisplayName "XPLabs HTTPS (TCP 443)" -Protocol "TCP" -LocalPort "443"

# Ensure XAMPP services
Ensure-ServiceRunning -ServiceName "Apache2.4"
Ensure-ServiceRunning -ServiceName "mysql"

# Ensure DNS A record host part
$parts = $SiteHost.Split('.', 2)
if ($parts.Count -eq 2) {
  $hostPart = $parts[0]
  Ensure-DnsRecord -Zone $DnsZone -HostName $hostPart
} else {
  Write-Host "Could not parse host '$SiteHost' for DNS host/zone split; skipping DNS record creation." -ForegroundColor Yellow
}

if ($EnsureIisReverseProxy) {
  Ensure-IisReverseProxy -HostHeader $SiteHost
}

if ($RunMigrations) {
  $migrateScript = Join-Path $ProjectPath "database\migrate.php"
  if (Test-Path $migrateScript) {
    Write-Host "Attempting migrations: php $migrateScript" -ForegroundColor Cyan
    try {
      & php $migrateScript
    } catch {
      Write-Host "Could not execute php migrate script automatically. Run it manually." -ForegroundColor Yellow
    }
  } else {
    Write-Host "Migration script not found at $migrateScript" -ForegroundColor Yellow
  }
}

Write-Host ""
Write-Host "Integration complete. Validate from a client:" -ForegroundColor Green
Write-Host " - nslookup $SiteHost"
Write-Host " - http://$SiteHost/xplabs/"

