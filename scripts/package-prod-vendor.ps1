$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$vendorPath = Join-Path $projectRoot 'vendor'
$archivePath = Join-Path $projectRoot 'vendor-prod.zip'

if (-not (Test-Path $vendorPath)) {
    throw "Le dossier vendor est introuvable : $vendorPath"
}

if (Test-Path $archivePath) {
    Remove-Item -LiteralPath $archivePath -Force
}

Compress-Archive -Path $vendorPath -DestinationPath $archivePath -CompressionLevel Optimal

Get-Item -LiteralPath $archivePath | Select-Object FullName, Length, LastWriteTime
