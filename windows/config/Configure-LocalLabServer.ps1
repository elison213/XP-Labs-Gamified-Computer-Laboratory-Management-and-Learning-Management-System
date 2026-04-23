param(
  [string] $ConfigPath = "",
  [switch] $NoPause
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
$script:LogDir = Join-Path $env:ProgramData 'XPLabsAgent\logs'
$script:LogPath = Join-Path $script:LogDir ("Configure-LocalLabServer_{0}.log" -f (Get-Date -Format 'yyyyMMdd_HHmmss'))

function Ensure-LogDir {
  if (-not (Test-Path $script:LogDir)) {
    New-Item -Path $script:LogDir -ItemType Directory -Force | Out-Null
  }
}

function Fail-And-Pause([string]$Message) {
  Write-Host ""
  Write-Host "ERROR: $Message" -ForegroundColor Red
  Write-Host "Log file: $script:LogPath" -ForegroundColor Yellow
  if (-not $NoPause) {
    Write-Host ""
    Read-Host "Press Enter to exit"
  }
}

function Assert-Admin {
  $id = [Security.Principal.WindowsIdentity]::GetCurrent()
  $p = New-Object Security.Principal.WindowsPrincipal($id)
  if (-not $p.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Run this script in an elevated PowerShell (Run as Administrator)."
  }
}

function Read-JsonFile([string]$Path) {
  if (-not (Test-Path $Path)) { throw "Config file not found: $Path" }
  # ConvertFrom-Json -Depth is not available in all Windows PowerShell builds.
  return (Get-Content -Raw -Path $Path | ConvertFrom-Json)
}

function Prompt-IfEmpty([string]$Value, [string]$Prompt, [switch]$Secret) {
  if ($Value -and $Value.Trim().Length -gt 0) { return $Value }
  if ($Secret) {
    $sec = Read-Host -Prompt $Prompt -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec)
    try { return [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
  }
  return (Read-Host -Prompt $Prompt)
}

function Ensure-Feature([string]$Name) {
  if (-not (Get-Command Get-WindowsFeature -ErrorAction SilentlyContinue)) {
    throw "Get-WindowsFeature is unavailable. Run this script in Windows PowerShell 5.1 on Windows Server."
  }
  $f = Get-WindowsFeature -Name $Name
  if (-not $f) { throw "WindowsFeature not found: $Name" }
  if (-not $f.Installed) {
    Install-WindowsFeature -Name $Name -IncludeManagementTools | Out-Null
  }
}

function Ensure-FirewallRule([string]$Name, [string]$DisplayName, [string]$Protocol, [string]$LocalPort) {
  $existing = Get-NetFirewallRule -DisplayName $DisplayName -ErrorAction SilentlyContinue
  if ($existing) { return }
  New-NetFirewallRule -Name $Name -DisplayName $DisplayName -Enabled True -Direction Inbound -Action Allow -Protocol $Protocol -LocalPort $LocalPort | Out-Null
}

function Set-StaticIp($cfgNet) {
  $alias = $cfgNet.interfaceAlias
  $ip = $cfgNet.ipAddress
  $prefix = [int]$cfgNet.prefixLength
  $gw = $cfgNet.defaultGateway
  $dns = @($cfgNet.dnsServers)

  $adapter = Get-NetAdapter -Name $alias -ErrorAction SilentlyContinue
  if (-not $adapter) {
    $up = @(Get-NetAdapter -ErrorAction SilentlyContinue | Where-Object { $_.Status -eq 'Up' })
    if ($up.Count -eq 1) {
      $alias = $up[0].Name
      Write-Host "Interface alias '$($cfgNet.interfaceAlias)' not found. Auto-selected active adapter: $alias" -ForegroundColor Yellow
    } elseif ($up.Count -gt 1) {
      Write-Host "Interface alias '$($cfgNet.interfaceAlias)' not found. Available active adapters:" -ForegroundColor Yellow
      $up | ForEach-Object { Write-Host " - $($_.Name)" }
      $alias = Read-Host "Enter adapter name to configure"
    } else {
      $all = @(Get-NetAdapter -ErrorAction SilentlyContinue)
      if ($all) {
        Write-Host "No active adapter auto-selected. Available adapters:" -ForegroundColor Yellow
        $all | ForEach-Object { Write-Host " - $($_.Name) [$($_.Status)]" }
        $alias = Read-Host "Enter adapter name to configure"
      } else {
        throw "No network adapters detected."
      }
    }
  }
  $adapter = Get-NetAdapter -Name $alias -ErrorAction Stop
  if ($adapter.Status -ne 'Up') {
    Write-Host "Warning: adapter '$alias' is not Up (status=$($adapter.Status))." -ForegroundColor Yellow
  }

  # Remove existing IPv4 addresses (best effort, avoids duplicate routes).
  # Skip APIPA and loopback addresses to prevent accidental lockout.
  Get-NetIPAddress -InterfaceAlias $alias -AddressFamily IPv4 -ErrorAction SilentlyContinue |
    Where-Object { $_.IPAddress -ne $ip -and $_.IPAddress -notlike '169.254.*' -and $_.IPAddress -ne '127.0.0.1' } |
    ForEach-Object { try { Remove-NetIPAddress -InterfaceAlias $alias -IPAddress $_.IPAddress -Confirm:$false -ErrorAction SilentlyContinue } catch {} }

  # Set address if missing
  $existingIp = Get-NetIPAddress -InterfaceAlias $alias -AddressFamily IPv4 -ErrorAction SilentlyContinue | Where-Object { $_.IPAddress -eq $ip }
  if (-not $existingIp) {
    New-NetIPAddress -InterfaceAlias $alias -IPAddress $ip -PrefixLength $prefix -DefaultGateway $gw | Out-Null
  }

  if ($dns.Count -gt 0) {
    Set-DnsClientServerAddress -InterfaceAlias $alias -ServerAddresses $dns | Out-Null
  }
}

function Ensure-ResumeTask([string]$TaskName, [string]$ScriptPath, [string]$Args) {
  $action = New-ScheduledTaskAction -Execute 'PowerShell.exe' -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$ScriptPath`" $Args"
  $trigger = New-ScheduledTaskTrigger -AtStartup
  $principal = New-ScheduledTaskPrincipal -UserId 'NT AUTHORITY\SYSTEM' -LogonType ServiceAccount -RunLevel Highest
  $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

  try { Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
  Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings | Out-Null
}

function Remove-ResumeTask([string]$TaskName) {
  try { Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue | Out-Null } catch {}
}

function Is-DomainController {
  try {
    $role = (Get-CimInstance -ClassName Win32_ComputerSystem).DomainRole
    # 4 = BDC, 5 = PDC
    return ($role -eq 4 -or $role -eq 5)
  } catch {
    return $false
  }
}

function Assert-WindowsServer {
  $os = Get-CimInstance Win32_OperatingSystem
  if (-not $os.Caption -or $os.Caption -notmatch 'Windows Server') {
    throw "This script is intended for Windows Server only. Detected: $($os.Caption)"
  }
}

function Ensure-WindowsPowerShell51 {
  if ($PSVersionTable.PSVersion.Major -ge 7) {
    throw "Run this script with Windows PowerShell 5.1 (powershell.exe), not PowerShell 7 (pwsh)."
  }
}

function Assert-RequiredCmdlets {
  foreach ($cmd in @('Get-NetAdapter', 'Set-DnsClientServerAddress', 'Get-WindowsFeature', 'Install-WindowsFeature')) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) {
      throw "Required cmdlet missing: $cmd"
    }
  }
}

function Resolve-AbsolutePathOrEmpty([string]$Path) {
  if (-not $Path) { return "" }
  try { return (Resolve-Path -Path $Path).Path } catch { return $Path }
}

function Test-PendingReboot {
  $paths = @(
    'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Component Based Servicing\RebootPending',
    'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\WindowsUpdate\Auto Update\RebootRequired'
  )
  foreach ($p in $paths) {
    if (Test-Path $p) { return $true }
  }

  try {
    $pending = (Get-ItemProperty -Path 'HKLM:\SYSTEM\CurrentControlSet\Control\Session Manager' -Name 'PendingFileRenameOperations' -ErrorAction SilentlyContinue)
    if ($pending -and $pending.PendingFileRenameOperations) { return $true }
  } catch {}

  return $false
}

function Ensure-DnsZoneAndRecord($cfgDns) {
  $zone = $cfgDns.zone
  $recordHost = $cfgDns.host
  $ip = $cfgDns.aRecordIp

  $z = Get-DnsServerZone -Name $zone -ErrorAction SilentlyContinue
  if (-not $z) {
    Add-DnsServerPrimaryZone -Name $zone -ReplicationScope 'Domain' | Out-Null
  }

  $existing = Get-DnsServerResourceRecord -ZoneName $zone -Name $recordHost -RRType 'A' -ErrorAction SilentlyContinue
  if (-not $existing) {
    Add-DnsServerResourceRecordA -ZoneName $zone -Name $recordHost -IPv4Address $ip | Out-Null
  } else {
    # Best-effort: update if different
    $currentIp = ($existing | Where-Object { $_.RecordType -eq 'A' } | Select-Object -First 1).RecordData.IPv4Address.IPAddressToString
    if ($currentIp -ne $ip) {
      Remove-DnsServerResourceRecord -ZoneName $zone -RRType 'A' -Name $recordHost -RecordData $currentIp -Force -ErrorAction SilentlyContinue | Out-Null
      Add-DnsServerResourceRecordA -ZoneName $zone -Name $recordHost -IPv4Address $ip | Out-Null
    }
  }
}

function Ensure-XamppServices($cfgXampp) {
  $xamppPath = $cfgXampp.path
  $apacheSvc = $cfgXampp.apacheServiceName
  $mysqlSvc = $cfgXampp.mysqlServiceName

  if (-not (Test-Path $xamppPath)) {
    Write-Host "XAMPP path not found: $xamppPath (skipping service checks)" -ForegroundColor Yellow
    return
  }

  foreach ($svcName in @($apacheSvc, $mysqlSvc)) {
    $svc = Get-Service -Name $svcName -ErrorAction SilentlyContinue
    if (-not $svc) {
      Write-Host "Service not found: $svcName (ensure XAMPP is installed as a service)" -ForegroundColor Yellow
      continue
    }
    if ($svc.StartType -ne 'Automatic') {
      Set-Service -Name $svcName -StartupType Automatic
    }
    if ($svc.Status -ne 'Running') {
      try { Start-Service -Name $svcName } catch { Write-Host "Failed to start service ${svcName}: $($_.Exception.Message)" -ForegroundColor Yellow }
    }
  }
}

try {
  Ensure-LogDir
  Start-Transcript -Path $script:LogPath -Append -Force | Out-Null

  Assert-Admin
  Assert-WindowsServer
  Ensure-WindowsPowerShell51
  Assert-RequiredCmdlets

  $resumeTask = 'XPLabs-LocalLabServer-Resume'
  $scriptSelf = $MyInvocation.MyCommand.Path
  $ConfigPath = Resolve-AbsolutePathOrEmpty -Path $ConfigPath

  # Load config (file or interactive)
  $cfg = $null
  if ($ConfigPath -and $ConfigPath.Trim().Length -gt 0) {
    $cfg = Read-JsonFile -Path $ConfigPath
  } else {
  # Minimal interactive config
    $cfg = [pscustomobject]@{
    domain = [pscustomobject]@{
      fqdn = ''
      netbios = ''
      safeModeAdminPassword = ''
    }
    dns = [pscustomobject]@{
      zone = ''
      host = 'lab'
      aRecordIp = ''
    }
    network = [pscustomobject]@{
      interfaceAlias = 'Ethernet'
      ipAddress = ''
      prefixLength = 24
      defaultGateway = ''
      dnsServers = @()
    }
    firewall = [pscustomobject]@{
      openDns = $true
      openHttp = $true
      openHttps = $true
    }
    xampp = [pscustomobject]@{
      path = 'C:\xampp'
      apacheServiceName = 'Apache2.4'
      mysqlServiceName = 'mysql'
    }
  }

    $cfg.domain.fqdn = Prompt-IfEmpty $cfg.domain.fqdn "AD domain FQDN (example: xplabs.com)"
    $cfg.domain.netbios = Prompt-IfEmpty $cfg.domain.netbios "AD NetBIOS name (example: XPLABS)"
    $cfg.domain.safeModeAdminPassword = Prompt-IfEmpty $cfg.domain.safeModeAdminPassword "DSRM (Safe Mode) password" -Secret

    $cfg.network.interfaceAlias = Prompt-IfEmpty $cfg.network.interfaceAlias "Network interface alias (Get-NetAdapter)" 
    $cfg.network.ipAddress = Prompt-IfEmpty $cfg.network.ipAddress "Static IP for server (example: 192.168.1.50)"
    $cfg.network.defaultGateway = Prompt-IfEmpty $cfg.network.defaultGateway "Default gateway (example: 192.168.1.1)"
    $cfg.dns.zone = Prompt-IfEmpty $cfg.dns.zone "DNS zone name (default: same as domain)" 
    if (-not $cfg.dns.zone) { $cfg.dns.zone = $cfg.domain.fqdn }
    $cfg.dns.host = Prompt-IfEmpty $cfg.dns.host "DNS host record (default: lab)"
    if (-not $cfg.dns.host) { $cfg.dns.host = 'lab' }
    $cfg.dns.aRecordIp = Prompt-IfEmpty $cfg.dns.aRecordIp "A record IPv4 (default: server static IP)"
    if (-not $cfg.dns.aRecordIp) { $cfg.dns.aRecordIp = $cfg.network.ipAddress }
    $cfg.network.dnsServers = @($cfg.network.ipAddress)
  }

  Write-Host "== XPLabs Local Lab Server configuration ==" -ForegroundColor Cyan
  Write-Host "Domain: $($cfg.domain.fqdn)  NetBIOS: $($cfg.domain.netbios)"
  Write-Host "Server IP: $($cfg.network.ipAddress)  Adapter: $($cfg.network.interfaceAlias)"
  Write-Host "DNS: $($cfg.dns.host).$($cfg.dns.zone) -> $($cfg.dns.aRecordIp)"

  # Phase 1: static IP + install roles
  Write-Host "Configuring static IP/DNS..." -ForegroundColor Cyan
  Set-StaticIp -cfgNet $cfg.network

  Write-Host "Installing AD DS + DNS roles..." -ForegroundColor Cyan
  Ensure-Feature -Name 'AD-Domain-Services'
  Ensure-Feature -Name 'DNS'

  # Feature install may require reboot before AD DS promotion.
  if (Test-PendingReboot) {
    Write-Host "Reboot required before domain promotion. Scheduling resume task and rebooting..." -ForegroundColor Yellow
    $args = ""
    if ($ConfigPath -and $ConfigPath.Trim().Length -gt 0) {
      $args = "-ConfigPath `"$ConfigPath`" -NoPause"
    } else {
      $args = "-NoPause"
    }
    Ensure-ResumeTask -TaskName $resumeTask -ScriptPath $scriptSelf -Args $args
    Restart-Computer -Force
    exit
  }

  # Phase 2: promote to DC (requires reboot)
  if (-not (Is-DomainController)) {
    Write-Host "Promoting to Domain Controller (will reboot)..." -ForegroundColor Cyan
    Import-Module ADDSDeployment

    # Ensure script resumes after reboot
    $args = ""
    if ($ConfigPath -and $ConfigPath.Trim().Length -gt 0) {
      $args = "-ConfigPath `"$ConfigPath`" -NoPause"
    } else {
      $args = "-NoPause"
    }
    Ensure-ResumeTask -TaskName $resumeTask -ScriptPath $scriptSelf -Args $args

    $sec = ConvertTo-SecureString $cfg.domain.safeModeAdminPassword -AsPlainText -Force

    Install-ADDSForest `
      -DomainName $cfg.domain.fqdn `
      -DomainNetbiosName $cfg.domain.netbios `
      -SafeModeAdministratorPassword $sec `
      -InstallDns:$true `
      -Force:$true

    # Install-ADDSForest will trigger reboot automatically.
    exit
  }

  # Phase 3: post-promotion tasks
  Write-Host "Post-promotion tasks..." -ForegroundColor Cyan
  Remove-ResumeTask -TaskName $resumeTask

  try { Import-Module DNSServer -ErrorAction Stop } catch {}

  Write-Host "Ensuring DNS zone + A record..." -ForegroundColor Cyan
  Ensure-DnsZoneAndRecord -cfgDns $cfg.dns

  Write-Host "Configuring firewall rules..." -ForegroundColor Cyan
  if ($cfg.firewall.openDns) {
    Ensure-FirewallRule -Name 'XPLabs-DNS-TCP' -DisplayName 'XPLabs DNS (TCP 53)' -Protocol 'TCP' -LocalPort '53'
    Ensure-FirewallRule -Name 'XPLabs-DNS-UDP' -DisplayName 'XPLabs DNS (UDP 53)' -Protocol 'UDP' -LocalPort '53'
  }
  if ($cfg.firewall.openHttp) { Ensure-FirewallRule -Name 'XPLabs-HTTP' -DisplayName 'XPLabs HTTP (TCP 80)' -Protocol 'TCP' -LocalPort '80' }
  if ($cfg.firewall.openHttps) { Ensure-FirewallRule -Name 'XPLabs-HTTPS' -DisplayName 'XPLabs HTTPS (TCP 443)' -Protocol 'TCP' -LocalPort '443' }

  Write-Host "Checking XAMPP services..." -ForegroundColor Cyan
  Ensure-XamppServices -cfgXampp $cfg.xampp

  Write-Host "Validation hints:" -ForegroundColor Green
  Write-Host " - Resolve-DnsName $($cfg.dns.host).$($cfg.dns.zone)"
  Write-Host " - From a client: browse http://$($cfg.dns.host).$($cfg.dns.zone)/xplabs/"

  Write-Host "Done." -ForegroundColor Green
}
catch {
  Fail-And-Pause -Message $_.Exception.Message
  exit 1
}
finally {
  try { Stop-Transcript | Out-Null } catch {}
}

