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
New-Item -ItemType Directory -Force -Path $reportRoot | Out-Null

& $binary filesystem --scanners vuln,secret,misconfig --severity HIGH,CRITICAL --exit-code 1 `
    --format json --output (Join-Path $reportRoot "trivy-$Mode.json") `
    --skip-dirs .git --skip-dirs .runtime --skip-dirs node_modules --skip-dirs build $resolvedTarget
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

Write-Output "Trivy $Mode filesystem scan: PASS"
