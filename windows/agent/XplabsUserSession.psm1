# User-session UI bridge (MeshCentral sessionDispatch pattern for XPLabs).
# SYSTEM agent must not Start-Process desktop UI; launch in the logged-on user session.
Set-StrictMode -Version Latest

function Get-XplabsUserSessionAgentDir {
  return Join-Path $env:ProgramFiles 'XPLabsAgent'
}

function Get-XplabsUserSessionLogFile {
  return Join-Path $env:ProgramData 'XPLabsAgent\logs\user-session-ui.log'
}

function Write-XplabsUserSessionLog {
  param([string] $Message)
  try {
    $logFile = Get-XplabsUserSessionLogFile
    $dir = Split-Path -Parent $logFile
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    $line = '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Add-Content -LiteralPath $logFile -Value $line -Encoding UTF8
  } catch {}
  if (Get-Command Write-XplabsLog -ErrorAction SilentlyContinue) {
    Write-XplabsLog -Level info -Message "[UserSession] $Message"
  }
}

function Get-XplabsInteractiveUserName {
  try {
    $user = [string](Get-CimInstance Win32_ComputerSystem -ErrorAction Stop).UserName
    if ([string]::IsNullOrWhiteSpace($user)) { return $null }
    return $user.Trim()
  } catch {
    return $null
  }
}

function Get-XplabsExplorerSessionId {
  $explorer = Get-Process -Name 'explorer' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 } | Select-Object -First 1
  if ($explorer) { return [int]$explorer.SessionId }
  return 0
}

function Test-XplabsScheduledTaskExists {
  param([Parameter(Mandatory)] [string] $TaskName)
  try {
    if (Get-ScheduledTask -TaskName $TaskName -ErrorAction Stop) { return $true }
  } catch {}
  $prev = $ErrorActionPreference
  $ErrorActionPreference = 'Continue'
  & schtasks.exe /Query /TN $TaskName /FO LIST 1>$null 2>$null
  $ok = ($LASTEXITCODE -eq 0)
  $ErrorActionPreference = $prev
  return $ok
}

function Stop-XplabsSessionZeroLockscreen {
  Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -eq 0 } | ForEach-Object {
    Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
  }
}

function Stop-XplabsSessionZeroWidget {
  Get-Process -Name 'XPLabs.Widget' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -eq 0 } | ForEach-Object {
    Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
  }
}

function Stop-XplabsSessionZeroUi {
  Stop-XplabsSessionZeroLockscreen
  Stop-XplabsSessionZeroWidget
}

function Stop-XplabsUserLockscreen {
  Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 } | ForEach-Object {
    Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
  }
}

function Test-XplabsLockscreenVisibleInUserSession {
  return [bool](Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 })
}

function Test-XplabsWidgetVisibleInUserSession {
  return [bool](Get-Process -Name 'XPLabs.Widget' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -gt 0 })
}

function Invoke-XplabsRunInInteractiveUserTask {
  param(
    [Parameter(Mandatory)] [string] $TaskName,
    [Parameter(Mandatory)] [string] $ExecutePath,
    [string] $Argument = '',
    [switch] $UseCmdExe
  )

  if ($ExecutePath -ne 'cmd.exe' -and -not (Test-Path -LiteralPath $ExecutePath)) { return $false }

  $user = Get-XplabsInteractiveUserName
  if (-not $user) {
    Write-XplabsUserSessionLog 'skipped: no interactive user'
    return $false
  }

  if ($UseCmdExe) {
    $exec = 'cmd.exe'
    $taskArg = if ([string]::IsNullOrWhiteSpace($Argument)) {
      '/c "{0}"' -f $ExecutePath.Trim().Trim('"')
    } else {
      $Argument
    }
  } else {
    $exec = $ExecutePath
    $taskArg = $Argument
  }

  try {
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue | Out-Null
  } catch {}

  try {
    $action = New-ScheduledTaskAction -Execute $exec -Argument $taskArg
    $principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Highest
    $settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable
    Register-ScheduledTask -TaskName $TaskName -Action $action -Principal $principal -Settings $settings -Force | Out-Null
    Start-ScheduledTask -TaskName $TaskName -ErrorAction Stop | Out-Null
    return $true
  } catch {
    Write-XplabsUserSessionLog "task $TaskName failed: $($_.Exception.Message)"
    return $false
  }
}

