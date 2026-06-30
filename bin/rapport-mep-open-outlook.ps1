param(
    [Parameter(Position = 0)]
    [string] $Uri
)

$ErrorActionPreference = 'Stop'

function Get-ProtocolQueryParameter {
    param(
        [string] $InputUri,
        [string] $Name
    )

    if ([string]::IsNullOrWhiteSpace($InputUri)) {
        return ''
    }

    $query = ''
    if ($InputUri -match '\?(.*)$') {
        $query = $Matches[1]
    }

    foreach ($pair in ($query -split '&')) {
        if ([string]::IsNullOrWhiteSpace($pair)) {
            continue
        }

        $parts = $pair -split '=', 2
        $rawKey = ''
        if ($parts.Length -gt 0 -and $null -ne $parts[0]) {
            $rawKey = [string] $parts[0]
        }

        $key = [System.Uri]::UnescapeDataString($rawKey.Replace('+', ' '))
        if ($key -ne $Name) {
            continue
        }

        $value = if ($parts.Length -gt 1) { $parts[1] } else { '' }
        return [System.Uri]::UnescapeDataString($value.Replace('+', ' '))
    }

    return ''
}

function Add-LogLine {
    param(
        [string] $Path,
        [string] $Message
    )

    if ([string]::IsNullOrWhiteSpace($Path)) {
        return
    }

    $directory = Split-Path -Parent $Path
    if ($directory -and -not (Test-Path -LiteralPath $directory)) {
        New-Item -ItemType Directory -Path $directory -Force | Out-Null
    }

    Add-Content -LiteralPath $Path -Value ((Get-Date -Format o) + ' ' + $Message)
}

function Get-SafeFileName {
    param(
        [string] $Value,
        [string] $Fallback
    )

    $candidate = [string] $Value
    if ([string]::IsNullOrWhiteSpace($candidate)) {
        $candidate = $Fallback
    }

    foreach ($invalidChar in [System.IO.Path]::GetInvalidFileNameChars()) {
        $candidate = $candidate.Replace([string] $invalidChar, '_')
    }

    return $candidate
}

function Get-OutlookExecutablePath {
    $registryCandidates = @(
        'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\OUTLOOK.EXE',
        'HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\App Paths\OUTLOOK.EXE'
    )

    foreach ($registryKey in $registryCandidates) {
        try {
            $registryValue = (Get-ItemProperty -Path $registryKey -ErrorAction Stop).'(default)'
            if (-not [string]::IsNullOrWhiteSpace($registryValue) -and (Test-Path -LiteralPath $registryValue)) {
                return $registryValue
            }
        } catch {
        }
    }

    $pathCandidates = @(
        'C:\Program Files\Microsoft Office\root\Office16\OUTLOOK.EXE',
        'C:\Program Files (x86)\Microsoft Office\root\Office16\OUTLOOK.EXE',
        'C:\Program Files\Microsoft Office\Office16\OUTLOOK.EXE',
        'C:\Program Files (x86)\Microsoft Office\Office16\OUTLOOK.EXE',
        'C:\Program Files\Microsoft Office\root\Office15\OUTLOOK.EXE',
        'C:\Program Files (x86)\Microsoft Office\root\Office15\OUTLOOK.EXE'
    )

    foreach ($candidate in $pathCandidates) {
        if (Test-Path -LiteralPath $candidate) {
            return $candidate
        }
    }

    return ''
}

