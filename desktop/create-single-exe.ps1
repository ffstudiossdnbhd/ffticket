# PowerShell Script to bundle FFTicket.Desktop publish output into a Single Standalone .EXE
param (
    [string]$PublishDir = "c:\Projects\FFTicket\desktop\publish\single-win-x64",
    [string]$OutputFile = "c:\Projects\FFTicket\desktop\publish\FFTicket-App.exe"
)

$PublishDir = Resolve-Path $PublishDir
$sedFile = Join-Path (Split-Path $OutputFile) "package.sed"

$rootFiles = Get-ChildItem -Path $PublishDir -File
$viewsFiles = Get-ChildItem -Path (Join-Path $PublishDir "Views") -File -ErrorAction SilentlyContinue
$controlsFiles = Get-ChildItem -Path (Join-Path $PublishDir "Controls") -File -ErrorAction SilentlyContinue

$src0 = ($rootFiles | ForEach-Object { "$($_.Name)=" }) -join "`r`n"
$src1 = ($viewsFiles | ForEach-Object { "$($_.Name)=" }) -join "`r`n"
$src2 = ($controlsFiles | ForEach-Object { "$($_.Name)=" }) -join "`r`n"

$sedContent = @"
[Version]
Class=IExpress
SEDVersion=3
[Options]
PackagePurpose=InstallApp
ShowInstallProgramWindow=0
HideExtractAnimation=1
UseLongFileName=1
InsideCompressed=1
CAB_FixedSize=0
CAB_ResvCodeSigning=0
RebootMode=N
InstallPrompt=%InstallPrompt%
DisplayLicense=%DisplayLicense%
FinishMessage=%FinishMessage%
TargetName=%TargetName%
FriendlyName=%FriendlyName%
AppLaunched=%AppLaunched%
PostInstallCmd=%PostInstallCmd%
AdminQuietInstCmd=%AdminQuietInstCmd%
UserQuietInstCmd=%UserQuietInstCmd%
SourceFiles=SourceFiles

[Strings]
InstallPrompt=
DisplayLicense=
FinishMessage=
TargetName=$OutputFile
FriendlyName=FFTicket Desktop App
AppLaunched=cmd /c "if not exist Views mkdir Views & if not exist Controls mkdir Controls & move *View*.xbf Views\ >nul 2>&1 & move *Window*.xbf Views\ >nul 2>&1 & move SemanticBadge.xbf Controls\ >nul 2>&1 & start "" FFTicket.Desktop.exe"
PostInstallCmd=<None>
AdminQuietInstCmd=
UserQuietInstCmd=

[SourceFiles]
SourceFiles0=$PublishDir\
SourceFiles1=$PublishDir\Views\
SourceFiles2=$PublishDir\Controls\

[SourceFiles0]
$src0

[SourceFiles1]
$src1

[SourceFiles2]
$src2
"@

Set-Content -Path $sedFile -Value $sedContent -Encoding ASCII
Write-Host "Packaging into single executable: $OutputFile ..."
Start-Process -FilePath "iexpress.exe" -ArgumentList "/N `"$sedFile`"" -NoNewWindow -Wait
if (Test-Path $OutputFile) {
    Write-Host "Success! Single EXE created at: $OutputFile"
} else {
    Write-Error "Failed to create executable."
}
