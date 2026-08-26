. (Join-Path $PSScriptRoot 'qmdb-toolchain-common.ps1')

& (Join-Path $PSScriptRoot 'stop-qmdb-mysql-test.ps1')
if ($LASTEXITCODE -ne 0) {
    throw "Refusing reset because MySQL shutdown failed with exit code $LASTEXITCODE."
}

$paths = Get-QmdbRuntimePaths
$dataRoot = Assert-QmdbRuntimeChildPath -Path $paths.MySqlData
$resolvedRuntime = [System.IO.Path]::GetFullPath((Get-QmdbRuntimeRoot)).TrimEnd('\') + '\'
if (-not $dataRoot.StartsWith($resolvedRuntime, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing reset outside the QMDB runtime directory: $dataRoot"
}

if (Test-Path -LiteralPath $dataRoot) {
    Remove-Item -LiteralPath $dataRoot -Recurse -Force
}

Write-Host 'QMDB MySQL test data removed. Starting a fresh isolated instance...'
& (Join-Path $PSScriptRoot 'start-qmdb-mysql-test.ps1')
exit $LASTEXITCODE
