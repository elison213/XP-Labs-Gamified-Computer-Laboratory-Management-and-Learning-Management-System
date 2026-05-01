param(
  [Parameter(Mandatory)] [string] $TargetHost,
  [Parameter(Mandatory)] [string] $WindowsSourceDir,
  [string] $Version = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function New-Result([bool]$Success, [string]$Message, [hashtable]$Data = @{}) {
  @{
    success = $Success
    message = $Message
    data = $Data
    timestamp = (Get-Date).ToString('s')
  }
}

try {
  if (-not (Test-Path $WindowsSourceDir)) { throw "WindowsSourceDir not found: $WindowsSourceDir" }

  $filesToUpdate = @(
    'agent\Run-AgentLoop.ps1',
    'agent\XplabsAgent.psm1',
    'agent\Install-Agent.ps1'
  )
  $lockscreenExe = Join-Path $WindowsSourceDir 'lockscreen\XPLabs.LockScreen\bin\Release\XPLabs.LockScreen.exe'
  $hasLockscreenExe = Test-Path $lockscreenExe

  $session = New-PSSession -ComputerName $TargetHost -ErrorAction Stop
  try {
    Invoke-Command -Session $session -ScriptBlock {
      $programDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
      $backupDir = Join-Path $programDir ('backup_' + (Get-Date -Format 'yyyyMMdd_HHmmss'))
      $stagingDir = Join-Path $env:TEMP ('xplabs_update_' + (Get-Date -Format 'yyyyMMdd_HHmmss'))
      New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
      New-Item -ItemType Directory -Path $stagingDir -Force | Out-Null
      @{
        ProgramDir = $programDir
        BackupDir = $backupDir
        StagingDir = $stagingDir
      }
    } | Out-Null

    foreach ($relative in $filesToUpdate) {
      $src = Join-Path $WindowsSourceDir $relative
      if (-not (Test-Path $src)) { throw "Missing update file: $src" }
      $remoteTemp = Invoke-Command -Session $session -ScriptBlock {
        param($rel)
        $stagingRoot = Join-Path $env:TEMP 'xplabs_update_active'
        if (-not (Test-Path $stagingRoot)) { New-Item -ItemType Directory -Path $stagingRoot -Force | Out-Null }
        Join-Path $stagingRoot ($rel -replace '[\\/]', '_')
      } -ArgumentList $relative
      Copy-Item -Path $src -Destination $remoteTemp -ToSession $session -Force
      Invoke-Command -Session $session -ScriptBlock {
        param($remoteFile, $rel)
        $programDir = Join-Path $env:ProgramFiles 'XPLabsAgent'
        $target = Join-Path $programDir $rel
        $targetDir = Split-Path -Parent $target
        if (-not (Test-Path $targetDir)) { New-Item -ItemType Directory -Path $targetDir -Force | Out-Null }
        if (Test-Path $target) {
          $backupDir = Join-Path $programDir ('backup_latest')
          if (-not (Test-Path $backupDir)) { New-Item -ItemType Directory -Path $backupDir -Force | Out-Null }
          Copy-Item -Path $target -Destination (Join-Path $backupDir ($rel -replace '[\\/]', '_')) -Force
        }
        Copy-Item -Path $remoteFile -Destination $target -Force
      } -ArgumentList $remoteTemp, $relative
    }

    if ($hasLockscreenExe) {
      $remoteExe = Invoke-Command -Session $session -ScriptBlock {
        $stagingRoot = Join-Path $env:TEMP 'xplabs_update_active'
        if (-not (Test-Path $stagingRoot)) { New-Item -ItemType Directory -Path $stagingRoot -Force | Out-Null }
        Join-Path $stagingRoot 'XPLabs.LockScreen.exe'
      }
      Copy-Item -Path $lockscreenExe -Destination $remoteExe -ToSession $session -Force
      Invoke-Command -Session $session -ScriptBlock {
        param($remoteFile)
        $lockDir = Join-Path $env:ProgramFiles 'XPLabsAgent\LockScreen'
        if (-not (Test-Path $lockDir)) { New-Item -ItemType Directory -Path $lockDir -Force | Out-Null }
        Copy-Item -Path $remoteFile -Destination (Join-Path $lockDir 'XPLabs.LockScreen.exe') -Force
      } -ArgumentList $remoteExe
    }

    Invoke-Command -Session $session -ScriptBlock {
      Start-ScheduledTask -TaskName 'XPLabsAgentLoop' -ErrorAction SilentlyContinue
      Start-ScheduledTask -TaskName 'XPLabsLockScreen' -ErrorAction SilentlyContinue
    } | Out-Null
  }
  finally {
    if ($session) { Remove-PSSession -Session $session -ErrorAction SilentlyContinue }
  }

  $result = New-Result -Success $true -Message "Update applied successfully" -Data @{
    target_host = $TargetHost
    version = $Version
    lockscreen_updated = $hasLockscreenExe
  }
  $result | ConvertTo-Json -Depth 10 -Compress
  exit 0
}
catch {
  $err = New-Result -Success $false -Message $_.Exception.Message -Data @{
    target_host = $TargetHost
    version = $Version
  }
  $err | ConvertTo-Json -Depth 10 -Compress
  exit 1
}
