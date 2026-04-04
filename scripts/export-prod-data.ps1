param(
    [string]$OutputPath = (Join-Path $PSScriptRoot '..\deploy_prod_data.sql')
)

$ErrorActionPreference = 'Stop'

function Get-DatabaseUrl {
    $candidateFiles = @(
        (Join-Path $PSScriptRoot '..\.env.local'),
        (Join-Path $PSScriptRoot '..\.env')
    )

    foreach ($file in $candidateFiles) {
        if (-not (Test-Path $file)) {
            continue
        }

        foreach ($line in Get-Content $file) {
            if ($line -match '^\s*DATABASE_URL\s*=\s*"?(?<value>[^"]+)"?\s*$') {
                return $matches['value']
            }
        }
    }

    throw 'DATABASE_URL introuvable dans .env.local ou .env.'
}

function Parse-MySqlConnectionString {
    param(
        [Parameter(Mandatory = $true)]
        [string]$DatabaseUrl
    )

    if ($DatabaseUrl -notmatch '^mysql:\/\/(?<user>[^:]+):(?<password>[^@]*)@(?<host>[^:\/\?]+)(?::(?<port>\d+))?\/(?<database>[^\?]+)') {
        throw "DATABASE_URL non supportee : $DatabaseUrl"
    }

    return @{
        User = $matches['user']
        Password = $matches['password']
        Host = $matches['host']
        Port = if ($matches['port']) { $matches['port'] } else { '3306' }
        Database = $matches['database']
    }
}

function Find-MySqlDump {
    $command = Get-Command mysqldump.exe -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    $default = 'C:\wamp\bin\mysql\mysql8.4.7\bin\mysqldump.exe'
    if (Test-Path $default) {
        return $default
    }

    $found = Get-ChildItem 'C:\wamp\bin\mysql' -Recurse -Filter mysqldump.exe -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending |
        Select-Object -First 1 -ExpandProperty FullName

    if ($found) {
        return $found
    }

    throw 'mysqldump.exe introuvable.'
}

$connection = Parse-MySqlConnectionString -DatabaseUrl (Get-DatabaseUrl)
$mysqldump = Find-MySqlDump
$resolvedOutputPath = [System.IO.Path]::GetFullPath($OutputPath)
$outputDirectory = Split-Path -Parent $resolvedOutputPath

if (-not (Test-Path $outputDirectory)) {
    New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null
}

$tablesToClear = @(
    'user_page_preference',
    'permission',
    'page_icon',
    'page_title',
    'page',
    'theme_setting',
    'service_color',
    'services',
    'department',
    'module',
    'utilisateur'
)

$tablesToDump = @(
    'department',
    'services',
    'module',
    'utilisateur',
    'page',
    'page_icon',
    'page_title',
    'permission',
    'service_color',
    'theme_setting',
    'user_page_preference'
)

$header = @(
    '-- ============================================================',
    '-- DASHBOARD - Synchronisation des donnees PROD',
    '-- Genere automatiquement depuis la base locale',
    "-- Genere le : $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')",
    "-- Base source : $($connection.Database)",
    '-- ============================================================',
    'SET NAMES utf8mb4;',
    'SET FOREIGN_KEY_CHECKS = 0;',
    ''
)

$deleteStatements = foreach ($table in $tablesToClear) {
    'DELETE FROM `{0}`;' -f $table
}

$dumpArgs = @(
    "--host=$($connection.Host)",
    "--port=$($connection.Port)",
    "--user=$($connection.User)",
    "--password=$($connection.Password)",
    '--default-character-set=utf8mb4',
    '--skip-comments',
    '--skip-triggers',
    '--complete-insert',
    '--skip-extended-insert',
    '--no-create-info',
    $connection.Database
) + $tablesToDump

$dumpOutput = & $mysqldump @dumpArgs
if ($LASTEXITCODE -ne 0) {
    throw "mysqldump a echoue avec le code $LASTEXITCODE."
}

$footer = @(
    '',
    'SET FOREIGN_KEY_CHECKS = 1;',
    '-- ============================================================',
    '-- FIN DU SCRIPT',
    '-- ============================================================'
)

@(
    $header
    $deleteStatements
    ''
    $dumpOutput
    $footer
) | Set-Content -Path $resolvedOutputPath -Encoding UTF8

Write-Output "Export SQL genere : $resolvedOutputPath"
