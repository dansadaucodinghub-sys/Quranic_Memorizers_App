[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$manifestPath = Join-Path $projectRoot 'tools\Security\tool-versions.json'
$manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
$toolDefinitions = $manifest.windows_x86_64
if ($null -eq $toolDefinitions) {
    throw 'Windows security-tool definitions are missing.'
}

$downloadRoot = Join-Path $projectRoot '.runtime\security-tools\downloads'
$extractionRoot = Join-Path $projectRoot '.runtime\security-tools\extracted'
$binaryRoot = Join-Path $projectRoot '.build\security-tools\bin'
New-Item -ItemType Directory -Force -Path $downloadRoot, $extractionRoot, $binaryRoot | Out-Null

foreach ($toolName in @('actionlint', 'gitleaks', 'trivy')) {
    $definition = $toolDefinitions.$toolName
    if ($null -eq $definition) {
        throw "Missing security-tool definition: $toolName"
    }
    $source = [System.Uri]$definition.url
    if ($source.Scheme -ne 'https' -or $source.Host -ne 'github.com' -or -not $source.AbsolutePath.Contains('/releases/download/')) {
        throw "Rejected non-approved security-tool source: $toolName"
    }

    $archivePath = Join-Path $downloadRoot ([string]$definition.archive)
    Invoke-WebRequest -UseBasicParsing -Uri $source.AbsoluteUri -OutFile $archivePath
    $actualHash = (Get-FileHash -Algorithm SHA256 -LiteralPath $archivePath).Hash.ToLowerInvariant()
    $expectedHash = ([string]$definition.sha256).ToLowerInvariant()
    if ($actualHash -ne $expectedHash) {
        throw "Security-tool checksum mismatch: $toolName"
    }

    $toolExtractionRoot = Join-Path $extractionRoot "$toolName-$($definition.version)"
    New-Item -ItemType Directory -Force -Path $toolExtractionRoot | Out-Null
    Expand-Archive -LiteralPath $archivePath -DestinationPath $toolExtractionRoot -Force
    $verifiedBinary = Join-Path $toolExtractionRoot ([string]$definition.binary_path)
    if (-not (Test-Path -LiteralPath $verifiedBinary -PathType Leaf)) {
        throw "Verified archive does not contain the expected binary: $toolName"
    }
    $installedBinary = Join-Path $binaryRoot "$toolName.exe"
    Copy-Item -LiteralPath $verifiedBinary -Destination $installedBinary -Force
    $versionOutput = (& $installedBinary --version 2>&1 | Out-String)
    if ($versionOutput -notmatch [regex]::Escape([string]$definition.version)) {
        throw "Installed security-tool version could not be verified: $toolName"
    }
    Write-Output "Installed $toolName $($definition.version) from a checksum-verified official release."
}

Write-Output "QMDB Windows security tools are available at $binaryRoot"
