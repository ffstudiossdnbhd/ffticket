# PowerShell script to publish FFTicket Desktop as a Single Executable
$ErrorActionPreference = "Stop"

$workspaceDir = Resolve-Path "$PSScriptRoot\.."
$desktopDir = Join-Path $workspaceDir "desktop"
$publishDir = Join-Path $desktopDir "publish"
$rawDir = Join-Path $publishDir "raw-win-x64"
$payloadZip = Join-Path $publishDir "payload.zip"
$finalExe = Join-Path $publishDir "FFTicket.exe"

Write-Host "1/3 Publishing FFTicket.Desktop project..." -ForegroundColor Cyan
dotnet publish (Join-Path $desktopDir "FFTicket.Desktop\FFTicket.Desktop.csproj") -c Release -r win-x64 --self-contained true -o $rawDir

Write-Host "2/3 Archiving payload..." -ForegroundColor Cyan
if (Test-Path $payloadZip) { Remove-Item $payloadZip -Force }
Compress-Archive -Path "$rawDir\*" -DestinationPath $payloadZip -Force

Write-Host "3/3 Building single standalone executable..." -ForegroundColor Cyan
dotnet publish (Join-Path $desktopDir "FFTicket.Bundle\FFTicket.Bundle.csproj") -c Release -r win-x64 --self-contained true /p:PublishSingleFile=true /p:IncludeNativeLibrariesForSelfExtract=true /p:DebugType=None /p:DebugSymbols=false /p:PayloadArchive="$payloadZip" -o $publishDir

if (Test-Path $rawDir) { Remove-Item $rawDir -Recurse -Force }
if (Test-Path $payloadZip) { Remove-Item $payloadZip -Force }

Write-Host "Success! Single EXE created at: $finalExe" -ForegroundColor Green
