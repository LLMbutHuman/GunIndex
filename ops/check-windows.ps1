# Read-only development prerequisite checks. Compatible with Windows PowerShell 5.1.
[CmdletBinding()]
param([switch]$Json, [switch]$LibraryOnly)
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Invoke-GunIndexProbe {
    param([string]$File, [string]$Arguments, [int]$TimeoutMilliseconds = 15000)
    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = New-Object System.Diagnostics.ProcessStartInfo
    $process.StartInfo.FileName = $File
    $process.StartInfo.Arguments = $Arguments
    $process.StartInfo.UseShellExecute = $false
    $process.StartInfo.CreateNoWindow = $true
    $process.StartInfo.RedirectStandardOutput = $true
    $process.StartInfo.RedirectStandardError = $true
    try {
        [void]$process.Start()
        $stdout = $process.StandardOutput.ReadToEndAsync()
        $stderr = $process.StandardError.ReadToEndAsync()
        if (-not $process.WaitForExit($TimeoutMilliseconds)) {
            $process.Kill()
            $process.WaitForExit()
            return [pscustomobject]@{ Ok = $false; Output = ''; Reason = 'timeout' }
        }
        # WSL output can contain UTF-16 NULs when captured through Windows pipes.
        $output = $stdout.GetAwaiter().GetResult().Replace([string][char]0, '')
        [void]$stderr.GetAwaiter().GetResult()
        return [pscustomobject]@{ Ok = ($process.ExitCode -eq 0); Output = $output.Trim(); Reason = 'exit' }
    } catch {
        return [pscustomobject]@{ Ok = $false; Output = ''; Reason = 'unavailable' }
    } finally { $process.Dispose() }
}

function Test-GunIndexUbuntu2 {
    param([string]$Listing)
    # Match an actual distro row, never just the presence of wsl.exe or a header.
    return [bool]($Listing.Replace([string][char]0, '') -match '(?m)^\s*\*?\s*Ubuntu(?:-[0-9.]+)?\s+\S+\s+2\s*$')
}

function Get-GunIndexWindowsChecks {
    $checks = New-Object System.Collections.Generic.List[object]
    foreach ($tool in @(
        @{ Name = 'Git'; Command = 'git.exe'; Args = '--version'; Next = 'Install Git from its official publisher.' },
        @{ Name = 'Node'; Command = 'node.exe'; Args = '--version'; Next = 'Install a supported Node.js LTS from its official publisher.' }
    )) {
        $command = Get-Command $tool.Command -CommandType Application -ErrorAction SilentlyContinue
        $ready = $false
        if ($command) { $ready = (Invoke-GunIndexProbe $command.Source $tool.Args).Ok }
        $checks.Add([pscustomobject]@{ Name = $tool.Name; Ready = $ready; Next = $(if ($ready) { '' } else { $tool.Next }) })
    }
    $wsl = Get-Command wsl.exe -CommandType Application -ErrorAction SilentlyContinue
    $ubuntu = $false
    if ($wsl) {
        $listing = Invoke-GunIndexProbe $wsl.Source '--list --verbose'
        $ubuntu = $listing.Ok -and (Test-GunIndexUbuntu2 $listing.Output)
    }
    $checks.Add([pscustomobject]@{ Name = 'Ubuntu-WSL2'; Ready = $ubuntu; Next = $(if ($ubuntu) { '' } else { 'Administrator: wsl --install -d Ubuntu. Complete any requested restart and Ubuntu user setup; verify VERSION 2 with wsl --list --verbose.' }) })
    # Probe only a confirmed Ubuntu WSL2 distro; never silently use another default.
    $linuxReady = $false
    if ($ubuntu) {
        $row = ($listing.Output.Replace([string][char]0, '') -split "`n" | Where-Object { $_ -match '^\s*\*?\s*(Ubuntu(?:-[0-9.]+)?)\s+\S+\s+2\s*$' } | Select-Object -First 1)
        [void]($row -match '^\s*\*?\s*(Ubuntu(?:-[0-9.]+)?)\s+')
        $distro = $Matches[1]
        $probe = Invoke-GunIndexProbe $wsl.Source "-d $distro --exec sh -c `"command -v python3 >/dev/null && command -v node >/dev/null && test -r /etc/ssl/certs/ca-certificates.crt && test -S /var/run/docker.sock && docker --host=unix:///var/run/docker.sock info --format '{{.OSType}}'`"" 30000
        $linuxReady = $probe.Ok -and ($probe.Output -eq 'linux')
    }
    $checks.Add([pscustomobject]@{ Name = 'Linux-runtime'; Ready = $linuxReady; Next = $(if ($linuxReady) { '' } else { 'Start Docker Desktop with Linux containers and Ubuntu WSL integration. In Ubuntu provide python3, Node.js and CA certificates; then rerun this check. Windows PHP/MySQL/Python are not required.' }) })
    return $checks.ToArray()
}

if (-not $LibraryOnly) {
    $checks = @(Get-GunIndexWindowsChecks)
    $ready = @($checks | Where-Object { -not $_.Ready }).Count -eq 0
    if ($Json) {
        [pscustomobject]@{ Ready = $ready; Checks = $checks } | ConvertTo-Json -Depth 4
    } else {
        foreach ($check in $checks) {
            $state = if ($check.Ready) { 'OK' } else { 'MISSING' }
            Write-Output ("[{0}] {1}" -f $state, $check.Name)
            if ($check.Next) { Write-Output ('  ' + $check.Next) }
        }
        Write-Output 'Development only. No installation, migrations or containers were started. See docs/WINDOWS_DEVELOPMENT.md.'
    }
    if (-not $ready) { exit 1 }
    exit 0
}
