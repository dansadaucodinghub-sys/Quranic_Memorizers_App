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

Write-Host 'QMDB portable PHP, Node.js, Composer, and MySQL client are active for this PowerShell process.'