function Invoke-XplabsLockscreenInUserSession {
  param([string] $AgentDir = '')

  if ([string]::IsNullOrWhiteSpace($AgentDir)) {
    $AgentDir = Get-XplabsUserSessionAgentDir
  }

  $setLocked = Join-Path $AgentDir 'Set-LockedAtLogon.ps1'
  $exe = Join-Path $AgentDir 'LockScreen\XPLabs.LockScreen.exe'
  $showCmd = Join-Path $AgentDir 'Show-Lockscreen.cmd'

  Write-XplabsUserSessionLog 'Invoke-XplabsLockscreenInUserSession'

  if (-not (Test-Path $exe)) {
    Write-XplabsUserSessionLog "missing exe: $exe"
    return $false
  }

  if (Test-XplabsLockscreenVisibleInUserSession) {
    Write-XplabsUserSessionLog 'lockscreen already visible'
    return $true
  }

  if (Test-Path $setLocked) {
    & powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File $setLocked | Out-Null
  }

  Get-Process -Name 'XPLabs.LockScreen' -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
  Start-Sleep -Milliseconds 300
  Stop-XplabsSessionZeroUi

  $elevated = $false
  try {
    $elevated = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
      [Security.Principal.WindowsBuiltInRole]::Administrator)
  } catch {}

  $user = Get-XplabsInteractiveUserName
  if ($elevated -and $user) {
    $arg = '/c start /MAX "" "{0}"' -f $exe
    if (Invoke-XplabsRunInInteractiveUserTask -TaskName 'XPLabsLockScreenManual' -ExecutePath 'cmd.exe' -Argument $arg -UseCmdExe) {
      Start-Sleep -Seconds 2
      Stop-XplabsSessionZeroUi
      if (Test-XplabsLockscreenVisibleInUserSession) {
        Write-XplabsUserSessionLog 'lockscreen visible (elevated->user task)'
        return $true
      }
    }
  }

  try {
    Start-Process -FilePath $exe -WindowStyle Maximized -ErrorAction Stop | Out-Null
    Start-Sleep -Seconds 1
    Stop-XplabsSessionZeroUi
    if (Test-XplabsLockscreenVisibleInUserSession) {
      Write-XplabsUserSessionLog 'lockscreen visible (direct)'
      return $true
    }
  } catch {
    Write-XplabsUserSessionLog "direct start failed: $($_.Exception.Message)"
  }

  if ((Test-Path $showCmd) -and $user) {
    if (Invoke-XplabsRunInInteractiveUserTask -TaskName 'XPLabsShowLockScreenDemand' -ExecutePath 'cmd.exe' -Argument ('/c "{0}"' -f $showCmd) -UseCmdExe) {
      Start-Sleep -Seconds 2
      if (Test-XplabsLockscreenVisibleInUserSession) { return $true }
    }
  }

  Write-XplabsUserSessionLog 'lockscreen not visible after all attempts'
  return $false
}

function Invoke-XplabsWidgetInUserSession {
  param([string] $AgentDir = '')

  if ([string]::IsNullOrWhiteSpace($AgentDir)) {
    $AgentDir = Get-XplabsUserSessionAgentDir
  }

  $exe = Join-Path $AgentDir 'Widget\XPLabs.Widget.exe'
  $showCmd = Join-Path $AgentDir 'Show-Widget-Now.cmd'

  Write-XplabsUserSessionLog 'Invoke-XplabsWidgetInUserSession'

  if (-not (Test-Path $exe)) {
    Write-XplabsUserSessionLog "missing widget exe: $exe"
    return $false
  }

  Stop-XplabsSessionZeroUi
  if (Test-XplabsWidgetVisibleInUserSession) { return $true }

  Get-Process -Name 'XPLabs.Widget' -ErrorAction SilentlyContinue | Where-Object { $_.SessionId -eq 0 } | Stop-Process -Force -ErrorAction SilentlyContinue

  $fromSystem = ([string]$env:USERNAME).Equals('SYSTEM', [StringComparison]::OrdinalIgnoreCase)
  if ($fromSystem -and (Test-Path $showCmd)) {
    if (Invoke-XplabsRunInInteractiveUserTask -TaskName 'XPLabsShowWidgetDemand' -ExecutePath 'cmd.exe' -Argument ('/c "{0}"' -f $showCmd) -UseCmdExe) {
      Start-Sleep -Seconds 2
      Stop-XplabsSessionZeroUi
      if (Test-XplabsWidgetVisibleInUserSession) {
        Write-XplabsUserSessionLog 'widget visible (SYSTEM->user show cmd)'
        return $true
      }
    }
  }

  $elevated = $false
  try {
    $elevated = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
      [Security.Principal.WindowsBuiltInRole]::Administrator)
  } catch {}

  $user = Get-XplabsInteractiveUserName
  if ($elevated -and $user) {
    if (Test-Path $showCmd) {
      if (Invoke-XplabsRunInInteractiveUserTask -TaskName 'XPLabsShowWidgetDemand' -ExecutePath 'cmd.exe' -Argument ('/c "{0}"' -f $showCmd) -UseCmdExe) {
        Start-Sleep -Seconds 2
        Stop-XplabsSessionZeroUi
        if (Test-XplabsWidgetVisibleInUserSession) { return $true }
      }
    }
    $arg = '/c start "" "{0}"' -f $exe
    if (Invoke-XplabsRunInInteractiveUserTask -TaskName 'XPLabsWidgetDemand' -ExecutePath 'cmd.exe' -Argument $arg -UseCmdExe) {
      Start-Sleep -Seconds 2
      Stop-XplabsSessionZeroUi
      if (Test-XplabsWidgetVisibleInUserSession) { return $true }
    }
  }

  try {
    if (Test-Path $showCmd) {
      Start-Process -FilePath 'cmd.exe' -ArgumentList @('/c', ('"{0}"' -f $showCmd)) -WindowStyle Hidden -ErrorAction Stop | Out-Null
    } else {
      Start-Process -FilePath $exe -WindowStyle Normal -ErrorAction Stop | Out-Null
    }
    Start-Sleep -Milliseconds 800
    Stop-XplabsSessionZeroUi
    return (Test-XplabsWidgetVisibleInUserSession)
  } catch {
    Write-XplabsUserSessionLog "widget direct start failed: $($_.Exception.Message)"
    return $false
  }
}

Export-ModuleMember -Function *
