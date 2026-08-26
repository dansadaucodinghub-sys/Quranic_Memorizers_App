[CmdletBinding()]
param(
    [ValidateSet('repository', 'staging', 'artifact')]
    [string] $Mode = 'repository',
    [string] $Target
)

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$binary = Join-Path $projectRoot '.build\security-tools\bin\gitleaks.exe'
$configuration = Join-Path $projectRoot 'tools\Security\gitleaks.toml'
$reportRoot = Join-Path $projectRoot 'build\reports'
if (-not (Test-Path -LiteralPath $binary -PathType Leaf)) {
    throw 'Pinned Gitleaks binary is unavailable. Run tools/windows/install-qmdb-security-tools.ps1.'
}
New-Item -ItemType Directory -Force -Path $reportRoot | Out-Null

if ($Mode -eq 'repository') {
    & $binary git --no-banner --redact --config $configuration --report-format json `
        --report-path (Join-Path $reportRoot 'gitleaks-history.json') $projectRoot
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
    & $binary dir --no-banner --redact --config $configuration --report-format json `
        --report-path (Join-Path $reportRoot 'gitleaks-working-tree.json') $projectRoot
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
} else {
    if ([string]::IsNullOrWhiteSpace($Target)) {
        throw 'A scan target is required for staging and artifact modes.'
    }
    $resolvedTarget = [System.IO.Path]::GetFullPath($Target)
    if (-not (Test-Path -LiteralPath $resolvedTarget -PathType Container)) {
        throw 'Secret scan target is not a directory.'
    }
    & $binary dir --no-banner --redact --config $configuration --report-format json `
        --report-path (Join-Path $reportRoot "gitleaks-$Mode.json") $resolvedTarget
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}

Write-Output "Gitleaks $Mode scan: PASS"
