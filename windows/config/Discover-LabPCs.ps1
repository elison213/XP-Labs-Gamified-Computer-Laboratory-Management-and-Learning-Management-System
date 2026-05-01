param(
  [string] $ServerBaseUrl = "http://local.xplabs.com/xplabs",
  [string] $SyncEndpoint = "/api/pc/discovery-sync.php",
  [string] $CsrfToken = "",
  [string] $SessionCookie = "",
  [switch] $PreviewOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-DhcpDiscoveredHosts {
  $items = @()
  $cmd = Get-Command Get-DhcpServerv4Lease -ErrorAction SilentlyContinue
  if (-not $cmd) { return $items }
  try {
    $scopes = Get-DhcpServerv4Scope -ErrorAction Stop
    foreach ($scope in $scopes) {
      $leases = Get-DhcpServerv4Lease -ScopeId $scope.ScopeId -ErrorAction SilentlyContinue
      foreach ($lease in $leases) {
        $items += [pscustomobject]@{
          hostname = [string]$lease.HostName
          ip_address = [string]$lease.IPAddress
          mac_address = ([string]$lease.ClientId).Replace('-', ':')
          source = 'dhcp'
        }
      }
    }
  } catch {}
  return $items
}

function Get-ArpDiscoveredHosts {
  $items = @()
  try {
    $lines = arp -a
    foreach ($line in $lines) {
      if ($line -match '^\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\s+([0-9a-f\-]{17})\s+dynamic') {
        $ip = $matches[1]
        $mac = $matches[2].ToLower().Replace('-', ':')
        $items += [pscustomobject]@{
          hostname = ""
          ip_address = $ip
          mac_address = $mac
          source = 'arp'
        }
      }
    }
  } catch {}
  return $items
}

function Merge-Hosts([array]$Hosts) {
  $map = @{}
  foreach ($h in $Hosts) {
    $key = ""
    if ($h.mac_address) { $key = "mac:$($h.mac_address)" }
    elseif ($h.hostname) { $key = "host:$($h.hostname)" }
    else { $key = "ip:$($h.ip_address)" }
    if (-not $map.ContainsKey($key)) {
      $map[$key] = [pscustomobject]@{
        hostname = $h.hostname
        ip_address = $h.ip_address
        mac_address = $h.mac_address
      }
    } else {
      $existing = $map[$key]
      if (-not $existing.hostname -and $h.hostname) { $existing.hostname = $h.hostname }
      if (-not $existing.ip_address -and $h.ip_address) { $existing.ip_address = $h.ip_address }
      if (-not $existing.mac_address -and $h.mac_address) { $existing.mac_address = $h.mac_address }
    }
  }
  return @($map.Values)
}

$dhcpHosts = Get-DhcpDiscoveredHosts
$arpHosts = Get-ArpDiscoveredHosts
$hosts = Merge-Hosts -Hosts @($dhcpHosts + $arpHosts)

Write-Host "Discovered hosts: $($hosts.Count)" -ForegroundColor Cyan
if ($PreviewOnly -or [string]::IsNullOrWhiteSpace($CsrfToken) -or [string]::IsNullOrWhiteSpace($SessionCookie)) {
  $out = Join-Path $env:TEMP "xplabs_discovered_hosts.json"
  @{ hosts = $hosts } | ConvertTo-Json -Depth 8 | Out-File -FilePath $out -Encoding UTF8
  Write-Host "Preview mode (or missing auth). Host payload saved to: $out" -ForegroundColor Yellow
  Write-Host "To sync, rerun with -CsrfToken and -SessionCookie from an authenticated admin session." -ForegroundColor Yellow
  return
}

$uri = $ServerBaseUrl.TrimEnd('/') + $SyncEndpoint
$headers = @{
  'Content-Type' = 'application/json'
  'X-CSRF-Token' = $CsrfToken
  'Cookie' = $SessionCookie
}
$body = @{ hosts = $hosts } | ConvertTo-Json -Depth 8

$res = Invoke-RestMethod -Method POST -Uri $uri -Headers $headers -Body $body
Write-Host "Discovery sync complete: created=$($res.created) updated=$($res.updated) failed=$($res.failed)" -ForegroundColor Green
