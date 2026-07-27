[CmdletBinding()]
param(
    [switch]$NoStart
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$environmentPath = Join-Path $projectRoot '.env'

function New-SecureValue {
    param([int]$ByteCount = 32)

    $bytes = New-Object byte[] $ByteCount
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($bytes)
    }
    finally {
        $generator.Dispose()
    }
    return [Convert]::ToBase64String($bytes).TrimEnd('=').Replace('+', '-').Replace('/', '_')
}

if (-not (Test-Path -LiteralPath $environmentPath)) {
    $lines = @(
        'MYSQL_DATABASE=u971957807_ffticket'
        'MYSQL_USER=ffticket_app'
        'WEB_API_BASE_URL=https://mediumorchid-hawk-477157.hostingersite.com/backend/api'
        ('MYSQL_PASSWORD=' + (New-SecureValue 24))
        ('MYSQL_ROOT_PASSWORD=' + (New-SecureValue 32))
        ('JWT_SECRET=' + (New-SecureValue 48))
        'BOOTSTRAP_ADMIN_NAME=System Admin'
        'BOOTSTRAP_ADMIN_EMAIL=admin@ffticket.local'
        ('BOOTSTRAP_ADMIN_PASSWORD=' + (New-SecureValue 18))
    )

    [System.IO.File]::WriteAllLines(
        $environmentPath,
        $lines,
        [System.Text.UTF8Encoding]::new($false)
    )
    Write-Host 'Created a private local Docker environment with generated credentials.'
}

if (-not $NoStart) {
    Push-Location $projectRoot
    try {
        docker compose up --detach --build
        if ($LASTEXITCODE -ne 0) {
            throw 'Docker Compose did not start successfully.'
        }
    }
    finally {
        Pop-Location
    }
}

Write-Host 'FFTicket web: http://localhost:8110'
Write-Host 'phpMyAdmin:  http://localhost:8111'
Write-Host 'First-run administrator credentials are stored in the private .env file.'
