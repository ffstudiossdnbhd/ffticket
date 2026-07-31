[CmdletBinding()]
param()

$ErrorActionPreference = "Stop"

$workspaceDir = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$desktopDir = Join-Path $workspaceDir "desktop"
$publishDir = Join-Path $desktopDir "publish"
$rawDir = Join-Path $publishDir "raw-win-x64"
$payloadZip = Join-Path $publishDir "payload.zip"
$finalExe = Join-Path $publishDir "FFTicket.exe"
$desktopProject = Join-Path $desktopDir "FFTicket.Desktop\FFTicket.Desktop.csproj"
$bundleProject = Join-Path $desktopDir "FFTicket.Bundle\FFTicket.Bundle.csproj"
$publishRoot = [System.IO.Path]::GetFullPath($publishDir).TrimEnd([System.IO.Path]::DirectorySeparatorChar) + [System.IO.Path]::DirectorySeparatorChar

function Remove-ReleaseTemporaryPath {
    param([Parameter(Mandatory = $true)][string]$Path)

    $fullPath = [System.IO.Path]::GetFullPath($Path)
    if (-not $fullPath.StartsWith($publishRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to remove a path outside the desktop publish directory."
    }

    if (Test-Path -LiteralPath $fullPath) {
        Remove-Item -LiteralPath $fullPath -Recurse -Force
    }
}

function Assert-FileUnlocked {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    try {
        $stream = [System.IO.File]::Open($Path, [System.IO.FileMode]::Open, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)
        $stream.Dispose()
    }
    catch {
        throw "The existing desktop\\publish\\FFTicket.exe is in use. Close FFTicket, then run this script again."
    }
}

New-Item -ItemType Directory -Path $publishDir -Force | Out-Null
Remove-ReleaseTemporaryPath -Path $rawDir
Remove-ReleaseTemporaryPath -Path $payloadZip

try {
    Write-Host "1/3 Publishing FFTicket.Desktop..." -ForegroundColor Cyan
    dotnet publish $desktopProject -c Release -r win-x64 --self-contained true -o $rawDir
    if ($LASTEXITCODE -ne 0) {
        throw "Desktop publish failed."
    }

    Write-Host "2/3 Archiving the runtime payload..." -ForegroundColor Cyan
    Compress-Archive -Path (Join-Path $rawDir "*") -DestinationPath $payloadZip -Force

    Assert-FileUnlocked -Path $finalExe
    Write-Host "3/3 Building desktop\\publish\\FFTicket.exe..." -ForegroundColor Cyan
    dotnet publish $bundleProject -c Release -r win-x64 --self-contained true /p:PublishSingleFile=true /p:IncludeNativeLibrariesForSelfExtract=true /p:DebugType=None /p:DebugSymbols=false /p:PayloadArchive="$payloadZip" -o $publishDir
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $finalExe)) {
        throw "Single EXE packaging failed."
    }
}
finally {
    Remove-ReleaseTemporaryPath -Path $rawDir
    Remove-ReleaseTemporaryPath -Path $payloadZip
}

Write-Host "Success! Single EXE created at: $finalExe" -ForegroundColor Green
