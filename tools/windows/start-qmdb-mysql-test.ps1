. (Join-Path $PSScriptRoot 'qmdb-toolchain-common.ps1')

$paths = Get-QmdbRuntimePaths
foreach ($binary in @($paths.MySqlServer, $paths.MySql, $paths.MySqlAdmin)) {
    if (-not (Test-Path -LiteralPath $binary)) {
        throw "Portable MySQL is not installed. Run tools/windows/bootstrap-qmdb-toolchain.ps1 first."
    }
}

$runtimeRoot = Get-QmdbRuntimeRoot
$mysqlRoot = Split-Path -Parent (Split-Path -Parent $paths.MySqlServer)
$dataRoot = Assert-QmdbRuntimeChildPath -Path $paths.MySqlData
$databaseDataRoot = Join-Path $dataRoot 'data'
$bootstrapMarker = Join-Path $dataRoot '.bootstrap-complete'
$bootstrapSql = Join-Path $dataRoot 'bootstrap.sql'
$stdoutLog = Join-Path $dataRoot 'mysqld.stdout.log'
$stderrLog = Join-Path $dataRoot 'mysqld.stderr.log'

New-Item -ItemType Directory -Force -Path $dataRoot | Out-Null

if (-not (Test-Path -LiteralPath $paths.TestEnvironment)) {
    $rootPassword = New-QmdbHexSecret
    $adminPassword = New-QmdbHexSecret
    $runtimePassword = New-QmdbHexSecret
    $schemaPassword = New-QmdbHexSecret
    $environment = @(
        'QMDB_TEST_DB_HOST=127.0.0.1'
        'QMDB_TEST_DB_PORT=3308'
        'QMDB_TEST_DB_NAME=qmdb_test'
        'QMDB_TEST_DB_USERNAME=qmdb_test_app'
        "QMDB_TEST_DB_PASSWORD=$runtimePassword"
        'QMDB_TEST_DB_TLS_MODE=disabled'
        'QMDB_TEST_DB_TLS_CA_FILE='
        'QMDB_TEST_DB_SCHEMA_USERNAME=qmdb_test_schema'
        "QMDB_TEST_DB_SCHEMA_PASSWORD=$schemaPassword"
        "QMDB_TEST_DB_ROOT_PASSWORD=$rootPassword"
        'QMDB_TEST_DB_ADMIN_USERNAME=qmdb_test_admin'
        "QMDB_TEST_DB_ADMIN_PASSWORD=$adminPassword"
    )
    Set-Content -LiteralPath $paths.TestEnvironment -Value $environment -Encoding ASCII
}

$existingEnvironment = Get-Content -LiteralPath $paths.TestEnvironment
if (-not ($existingEnvironment -match '^QMDB_TEST_DB_ADMIN_USERNAME=')) {
    Add-Content -LiteralPath $paths.TestEnvironment -Value @(
        'QMDB_TEST_DB_ADMIN_USERNAME=qmdb_test_admin'
        'QMDB_TEST_DB_ADMIN_PASSWORD=' + (New-QmdbHexSecret)
    ) -Encoding ASCII
}

Import-QmdbTestEnvironment

