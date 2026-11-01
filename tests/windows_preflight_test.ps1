# No Docker, network, admin rights or database required.
$ErrorActionPreference = 'Stop'
. "$PSScriptRoot/../ops/check-windows.ps1" -LibraryOnly
$count = 0
function Assert-Check([bool]$Condition, [string]$Message) {
    if (-not $Condition) { throw $Message }
    $script:count++
}
Assert-Check (Test-GunIndexUbuntu2 "  NAME STATE VERSION`n* Ubuntu Running 2") 'Ubuntu WSL2 not detected'
Assert-Check (Test-GunIndexUbuntu2 "  Ubuntu-22.04 Stopped 2") 'Versioned Ubuntu not detected'
Assert-Check (-not (Test-GunIndexUbuntu2 '* Ubuntu Running 1')) 'WSL1 accepted'
Assert-Check (-not (Test-GunIndexUbuntu2 '* Debian Running 2')) 'Other default distro accepted'
Assert-Check (-not (Test-GunIndexUbuntu2 'Usage: wsl.exe Ubuntu 2')) 'Help output accepted'
$nulListing = ("* Ubuntu Running 2".ToCharArray() | ForEach-Object { [string]$_ + [char]0 }) -join ''
Assert-Check (Test-GunIndexUbuntu2 $nulListing) 'NUL-padded WSL output not detected'
$shell = (Get-Process -Id $PID).Path
$ok = Invoke-GunIndexProbe $shell '-NoProfile -NonInteractive -Command "Write-Output synthetic; exit 0"'
Assert-Check ($ok.Ok -and $ok.Output -eq 'synthetic') 'Successful process not captured'
$bad = Invoke-GunIndexProbe $shell '-NoProfile -NonInteractive -Command "exit 7"'
Assert-Check (-not $bad.Ok) 'Failed process accepted'
$timeout = Invoke-GunIndexProbe $shell '-NoProfile -NonInteractive -Command "Start-Sleep -Seconds 10"' 100
Assert-Check (-not $timeout.Ok -and $timeout.Reason -eq 'timeout') 'Timeout not enforced'
$missing = Invoke-GunIndexProbe 'gunindex-nonexistent-synthetic-command.exe' ''
Assert-Check (-not $missing.Ok -and $missing.Reason -eq 'unavailable') 'Missing executable accepted'
Write-Output "$count preflight checks passed. No application tests executed."
