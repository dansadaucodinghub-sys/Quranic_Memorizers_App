. (Join-Path $PSScriptRoot 'qmdb-toolchain-common.ps1')

$paths = Get-QmdbRuntimePaths
if (-not (Test-Path -LiteralPath $paths.MySqlAdmin) -or -not (Test-Path -LiteralPath $paths.TestEnvironment)) {
    Write-Host 'QMDB MySQL test server is not provisioned.'
    exit 0
}

Import-QmdbTestEnvironment
$previousPassword = $env:MYSQL_PWD
$env:MYSQL_PWD = $env:QMDB_TEST_DB_ADMIN_PASSWORD
try {
    & $paths.MySqlAdmin --defaults-file=$($paths.MySqlConfig) --user=$($env:QMDB_TEST_DB_ADMIN_USERNAME) ping --silent *> $null
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'QMDB MySQL test server is not running.'
        exit 0
    }

    & $paths.MySqlAdmin --defaults-file=$($paths.MySqlConfig) --user=$($env:QMDB_TEST_DB_ADMIN_USERNAME) shutdown
    if ($LASTEXITCODE -ne 0) {
        throw "MySQL shutdown failed with exit code $LASTEXITCODE."
    }
} finally {
    $env:MYSQL_PWD = $previousPassword
}

Write-Host 'QMDB MySQL test server stopped.'
