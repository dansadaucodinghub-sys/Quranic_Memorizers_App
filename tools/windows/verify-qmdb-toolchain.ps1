. (Join-Path $PSScriptRoot 'use-qmdb-toolchain.ps1')

$paths = Get-QmdbRuntimePaths
$projectRoot = Get-QmdbProjectRoot
$failures = [System.Collections.Generic.List[string]]::new()

function Assert-QmdbResult {
    param(
        [Parameter(Mandatory)][bool] $Condition,
        [Parameter(Mandatory)][string] $Failure
    )

    if (-not $Condition) {
        $failures.Add($Failure)
    }
}

$phpVersion = (& $paths.Php -r 'echo PHP_VERSION;').Trim()
Assert-QmdbResult ($phpVersion -match '^8\.5\.') "PHP must be 8.5.x; resolved $phpVersion."

$extensions = @(& $paths.Php -m | ForEach-Object { $_.Trim().ToLowerInvariant() })
foreach ($extension in @('pdo', 'pdo_mysql', 'sodium', 'openssl', 'mbstring', 'fileinfo', 'curl', 'intl', 'zip')) {
    Assert-QmdbResult ($extensions -contains $extension) "Required PHP extension is unavailable: $extension"
}

$previousErrorActionPreference = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
$composerVersion = (& $paths.Php $paths.Composer --version 2>$null | Out-String).Trim()
$composerExitCode = $LASTEXITCODE
$ErrorActionPreference = $previousErrorActionPreference
Assert-QmdbResult ($composerExitCode -eq 0) 'Composer could not run under portable PHP.'

Push-Location $projectRoot
try {
    & $paths.Php $paths.Composer validate --strict
    Assert-QmdbResult ($LASTEXITCODE -eq 0) 'Composer validation failed.'
    & $paths.Php $paths.Composer check-platform-reqs
    Assert-QmdbResult ($LASTEXITCODE -eq 0) 'Composer platform requirements failed.'
} finally {
    Pop-Location
}

$nodeVersion = (& $paths.Node --version).Trim()
Assert-QmdbResult ($nodeVersion -match '^v24\.') "Node.js must be 24.x; resolved $nodeVersion."
$npmVersion = (& $paths.Npm --version).Trim()
Assert-QmdbResult ($LASTEXITCODE -eq 0) 'npm could not run under portable Node.js.'

& (Join-Path $PSScriptRoot 'start-qmdb-mysql-test.ps1')
Assert-QmdbResult ($LASTEXITCODE -eq 0) 'MySQL test server startup failed.'

$previousPassword = $env:MYSQL_PWD
$env:MYSQL_PWD = $env:QMDB_TEST_DB_PASSWORD
try {
    $mysqlProof = & $paths.MySql --defaults-file=$($paths.MySqlConfig) --user=$($env:QMDB_TEST_DB_USERNAME) --database=$($env:QMDB_TEST_DB_NAME) --batch --skip-column-names --execute="SELECT VERSION(), @@version_comment, @@default_storage_engine, @@session.time_zone, @@session.sql_mode, @@character_set_connection;"
    Assert-QmdbResult ($LASTEXITCODE -eq 0) 'MySQL identity query failed for the non-root runtime account.'
} finally {
    $env:MYSQL_PWD = $previousPassword
}

$mysqlProofText = ($mysqlProof | Out-String).Trim()
Assert-QmdbResult ($mysqlProofText -match '^8\.4\.11\s') "MySQL must be 8.4.11; proof was: $mysqlProofText"
Assert-QmdbResult ($mysqlProofText -match 'MySQL Community Server') 'Database identity is not Oracle MySQL Community Server.'
Assert-QmdbResult ($mysqlProofText -notmatch 'MariaDB') 'MariaDB is prohibited for authoritative QMDB tests.'
Assert-QmdbResult ($mysqlProofText -match '\sInnoDB\s') 'MySQL default storage engine is not InnoDB.'
Assert-QmdbResult ($mysqlProofText -match '\s\+00:00\s') 'MySQL session timezone is not UTC.'
Assert-QmdbResult ($mysqlProofText -match 'utf8mb4') 'MySQL connection character set is not utf8mb4.'

Write-Host "PHP=$phpVersion"
Write-Host "Composer=$composerVersion"
Write-Host "Node=$nodeVersion"
Write-Host "npm=$npmVersion"
Write-Host "MySQL=$mysqlProofText"

if ($failures.Count -gt 0) {
    foreach ($failure in $failures) {
        Write-Error $failure
    }
    exit 1
}

Write-Host 'QMDB toolchain verification: PASS'
exit 0
