param(
    [switch] $ForceDownload
)

. (Join-Path $PSScriptRoot 'qmdb-toolchain-common.ps1')

$lock = Get-QmdbToolchainLock
$runtimeRoot = Get-QmdbRuntimeRoot
$toolchainRoot = Get-QmdbToolchainRoot
$downloadsRoot = Join-Path $runtimeRoot 'downloads'
$stagingRoot = Join-Path $runtimeRoot 'staging'

New-Item -ItemType Directory -Force -Path $downloadsRoot, $stagingRoot, $toolchainRoot | Out-Null

function Get-QmdbVerifiedArchive {
    param(
        [Parameter(Mandatory)] $Component,
        [Parameter(Mandatory)][string] $Name
    )

    $archivePath = Join-Path $downloadsRoot ([string] $Component.archive)
    Assert-QmdbRuntimeChildPath -Path $archivePath | Out-Null

    $validExistingArchive = $false
    if ((Test-Path -LiteralPath $archivePath) -and -not $ForceDownload) {
        $validExistingArchive = (Get-QmdbSha256 -Path $archivePath) -eq ([string] $Component.sha256).ToLowerInvariant()
    }

    if (-not $validExistingArchive) {
        if (Test-Path -LiteralPath $archivePath) {
            Remove-Item -LiteralPath $archivePath -Force
        }

        Write-Host "Downloading verified $Name archive from its official distributor..."
        $previousProgressPreference = $ProgressPreference
        $ProgressPreference = 'SilentlyContinue'
        try {
            Invoke-WebRequest -UseBasicParsing -Uri ([string] $Component.url) -OutFile $archivePath
        } finally {
            $ProgressPreference = $previousProgressPreference
        }
    }

    $actualSha256 = Get-QmdbSha256 -Path $archivePath
    $expectedSha256 = ([string] $Component.sha256).ToLowerInvariant()
    if ($actualSha256 -ne $expectedSha256) {
        throw "$Name SHA-256 verification failed."
    }

    $md5Property = $Component.PSObject.Properties['md5']
    if ($null -ne $md5Property -and ([string] $md5Property.Value).Length -gt 0) {
        $actualMd5 = (Get-FileHash -Algorithm MD5 -LiteralPath $archivePath).Hash.ToLowerInvariant()
        if ($actualMd5 -ne ([string] $md5Property.Value).ToLowerInvariant()) {
            throw "$Name official MD5 verification failed."
        }
    }

    Write-Host "$Name archive verified: $actualSha256"
    return $archivePath
}

function Install-QmdbZipComponent {
    param(
        [Parameter(Mandatory)][string] $ArchivePath,
        [Parameter(Mandatory)][string] $Destination,
        [Parameter(Mandatory)][string] $Name
    )

    Assert-QmdbRuntimeChildPath -Path $Destination | Out-Null
    $staging = Join-Path $stagingRoot ($Name.ToLowerInvariant() -replace '[^a-z0-9]+', '-')
    Assert-QmdbRuntimeChildPath -Path $staging | Out-Null

    if (Test-Path -LiteralPath $staging) {
        Remove-Item -LiteralPath $staging -Recurse -Force
    }
    if (Test-Path -LiteralPath $Destination) {
        Remove-Item -LiteralPath $Destination -Recurse -Force
    }

    New-Item -ItemType Directory -Force -Path $staging, $Destination | Out-Null
    Expand-Archive -LiteralPath $ArchivePath -DestinationPath $staging -Force

    $children = @(Get-ChildItem -LiteralPath $staging -Force)
    $contentRoot = $staging
    if ($children.Count -eq 1 -and $children[0].PSIsContainer) {
        $contentRoot = $children[0].FullName
    }

    Get-ChildItem -LiteralPath $contentRoot -Force | Copy-Item -Destination $Destination -Recurse -Force
    Remove-Item -LiteralPath $staging -Recurse -Force
}

$phpArchive = Get-QmdbVerifiedArchive -Component $lock.php -Name 'PHP'
Install-QmdbZipComponent -ArchivePath $phpArchive -Destination (Join-Path $toolchainRoot 'php') -Name 'PHP'

$phpRoot = Join-Path $toolchainRoot 'php'
$phpIniTemplate = Join-Path $phpRoot 'php.ini-production'
$phpIniPath = Join-Path $phpRoot 'php.ini'
if (-not (Test-Path -LiteralPath $phpIniTemplate)) {
    throw 'The verified PHP archive does not contain php.ini-production.'
}

$phpIni = Get-Content -Raw -LiteralPath $phpIniTemplate
$phpIni = [regex]::Replace($phpIni, '(?m)^;?\s*date\.timezone\s*=.*$', 'date.timezone=UTC')
$phpIni = [regex]::Replace($phpIni, '(?m)^;?\s*extension_dir\s*=\s*"ext"\s*$', 'extension_dir="ext"')
foreach ($extension in @('curl', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'pdo_sqlite', 'sodium', 'sqlite3', 'zip')) {
    $pattern = '(?m)^;\s*extension=' + [regex]::Escape($extension) + '\s*$'
    $phpIni = [regex]::Replace($phpIni, $pattern, 'extension=' + $extension)
}
Set-Content -LiteralPath $phpIniPath -Value $phpIni -Encoding UTF8

$nodeArchive = Get-QmdbVerifiedArchive -Component $lock.node -Name 'Node.js'
Install-QmdbZipComponent -ArchivePath $nodeArchive -Destination (Join-Path $toolchainRoot 'node') -Name 'Node.js'

$composerArchive = Get-QmdbVerifiedArchive -Component $lock.composer -Name 'Composer'
$composerRoot = Join-Path $toolchainRoot 'composer'
New-Item -ItemType Directory -Force -Path $composerRoot | Out-Null
Copy-Item -LiteralPath $composerArchive -Destination (Join-Path $composerRoot 'composer.phar') -Force

$mysqlArchive = Get-QmdbVerifiedArchive -Component $lock.mysql -Name 'MySQL'
Install-QmdbZipComponent -ArchivePath $mysqlArchive -Destination (Join-Path $toolchainRoot 'mysql') -Name 'MySQL'

Write-Host 'QMDB portable toolchain archives are installed and ready for verification.'
