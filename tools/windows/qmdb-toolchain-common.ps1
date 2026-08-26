$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$script:QmdbProjectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$script:QmdbRuntimeRoot = Join-Path $script:QmdbProjectRoot '.runtime'
$script:QmdbToolchainRoot = Join-Path $script:QmdbRuntimeRoot 'toolchain'
$script:QmdbToolchainLockPath = Join-Path $script:QmdbProjectRoot 'tools\runtime\toolchain.lock.json'

function Get-QmdbProjectRoot {
    return $script:QmdbProjectRoot
}

function Get-QmdbRuntimeRoot {
    return $script:QmdbRuntimeRoot
}

function Get-QmdbToolchainRoot {
    return $script:QmdbToolchainRoot
}

function Get-QmdbToolchainLock {
    if (-not (Test-Path -LiteralPath $script:QmdbToolchainLockPath)) {
        throw "QMDB toolchain lock is missing: $script:QmdbToolchainLockPath"
    }

    return Get-Content -Raw -LiteralPath $script:QmdbToolchainLockPath | ConvertFrom-Json
}

function Assert-QmdbRuntimeChildPath {
    param([Parameter(Mandatory)][string] $Path)

    $resolvedRuntime = [System.IO.Path]::GetFullPath($script:QmdbRuntimeRoot).TrimEnd('\') + '\'
    $resolvedPath = [System.IO.Path]::GetFullPath($Path)
    if (-not $resolvedPath.StartsWith($resolvedRuntime, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing operation outside the QMDB runtime directory: $resolvedPath"
    }

    return $resolvedPath
}

function Get-QmdbRuntimePaths {
    $toolchain = Get-QmdbToolchainRoot

    return [pscustomobject]@{
        Php = Join-Path $toolchain 'php\php.exe'
        Composer = Join-Path $toolchain 'composer\composer.phar'
        Node = Join-Path $toolchain 'node\node.exe'
        Npm = Join-Path $toolchain 'node\npm.cmd'
        MySql = Join-Path $toolchain 'mysql\bin\mysql.exe'
        MySqlAdmin = Join-Path $toolchain 'mysql\bin\mysqladmin.exe'
        MySqlServer = Join-Path $toolchain 'mysql\bin\mysqld.exe'
        MySqlData = Join-Path $script:QmdbRuntimeRoot 'mysql-data'
        MySqlConfig = Join-Path $script:QmdbRuntimeRoot 'mysql-data\my.ini'
        TestEnvironment = Join-Path $script:QmdbRuntimeRoot 'qmdb-test.env'
    }
}

function Get-QmdbSha256 {
    param([Parameter(Mandatory)][string] $Path)

    return (Get-FileHash -Algorithm SHA256 -LiteralPath $Path).Hash.ToLowerInvariant()
}

function Import-QmdbTestEnvironment {
    $paths = Get-QmdbRuntimePaths
    if (-not (Test-Path -LiteralPath $paths.TestEnvironment)) {
        return
    }

    foreach ($line in Get-Content -LiteralPath $paths.TestEnvironment) {
        $trimmed = $line.Trim()
        if ($trimmed.Length -eq 0 -or $trimmed.StartsWith('#')) {
            continue
        }

        $separator = $trimmed.IndexOf('=')
        if ($separator -lt 1) {
            throw "Invalid QMDB test environment entry."
        }

        $name = $trimmed.Substring(0, $separator)
        $value = $trimmed.Substring($separator + 1)
        if ($name -notmatch '^QMDB_TEST_DB_[A-Z0-9_]+$') {
            throw "Unexpected QMDB test environment key: $name"
        }

        [System.Environment]::SetEnvironmentVariable($name, $value, 'Process')
    }
}

function New-QmdbHexSecret {
    param([ValidateRange(16, 128)][int] $Bytes = 32)

    $buffer = New-Object byte[] $Bytes
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $generator.GetBytes($buffer)
    } finally {
        $generator.Dispose()
    }

    return ([System.BitConverter]::ToString($buffer) -replace '-', '').ToLowerInvariant()
}