function Open-OutlookComposeWindow {
    param(
        [pscustomobject] $Payload,
        [string] $LogPath
    )

    $outlookPath = Get-OutlookExecutablePath
    if (-not (Get-Process OUTLOOK -ErrorAction SilentlyContinue) -and -not [string]::IsNullOrWhiteSpace($outlookPath)) {
        Start-Process -FilePath $outlookPath | Out-Null
        Start-Sleep -Seconds 2
    }

    $lastException = $null

    for ($attempt = 1; $attempt -le 3; $attempt++) {
        $outlook = $null
        $mail = $null
        $inspector = $null

        try {
            $outlook = New-Object -ComObject Outlook.Application
            $mail = $outlook.CreateItem(0)

            $recipients = @()
            if ($Payload.recipients -is [System.Array]) {
                $recipients = @($Payload.recipients)
            } elseif (-not [string]::IsNullOrWhiteSpace([string] $Payload.recipients)) {
                $recipients = @([string] $Payload.recipients)
            }

            if ($recipients.Count -gt 0) {
                $mail.To = ($recipients -join ';')
            }

            $mail.Subject = [string] $Payload.subject

            $htmlBody = [string] $Payload.htmlBody
            $textBody = [string] $Payload.textBody
            if (-not [string]::IsNullOrWhiteSpace($htmlBody)) {
                $mail.HTMLBody = $htmlBody
            } elseif (-not [string]::IsNullOrWhiteSpace($textBody)) {
                $mail.Body = $textBody
            }

            $pdfPath = [string] $Payload.pdfPath
            if (-not [string]::IsNullOrWhiteSpace($pdfPath) -and (Test-Path -LiteralPath $pdfPath)) {
                [void] $mail.Attachments.Add($pdfPath)
            }

            $mail.Display()
            $inspector = $mail.GetInspector()
            if ($inspector -ne $null) {
                $inspector.Activate()
            }

            Add-LogLine -Path $LogPath -Message ('OK compose outlook via COM; attempt=' + $attempt + '; sent=' + $mail.Sent + '; attachments=' + $mail.Attachments.Count)
            return $true
        } catch {
            $lastException = $_.Exception
            Add-LogLine -Path $LogPath -Message ('WARN COM attempt ' + $attempt + ' failed: ' + $_.Exception.Message)

            if ($attempt -lt 3 -and -not [string]::IsNullOrWhiteSpace($outlookPath)) {
                try {
                    Start-Process -FilePath $outlookPath | Out-Null
                } catch {
                }

                Start-Sleep -Seconds 2
            }
        } finally {
            if ($inspector -ne $null) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($inspector) }
            if ($mail -ne $null) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($mail) }
            if ($outlook -ne $null) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($outlook) }
            [GC]::Collect()
            [GC]::WaitForPendingFinalizers()
        }
    }

    if ($lastException -ne $null) {
        throw $lastException
    }

    return $false
}

