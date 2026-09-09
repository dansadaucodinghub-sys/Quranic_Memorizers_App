[CmdletBinding()]
param(
    [ValidateSet('repository', 'staging', 'artifact')]
    [string] $Mode = 'repository',
    [string] $Target
)

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$binary = Join-Path $projectRoot '.build\security-tools\bin\trivy.exe'
$reportRoot = Join-Path $projectRoot 'build\reports'
if (-not (Test-Path -LiteralPath $binary -PathType Leaf)) {
    throw 'Pinned Trivy binary is unavailable. Run tools/windows/install-qmdb-security-tools.ps1.'
}
$resolvedTarget = if ([string]::IsNullOrWhiteSpace($Target)) {
    $projectRoot
} else {
    [System.IO.Path]::GetFullPath($Target)
}
if (-not (Test-Path -LiteralPath $resolvedTarget -PathType Container)) {
    throw 'Filesystem scan target is not a directory.'
}
& php (Join-Path $projectRoot 'tools\security\scan-trivy.php') $Mode $resolvedTarget
exit $LASTEXITCODE
