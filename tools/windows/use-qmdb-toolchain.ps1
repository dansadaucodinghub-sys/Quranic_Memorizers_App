. (Join-Path $PSScriptRoot 'qmdb-toolchain-common.ps1')

$paths = Get-QmdbRuntimePaths
$requiredFiles = @($paths.Php, $paths.Composer, $paths.Node, $paths.Npm, $paths.MySql, $paths.MySqlServer)
foreach ($requiredFile in $requiredFiles) {
    if (-not (Test-Path -LiteralPath $requiredFile)) {
        throw "QMDB toolchain is incomplete. Run tools/windows/bootstrap-qmdb-toolchain.ps1 first. Missing: $requiredFile"
    }
}

$phpDirectory = Split-Path -Parent $paths.Php
$nodeDirectory = Split-Path -Parent $paths.Node
$mysqlDirectory = Split-Path -Parent $paths.MySql
$env:PATH = "$phpDirectory;$nodeDirectory;$mysqlDirectory;$env:PATH"
$env:PHPRC = $phpDirectory
$env:COMPOSER_HOME = Join-Path (Get-QmdbRuntimeRoot) 'composer-home'
$env:COMPOSER_CACHE_DIR = Join-Path (Get-QmdbRuntimeRoot) 'composer-cache'
$env:npm_config_cache = Join-Path (Get-QmdbRuntimeRoot) 'npm-cache'
$env:npm_config_engine_strict = 'true'

New-Item -ItemType Directory -Force -Path $env:COMPOSER_HOME, $env:COMPOSER_CACHE_DIR, $env:npm_config_cache | Out-Null
Import-QmdbTestEnvironment

if ($env:QMDB_TEST_DB_HOST) {
    $testRuntimeMap = @{
        APP_ENV = 'test'
        APP_DEBUG = 'false'
        APP_TIMEZONE = 'UTC'
        APP_LOG_LEVEL = 'emergency'
        APP_PUBLIC_BASE_URL = 'http://127.0.0.1:8080'
        AUTH_CSRF_SIGNING_KEY = 'qmdb-test-csrf-signing-key-32-bytes-minimum'
        AUTH_IDENTITY_HMAC_KEY = 'qmdb-test-identity-hmac-key-32-bytes-minimum'
        AUTH_CONTACT_ENCRYPTION_KEY = 'Y2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2M='
        AUTH_CONTACT_ENCRYPTION_KEY_ID = 'test-v1'
        MAILER_DSN = 'null://null'
        MAIL_FROM_ADDRESS = 'no-reply@example.test'
        MAIL_FROM_NAME = 'QMDB Test'
        DB_HOST = $env:QMDB_TEST_DB_HOST
        DB_PORT = $env:QMDB_TEST_DB_PORT
        DB_NAME = $env:QMDB_TEST_DB_NAME
        DB_USERNAME = $env:QMDB_TEST_DB_USERNAME
        DB_PASSWORD = $env:QMDB_TEST_DB_PASSWORD
        DB_SCHEMA_USERNAME = $env:QMDB_TEST_DB_SCHEMA_USERNAME
        DB_SCHEMA_PASSWORD = $env:QMDB_TEST_DB_SCHEMA_PASSWORD
        DB_TLS_MODE = $env:QMDB_TEST_DB_TLS_MODE
        DB_TLS_CA_FILE = $env:QMDB_TEST_DB_TLS_CA_FILE
    }
    foreach ($entry in $testRuntimeMap.GetEnumerator()) {
        if ([string]::IsNullOrEmpty([System.Environment]::GetEnvironmentVariable($entry.Key, 'Process'))) {
            [System.Environment]::SetEnvironmentVariable($entry.Key, $entry.Value, 'Process')
        }
    }
}

Write-Host 'QMDB portable PHP, Node.js, Composer, and MySQL client are active for this PowerShell process.'