function Get-RemotePayload {
    param(
        [string] $PayloadUrl,
        [string] $ProjectDir,
        [string] $InitialLogPath
    )

    Add-LogLine -Path $InitialLogPath -Message ('FETCH ' + $PayloadUrl)
    $response = Invoke-WebRequest -Uri $PayloadUrl -Method Get -UseBasicParsing -TimeoutSec 45
    $reader = New-Object System.IO.StreamReader($response.RawContentStream, [System.Text.Encoding]::UTF8, $true)
    try {
        $jsonContent = $reader.ReadToEnd()
    } finally {
        $reader.Dispose()
    }
    $payload = $jsonContent | ConvertFrom-Json
    if ($null -eq $payload) {
        throw 'Le payload Outlook recu est vide.'
    }

    $reportId = [string] $payload.reportId
    $targetDirectory = if ([string]::IsNullOrWhiteSpace($reportId)) {
        Join-Path $env:TEMP 'dashboard-rapport-mep'
    } else {
        Join-Path $ProjectDir ('var\rapport-mep\exports\' + $reportId)
    }

    if (-not (Test-Path -LiteralPath $targetDirectory)) {
        New-Item -ItemType Directory -Path $targetDirectory -Force | Out-Null
    }

    $logPath = Join-Path $targetDirectory 'outlook-open.log'
    Add-LogLine -Path $logPath -Message 'START protocol handler (remote payload)'

    $localPayload = [ordered]@{
        reportId = $reportId
        recipients = $payload.recipients
        subject = [string] $payload.subject
        textBody = [string] $payload.textBody
        htmlBody = [string] $payload.htmlBody
        pdfPath = ''
        pdfFileName = [string] $payload.pdfFileName
        emlPath = ''
    }

    $pdfContentBase64 = [string] $payload.pdfContentBase64
    if (-not [string]::IsNullOrWhiteSpace($pdfContentBase64)) {
        $pdfFileName = Get-SafeFileName -Value ([string] $payload.pdfFileName) -Fallback 'rapport-mep.pdf'
        $pdfPath = Join-Path $targetDirectory $pdfFileName
        [System.IO.File]::WriteAllBytes($pdfPath, [System.Convert]::FromBase64String($pdfContentBase64))
        $localPayload.pdfPath = $pdfPath
        $localPayload.pdfFileName = $pdfFileName
        Add-LogLine -Path $logPath -Message ('OK pdf reconstruit: ' + $pdfPath)
    }

    $emlContentBase64 = [string] $payload.emlContentBase64
    if (-not [string]::IsNullOrWhiteSpace($emlContentBase64)) {
        $emlFileName = Get-SafeFileName -Value ([string] $payload.emlFileName) -Fallback 'rapport-mep.eml'
        $emlPath = Join-Path $targetDirectory $emlFileName
        [System.IO.File]::WriteAllBytes($emlPath, [System.Convert]::FromBase64String($emlContentBase64))
        $localPayload.emlPath = $emlPath
        Add-LogLine -Path $logPath -Message ('OK eml reconstruit: ' + $emlPath)
    }

    return @{
        Payload = [pscustomobject] $localPayload
        LogPath = $logPath
    }
}

if ([string]::IsNullOrWhiteSpace($Uri) -and $args.Count -gt 0) {
    $Uri = [string] $args[0]
}

$projectDir = Split-Path -Parent $PSScriptRoot
$payloadUrl = Get-ProtocolQueryParameter -InputUri $Uri -Name 'payloadUrl'
$reportId = Get-ProtocolQueryParameter -InputUri $Uri -Name 'report'
$payload = $null
$logPath = Join-Path $env:TEMP 'dashboard-rapport-mep-outlook.log'

if (-not [string]::IsNullOrWhiteSpace($payloadUrl)) {
    $remotePayload = Get-RemotePayload -PayloadUrl $payloadUrl -ProjectDir $projectDir -InitialLogPath $logPath
    $payload = $remotePayload.Payload
    $logPath = $remotePayload.LogPath
} else {
    if ([string]::IsNullOrWhiteSpace($reportId)) {
        throw 'Identifiant de rapport manquant dans l''URL dashboardoutlook.'
    }

    $exportDir = Join-Path $projectDir ('var\rapport-mep\exports\' + $reportId)
    $payloadPath = Join-Path $exportDir 'outlook-payload.json'
    $logPath = Join-Path $exportDir 'outlook-open.log'

    Add-LogLine -Path $logPath -Message 'START protocol handler'

    if (-not (Test-Path -LiteralPath $payloadPath)) {
        Add-LogLine -Path $logPath -Message ('ERROR payload introuvable: ' + $payloadPath)
        throw 'Payload Outlook introuvable pour le rapport demande.'
    }

    $payload = Get-Content -LiteralPath $payloadPath -Raw | ConvertFrom-Json
}

try {
    if (-not (Open-OutlookComposeWindow -Payload $payload -LogPath $logPath)) {
        throw 'Impossible d ouvrir la fenetre de composition Outlook.'
    }
} catch {
    Add-LogLine -Path $logPath -Message ('WARN bascule sur le fichier EML: ' + $_.Exception.Message)

    $emlPath = [string] $payload.emlPath
    if ([string]::IsNullOrWhiteSpace($emlPath)) {
        $fallbackDirectory = if ($payload -and -not [string]::IsNullOrWhiteSpace([string] $payload.reportId)) {
            Join-Path $projectDir ('var\rapport-mep\exports\' + [string] $payload.reportId)
        } else {
            Split-Path -Parent $logPath
        }
        $emlPath = Join-Path $fallbackDirectory 'rapport-mep.eml'
    }

    if (-not (Test-Path -LiteralPath $emlPath)) {
        Add-LogLine -Path $logPath -Message ('ERROR eml introuvable: ' + $emlPath)
        throw 'Fichier EML introuvable pour le rapport demande.'
    }

    try {
        $outlookPath = Get-OutlookExecutablePath
        if (-not [string]::IsNullOrWhiteSpace($outlookPath)) {
            $arguments = '/eml "' + $emlPath.Replace('"', '""') + '"'
            Start-Process -FilePath $outlookPath -ArgumentList $arguments | Out-Null
            Add-LogLine -Path $logPath -Message ('OK fallback EML via executable: ' + $outlookPath)
        } else {
            Start-Process -FilePath $emlPath | Out-Null
            Add-LogLine -Path $logPath -Message 'OK fallback EML via association Windows'
        }
    } catch {
        Add-LogLine -Path $logPath -Message ('ERROR ' + $_.Exception.Message)
        throw
    }
}
