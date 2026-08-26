param(
    [Parameter(Mandatory, Position = 0)]
    [ValidateSet('php', 'composer', 'node', 'npm', 'mysql', 'mysqladmin')]
    [string] $Tool,

    [Parameter(ValueFromRemainingArguments)]
    [string[]] $Arguments
)

. (Join-Path $PSScriptRoot 'use-qmdb-toolchain.ps1')

$paths = Get-QmdbRuntimePaths
$exitCode = 0
switch ($Tool) {
    'php' {
        & $paths.Php @Arguments
        $exitCode = $LASTEXITCODE
    }
    'composer' {
        & $paths.Php $paths.Composer @Arguments
        $exitCode = $LASTEXITCODE
    }
    'node' {
        & $paths.Node @Arguments
        $exitCode = $LASTEXITCODE
    }
    'npm' {
        & $paths.Npm @Arguments
        $exitCode = $LASTEXITCODE
    }
    'mysql' {
        & $paths.MySql @Arguments
        $exitCode = $LASTEXITCODE
    }
    'mysqladmin' {
        & $paths.MySqlAdmin @Arguments
        $exitCode = $LASTEXITCODE
    }
}

exit $exitCode
