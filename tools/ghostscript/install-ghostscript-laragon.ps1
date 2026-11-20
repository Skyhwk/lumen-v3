#Requires -RunAsAdministrator
<#
  Pasang Ghostscript AGPL 10.06 (Imagick / PDF BAK).
  Pesan "is already running" = installer lama masih hidup; skrip ini hentikan dulu.
#>
$ErrorActionPreference = 'Stop'

$installRoot = 'C:\laragon\bin\ghostscript\gs10060'
$laragonBin = 'C:\laragon\bin\ghostscript\bin'
$installer = Join-Path $env:TEMP 'gs10060w64.exe'
$downloadUrl = 'https://github.com/ArtifexSoftware/ghostpdl-downloads/releases/download/gs10060/gs10060w64.exe'

function Find-GhostscriptBinary {
    param([string]$Root)
    if (-not $Root -or -not (Test-Path $Root)) {
        return $null
    }
    $hit = Get-ChildItem -Path $Root -Recurse -Filter 'gswin64c.exe' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($hit) {
        return $hit.FullName
    }
    return $null
}

function Stop-GhostscriptInstallerProcesses {
    foreach ($name in @('gs10060w64', 'gs10060w32', 'gswin64c')) {
        Get-Process -Name $name -ErrorAction SilentlyContinue | ForEach-Object {
            Write-Host ('Menghentikan proses: ' + $_.Name + ' (pid ' + $_.Id + ')')
            Stop-Process -Id $_.Id -Force -ErrorAction SilentlyContinue
        }
    }
    Get-CimInstance Win32_Process -ErrorAction SilentlyContinue |
        Where-Object { $_.ExecutablePath -like '*gs10060w64.exe*' } |
        ForEach-Object {
            Write-Host ('Menghentikan installer: pid ' + $_.ProcessId)
            Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue
        }
    Start-Sleep -Seconds 2
}

function Finish-InstallFromBinary {
    param([string]$GsBin)

    if (-not (Test-Path $laragonBin)) {
        New-Item -ItemType Directory -Force -Path $laragonBin | Out-Null
    }

    $sourceBinDir = Split-Path $GsBin -Parent
    Copy-Item (Join-Path $sourceBinDir 'gswin64c.exe') (Join-Path $laragonBin 'gswin64c.exe') -Force
    Copy-Item (Join-Path $laragonBin 'gswin64c.exe') (Join-Path $laragonBin 'gs.exe') -Force

    $linkTarget = Join-Path $laragonBin 'gswin64c.exe'
    [Environment]::SetEnvironmentVariable('GHOSTSCRIPT_BINARY', $linkTarget, 'User')
    Write-Host ('GHOSTSCRIPT_BINARY (User) = ' + $linkTarget)

    $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
    if ($null -eq $userPath) {
        $userPath = ''
    }
    if ($userPath -notlike ('*' + $laragonBin + '*')) {
        $newPath = $laragonBin + ';' + $userPath
        [Environment]::SetEnvironmentVariable('Path', $newPath, 'User')
        Write-Host ('PATH User ditambah: ' + $laragonBin)
    }

    & $linkTarget --version
    Write-Host 'Ghostscript siap. Restart Laragon / buka terminal baru, lalu tes upload PDF BAK.'
}

Stop-GhostscriptInstallerProcesses

$gsBin = Find-GhostscriptBinary $laragonBin
if (-not $gsBin) {
    $gsBin = Find-GhostscriptBinary $installRoot
}
if (-not $gsBin) {
    $gsBin = Find-GhostscriptBinary 'C:\Program Files\gs'
}
if ($gsBin) {
    Write-Host ('Ghostscript sudah ada: ' + $gsBin)
    Finish-InstallFromBinary $gsBin
    exit 0
}

if (-not (Test-Path $installer) -or (Get-Item $installer).Length -lt 50MB) {
    Write-Host 'Mengunduh Ghostscript...'
    curl.exe -sL -o $installer $downloadUrl
}

if (-not (Test-Path $installer)) {
    throw ('Installer tidak ditemukan: ' + $installer)
}

$parent = Split-Path $installRoot -Parent
if (-not (Test-Path $parent)) {
    New-Item -ItemType Directory -Force -Path $parent | Out-Null
}

if (Test-Path $installRoot) {
    Write-Host ('Membersihkan instalasi lama: ' + $installRoot)
    Remove-Item $installRoot -Recurse -Force
}

Stop-GhostscriptInstallerProcesses

Write-Host ('Memasang ke ' + $installRoot + ' ...')
$proc = Start-Process -FilePath $installer -ArgumentList @('/S', ('/D=' + $installRoot)) -Wait -PassThru

$gsBin = Find-GhostscriptBinary $installRoot

if (-not $gsBin) {
    Write-Host ('Custom path gagal (exit ' + $proc.ExitCode + '). Menunggu installer selesai lalu coba default Program Files...')
    Stop-GhostscriptInstallerProcesses
    Start-Sleep -Seconds 1
    $proc2 = Start-Process -FilePath $installer -ArgumentList '/S' -Wait -PassThru
  foreach ($base in @('C:\Program Files\gs', 'C:\Program Files (x86)\gs')) {
        $gsBin = Find-GhostscriptBinary $base
        if ($gsBin) {
            Write-Host ('Ditemukan di: ' + $gsBin)
            break
        }
    }
    if (-not $gsBin) {
        throw ('Installer gagal. Tutup dialog "already running" jika masih muncul, jalankan: Get-Process gs10060w64 | Stop-Process -Force lalu ulangi skrip.')
    }
}

Finish-InstallFromBinary $gsBin