$mysqlRootForConfig = $mysqlRoot.Replace('\', '/')
$databaseDataRootForConfig = $databaseDataRoot.Replace('\', '/')
$pidFileForConfig = (Join-Path $dataRoot 'mysqld.pid').Replace('\', '/')
$errorLogForConfig = (Join-Path $dataRoot 'mysqld.error.log').Replace('\', '/')
$config = @"
[mysqld]
basedir=$mysqlRootForConfig
datadir=$databaseDataRootForConfig
port=3308
bind-address=127.0.0.1
mysqlx=0
skip-log-bin
skip-name-resolve
default-storage-engine=InnoDB
default-time-zone='+00:00'
character-set-server=utf8mb4
collation-server=utf8mb4_0900_ai_ci
sql-mode=STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY
pid-file=$pidFileForConfig
log-error=$errorLogForConfig
secure-file-priv=""

[client]
host=127.0.0.1
port=3308
protocol=TCP
default-character-set=utf8mb4
"@
Set-Content -LiteralPath $paths.MySqlConfig -Value $config -Encoding ASCII

function Test-QmdbMySqlReady {
    $previousPassword = $env:MYSQL_PWD
    $env:MYSQL_PWD = $env:QMDB_TEST_DB_ADMIN_PASSWORD
    try {
        & $paths.MySqlAdmin --defaults-file=$($paths.MySqlConfig) --user=$($env:QMDB_TEST_DB_ADMIN_USERNAME) ping --silent *> $null
        return $LASTEXITCODE -eq 0
    } finally {
        $env:MYSQL_PWD = $previousPassword
    }
}

if (Test-QmdbMySqlReady) {
    Write-Host 'QMDB MySQL 8.4 test server is already running on 127.0.0.1:3308.'
    exit 0
}

if (-not (Test-Path -LiteralPath (Join-Path $databaseDataRoot 'auto.cnf'))) {
    Write-Host 'Initializing isolated QMDB MySQL test data directory...'
    & $paths.MySqlServer --defaults-file=$($paths.MySqlConfig) --initialize-insecure --console
    if ($LASTEXITCODE -ne 0) {
        throw "MySQL data-directory initialization failed with exit code $LASTEXITCODE."
    }
}

$arguments = @("--defaults-file=$($paths.MySqlConfig)", '--standalone', '--console')
if (-not (Test-Path -LiteralPath $bootstrapMarker)) {
    $databaseName = $env:QMDB_TEST_DB_NAME
    $runtimeUser = $env:QMDB_TEST_DB_USERNAME
    $schemaUser = $env:QMDB_TEST_DB_SCHEMA_USERNAME
    $adminUser = $env:QMDB_TEST_DB_ADMIN_USERNAME
    if (
        ($databaseName -notmatch '^[A-Za-z0-9_]+$') -or
        ($runtimeUser -notmatch '^[A-Za-z0-9_]+$') -or
        ($schemaUser -notmatch '^[A-Za-z0-9_]+$') -or
        ($adminUser -notmatch '^[A-Za-z0-9_]+$')
    ) {
        throw 'Unsafe QMDB test database or user identifier.'
    }

    $sql = @"
ALTER USER 'root'@'localhost' IDENTIFIED BY '$($env:QMDB_TEST_DB_ROOT_PASSWORD)';
CREATE USER IF NOT EXISTS '$adminUser'@'127.0.0.1' IDENTIFIED BY '$($env:QMDB_TEST_DB_ADMIN_PASSWORD)';
GRANT ALL PRIVILEGES ON *.* TO '$adminUser'@'127.0.0.1' WITH GRANT OPTION;
CREATE DATABASE IF NOT EXISTS ``$databaseName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER IF NOT EXISTS '$runtimeUser'@'127.0.0.1' IDENTIFIED BY '$($env:QMDB_TEST_DB_PASSWORD)';
CREATE USER IF NOT EXISTS '$schemaUser'@'127.0.0.1' IDENTIFIED BY '$($env:QMDB_TEST_DB_SCHEMA_PASSWORD)';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, CREATE TEMPORARY TABLES, ALTER, DROP, INDEX, REFERENCES ON ``$databaseName``.* TO '$runtimeUser'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON ``$databaseName``.* TO '$schemaUser'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
    Set-Content -LiteralPath $bootstrapSql -Value $sql -Encoding ASCII
    $arguments += "--init-file=$bootstrapSql"
}

Write-Host 'Starting isolated QMDB MySQL 8.4 test server...'
Start-Process -FilePath $paths.MySqlServer -ArgumentList $arguments -WindowStyle Hidden -RedirectStandardOutput $stdoutLog -RedirectStandardError $stderrLog | Out-Null

$ready = $false
for ($attempt = 1; $attempt -le 45; $attempt++) {
    if (Test-QmdbMySqlReady) {
        $ready = $true
        break
    }
    Start-Sleep -Seconds 2
}

if (-not $ready) {
    $safeError = if (Test-Path -LiteralPath $stderrLog) { (Get-Content -LiteralPath $stderrLog -Tail 30) -join [Environment]::NewLine } else { 'No stderr log was produced.' }
    throw "QMDB MySQL test server failed to become ready.`n$safeError"
}

if (Test-Path -LiteralPath $bootstrapSql) {
    Remove-Item -LiteralPath $bootstrapSql -Force
}
if (-not (Test-Path -LiteralPath $bootstrapMarker)) {
    Set-Content -LiteralPath $bootstrapMarker -Value 'initialized' -Encoding ASCII
}

Write-Host 'QMDB MySQL 8.4 test server is ready on 127.0.0.1:3308.'
