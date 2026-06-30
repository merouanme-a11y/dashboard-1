<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Twig\Environment;

final class RapportMepDocumentService
{
    public function __construct(
        private KernelInterface $kernel,
        private Environment $twig,
        private RapportMepService $rapportMepService,
        private string $mailerFrom,
    ) {}

    public function ensureReportPdf(array $report): array
    {
        $reportId = trim((string) ($report['id'] ?? ''));
        if ($reportId === '') {
            throw new \RuntimeException('Identifiant de rapport MEP manquant.');
        }

        $directory = $this->getExportDirectory($reportId);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de preparer le dossier d export du rapport MEP.');
        }

        $pdfPath = $directory . '/rapport-mep.pdf';
        $htmlPath = $directory . '/rapport-mep.html';

        if ($this->shouldRegeneratePdf($report, $pdfPath)) {
            $pdfReport = $this->preparePdfReport($report);
            $html = $this->twig->render('rapport_mep/pdf.html.twig', [
                'report' => $pdfReport,
                'colorMap' => $this->rapportMepService->getColorMap(),
                'previewStats' => $this->rapportMepService->buildPreviewStats($pdfReport),
            ]);

            file_put_contents($htmlPath, $html);
            $this->convertHtmlToPdf($htmlPath, $pdfPath);
        }

        return [
            'path' => $pdfPath,
            'fileName' => $this->buildPdfFileName($report),
        ];
    }

    public function buildEmailDraft(array $report): array
    {
        $pdfDocument = $this->ensureReportPdf($report);
        $email = (new Email())
            ->from($this->mailerFrom)
            ->subject(trim((string) ($report['emailSubject'] ?? '')) ?: (string) ($report['title'] ?? 'Rapport MEP'))
            ->text($this->rapportMepService->buildEmailTextBody($report))
            ->html($this->rapportMepService->buildEmailHtmlBody($report))
            ->attachFromPath((string) $pdfDocument['path'], (string) $pdfDocument['fileName'], 'application/pdf');

        foreach ($this->rapportMepService->getEmailRecipients($report) as $recipient) {
            $email->addTo($recipient);
        }

        $content = $this->markEmailAsEditableDraft($email->toString());

        return [
            'content' => $content,
            'fileName' => $this->buildEmlFileName($report),
            'pdf' => $pdfDocument,
        ];
    }

    public function openEmailDraftInOutlook(array $report): array
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            throw new \RuntimeException('L ouverture directe dans Outlook est disponible uniquement sur Windows en local.');
        }

        $reportId = trim((string) ($report['id'] ?? ''));
        if ($reportId === '') {
            throw new \RuntimeException('Identifiant de rapport MEP manquant.');
        }

        $draft = $this->buildEmailDraft($report);
        $directory = $this->getExportDirectory($reportId);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de preparer le dossier du brouillon mail.');
        }

        $emlPath = $directory . '/rapport-mep.eml';
        file_put_contents($emlPath, (string) $draft['content']);

        $this->launchOutlookComposeWindow($report, (string) $draft['pdf']['path']);

        return [
            'path' => $emlPath,
            'fileName' => (string) $draft['fileName'],
            'pdf' => $draft['pdf'],
        ];
    }

    public function prepareOutlookLaunch(array $report): array
    {
        $reportId = trim((string) ($report['id'] ?? ''));
        if ($reportId === '') {
            throw new \RuntimeException('Identifiant de rapport MEP manquant.');
        }

        $draft = $this->buildEmailDraft($report);
        $pdfDocument = $draft['pdf'];
        $directory = $this->getExportDirectory($reportId);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de preparer le dossier Outlook du rapport MEP.');
        }

        $emlPath = $directory . '/rapport-mep.eml';
        $payloadPath = $directory . '/outlook-payload.json';
        file_put_contents($emlPath, (string) ($draft['content'] ?? ''));
        $payload = [
            'reportId' => $reportId,
            'recipients' => $this->rapportMepService->getEmailRecipients($report),
            'subject' => trim((string) ($report['emailSubject'] ?? '')) ?: (string) ($report['title'] ?? 'Rapport MEP'),
            'textBody' => $this->rapportMepService->buildEmailTextBody($report),
            'htmlBody' => $this->rapportMepService->buildEmailHtmlBody($report),
            'pdfPath' => (string) ($pdfDocument['path'] ?? ''),
            'pdfFileName' => (string) ($pdfDocument['fileName'] ?? ''),
            'emlPath' => $emlPath,
            'generatedAt' => date(DATE_ATOM),
        ];

        file_put_contents(
            $payloadPath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return [
            'protocolUrl' => 'dashboardoutlook://open?report=' . rawurlencode($reportId),
            'payloadPath' => $payloadPath,
            'pdf' => $pdfDocument,
        ];
    }

    private function shouldRegeneratePdf(array $report, string $pdfPath): bool
    {
        if (!is_file($pdfPath)) {
            return true;
        }

        $pdfTimestamp = (int) @filemtime($pdfPath);
        $reportTimestamp = strtotime((string) ($report['updatedAt'] ?? '')) ?: 0;
        $templateTimestamp = max(
            (int) @filemtime($this->kernel->getProjectDir() . '/templates/rapport_mep/pdf.html.twig'),
            (int) @filemtime(__FILE__)
        );

        return $pdfTimestamp <= max($reportTimestamp, $templateTimestamp);
    }

    private function preparePdfReport(array $report): array
    {
        $prepared = $report;
        $prepared['emailSubject'] = $this->repairDisplayText((string) ($report['emailSubject'] ?? ''));
        $prepared['rows'] = [];

        foreach (is_array($report['rows'] ?? null) ? $report['rows'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $prepared['rows'][] = $this->preparePdfRow($row);
        }

        return $prepared;
    }

    private function preparePdfRow(array $row): array
    {
        foreach ([
            'ticketId',
            'type',
            'service',
            'state',
            'actionType',
            'resolvedAt',
            'summary',
            'owner',
            'reporter',
            'redmineLabel',
            'redmineUrl',
        ] as $field) {
            $row[$field] = $this->repairDisplayText((string) ($row[$field] ?? ''));
        }

        $row['serviceColorStyle'] = $this->resolveColorStyle((string) ($row['service'] ?? ''));
        $row['ownerColorStyle'] = $this->resolveColorStyle((string) ($row['owner'] ?? ''));

        return $row;
    }

    private function markEmailAsEditableDraft(string $content): string
    {
        if (preg_match('/^X-Unsent:\s*1$/mi', $content) === 1) {
            return $content;
        }

        $lineBreak = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $headerBlockSeparator = $lineBreak . $lineBreak;
        $separatorPosition = strpos($content, $headerBlockSeparator);

        if ($separatorPosition !== false) {
            return substr($content, 0, $separatorPosition)
                . $lineBreak
                . 'X-Unsent: 1'
                . $headerBlockSeparator
                . substr($content, $separatorPosition + strlen($headerBlockSeparator));
        }

        return 'X-Unsent: 1' . $headerBlockSeparator . $content;
    }

    private function resolveColorStyle(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $colorMap = $this->rapportMepService->getColorMap();
        $candidates = array_values(array_unique(array_filter([
            $value,
            $this->repairDisplayText($value),
        ], static fn (string $candidate): bool => $candidate !== '')));

        foreach ($candidates as $candidate) {
            if (isset($colorMap[$candidate]) && is_array($colorMap[$candidate])) {
                return $colorMap[$candidate];
            }
        }

        $normalizedMap = [];
        foreach ($colorMap as $label => $style) {
            $normalizedMap[$this->normalizeColorLookupKey((string) $label)] = $style;
        }

        foreach ($candidates as $candidate) {
            $normalizedCandidate = $this->normalizeColorLookupKey($candidate);
            if ($normalizedCandidate !== '' && isset($normalizedMap[$normalizedCandidate]) && is_array($normalizedMap[$normalizedCandidate])) {
                return $normalizedMap[$normalizedCandidate];
            }
        }

        return null;
    }

    private function launchOutlookComposeWindow(array $report, string $pdfPath): void
    {
        $reportId = trim((string) ($report['id'] ?? ''));
        if ($reportId === '') {
            throw new \RuntimeException('Identifiant de rapport MEP manquant.');
        }

        $recipients = implode(';', $this->rapportMepService->getEmailRecipients($report));
        $subject = trim((string) ($report['emailSubject'] ?? '')) ?: (string) ($report['title'] ?? 'Rapport MEP');
        $textBody = $this->rapportMepService->buildEmailTextBody($report);
        $htmlBody = $this->rapportMepService->buildEmailHtmlBody($report);
        $directory = $this->getExportDirectory($reportId);
        $logPath = $directory . '/outlook-open.log';
        $scriptPath = $directory . '/open-outlook-draft.ps1';
        $recipientsBase64 = base64_encode($recipients);
        $subjectBase64 = base64_encode($subject);
        $textBodyBase64 = base64_encode($textBody);
        $htmlBodyBase64 = base64_encode($htmlBody);
        $pdfPathBase64 = base64_encode($pdfPath);
        $logPathBase64 = base64_encode($logPath);

        $script = implode("\n", [
            "\$ErrorActionPreference = 'Stop'",
            '$recipient = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String(' . json_encode($recipientsBase64) . '))',
            '$subject = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String(' . json_encode($subjectBase64) . '))',
            '$textBody = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String(' . json_encode($textBodyBase64) . '))',
            '$htmlBody = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String(' . json_encode($htmlBodyBase64) . '))',
            '$pdfPath = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String(' . json_encode($pdfPathBase64) . '))',
            '$logPath = [System.Text.Encoding]::UTF8.GetString([System.Convert]::FromBase64String(' . json_encode($logPathBase64) . '))',
            '$outlook = $null',
            '$mail = $null',
            '$inspector = $null',
            "[System.IO.File]::WriteAllText(\$logPath, (Get-Date -Format o) + ' START' + [Environment]::NewLine)",
            'try {',
            '  $outlook = New-Object -ComObject Outlook.Application',
            '  $mail = $outlook.CreateItem(0)',
            "  if (\$recipient -ne '') { \$mail.To = \$recipient }",
            '  $mail.Subject = $subject',
            "  if (\$htmlBody -ne '') { \$mail.HTMLBody = \$htmlBody } elseif (\$textBody -ne '') { \$mail.Body = \$textBody }",
            '  if (Test-Path -LiteralPath $pdfPath) { [void] $mail.Attachments.Add($pdfPath) }',
            '  $mail.Display()',
            '  $inspector = $mail.GetInspector',
            '  if ($inspector -ne $null) {',
            '    $inspector.Activate()',
            '    Start-Sleep -Milliseconds 400',
            '    try { Add-Type -AssemblyName Microsoft.VisualBasic -ErrorAction Stop; [Microsoft.VisualBasic.Interaction]::AppActivate($inspector.Caption) | Out-Null } catch {}',
            '  }',
            '  Start-Sleep -Milliseconds 900',
            "  Add-Content -LiteralPath \$logPath -Value ((Get-Date -Format o) + ' OK')",
            '} catch {',
            "  Add-Content -LiteralPath \$logPath -Value ((Get-Date -Format o) + ' ERROR ' + \$_.Exception.Message)",
            '  exit 1',
            '} finally {',
            '  if ($inspector -ne $null) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($inspector) }',
            '  if ($mail -ne $null) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($mail) }',
            '  if ($outlook -ne $null) { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($outlook) }',
            '  [GC]::Collect()',
            '  [GC]::WaitForPendingFinalizers()',
            '}',
        ]);

        file_put_contents($scriptPath, $script);

        $commandLine = sprintf(
            'cmd /c start "" /min powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -Sta -File "%s"',
            str_replace('"', '""', $scriptPath)
        );

        $this->runDetachedShellCommandLine($commandLine, 10);
    }

    private function findOutlookBinary(): ?string
    {
        $finder = new ExecutableFinder();

        foreach ([
            $_ENV['OUTLOOK_BINARY'] ?? null,
            $_SERVER['OUTLOOK_BINARY'] ?? null,
            $finder->find('outlook'),
            'C:\\Program Files\\Microsoft Office\\root\\Office16\\OUTLOOK.EXE',
            'C:\\Program Files (x86)\\Microsoft Office\\root\\Office16\\OUTLOOK.EXE',
            'C:\\Program Files\\Microsoft Office\\Office16\\OUTLOOK.EXE',
            'C:\\Program Files (x86)\\Microsoft Office\\Office16\\OUTLOOK.EXE',
            'C:\\Program Files\\Microsoft Office\\root\\Office15\\OUTLOOK.EXE',
            'C:\\Program Files (x86)\\Microsoft Office\\root\\Office15\\OUTLOOK.EXE',
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeColorLookupKey(string $value): string
    {
        $value = $this->repairDisplayText($value);
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = is_string($converted) && $converted !== '' ? $converted : $value;
        $value = strtolower(trim($value));

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function repairDisplayText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return strtr($value, [
            'Ã©' => 'é',
            'Ã¨' => 'è',
            'Ãª' => 'ê',
            'Ã«' => 'ë',
            'Ã‰' => 'É',
            'Ã€' => 'À',
            'Ã ' => 'à',
            'Ã¢' => 'â',
            'Ã¤' => 'ä',
            'Ã¹' => 'ù',
            'Ã»' => 'û',
            'Ã¼' => 'ü',
            'Ã´' => 'ô',
            'Ã¶' => 'ö',
            'Ã®' => 'î',
            'Ã¯' => 'ï',
            'Ã§' => 'ç',
            'â€™' => "'",
            'â€“' => '-',
            'â€”' => '-',
            'â€œ' => '"',
            'â€' => '"',
            'Â°' => '°',
            'Â' => '',
        ]);
    }

    private function convertHtmlToPdf(string $htmlPath, string $pdfPath): void
    {
        $binary = $this->findLibreOfficeBinary();
        if ($binary === null) {
            throw new \RuntimeException('LibreOffice est requis pour generer le PDF du rapport MEP.');
        }

        $outputDirectory = dirname($pdfPath);
        $expectedOutputPath = $outputDirectory . '/' . pathinfo($htmlPath, PATHINFO_FILENAME) . '.pdf';

        if (is_file($expectedOutputPath)) {
            @unlink($expectedOutputPath);
        }

        if (is_file($pdfPath)) {
            @unlink($pdfPath);
        }

        $result = $this->runCommand([
            $binary,
            '--headless',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDirectory,
            $htmlPath,
        ], 120, $this->getLibreOfficeWorkingDirectory($outputDirectory), $this->getLibreOfficeEnvironment($outputDirectory));

        if (!$result['success']) {
            $errorMessage = trim((string) ($result['errorOutput'] !== '' ? $result['errorOutput'] : $result['output']));
            throw new \RuntimeException('La conversion PDF du rapport MEP a echoue : ' . $errorMessage);
        }

        if (!is_file($expectedOutputPath)) {
            throw new \RuntimeException('Le PDF du rapport MEP n a pas ete genere.');
        }

        if ($expectedOutputPath !== $pdfPath && !@rename($expectedOutputPath, $pdfPath)) {
            throw new \RuntimeException('Impossible d enregistrer le PDF du rapport MEP.');
        }
    }

    private function findLibreOfficeBinary(): ?string
    {
        $finder = new ExecutableFinder();

        foreach ([
            $_ENV['LIBREOFFICE_BINARY'] ?? null,
            $_SERVER['LIBREOFFICE_BINARY'] ?? null,
            $finder->find('soffice'),
            $finder->find('libreoffice'),
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
            '/snap/bin/libreoffice',
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function getExportDirectory(string $reportId): string
    {
        return $this->kernel->getProjectDir() . '/var/rapport-mep/exports/' . $reportId;
    }

    private function buildPdfFileName(array $report): string
    {
        $releaseDate = trim((string) ($report['releaseDate'] ?? ''));
        if ($releaseDate === '') {
            $releaseDate = date('Y-m-d');
        }

        return 'rapport-mep-' . $releaseDate . '.pdf';
    }

    private function buildEmlFileName(array $report): string
    {
        $releaseDate = trim((string) ($report['releaseDate'] ?? ''));
        if ($releaseDate === '') {
            $releaseDate = date('Y-m-d');
        }

        return 'rapport-mep-' . $releaseDate . '.eml';
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     *
     * @return array{success: bool, output: string, errorOutput: string, exitCode: int}
     */
    private function runCommand(array $command, int $timeoutSeconds = 120, ?string $workingDirectory = null, array $environment = []): array
    {
        if ($this->isPhpFunctionAvailable('proc_open')) {
            $processEnvironment = $environment !== [] ? array_merge($_ENV, $_SERVER, $environment) : null;
            $process = new Process($command, $workingDirectory, $processEnvironment);
            $process->setTimeout($timeoutSeconds);
            $process->run();

            return [
                'success' => $process->isSuccessful(),
                'output' => trim($process->getOutput()),
                'errorOutput' => trim($process->getErrorOutput()),
                'exitCode' => $process->getExitCode() ?? 1,
            ];
        }

        return $this->runCommandWithoutProcess($command, $workingDirectory, $environment);
    }

    private function runDetachedShellCommandLine(string $commandLine, int $timeoutSeconds = 10): void
    {
        if ($this->isPhpFunctionAvailable('proc_open')) {
            $process = Process::fromShellCommandline($commandLine);
            $process->setTimeout($timeoutSeconds);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Impossible de lancer l ouverture du brouillon dans Outlook. ' . trim($process->getErrorOutput() ?: $process->getOutput()));
            }

            return;
        }

        if ($this->isPhpFunctionAvailable('exec')) {
            $output = [];
            $exitCode = 0;
            @exec($commandLine . (DIRECTORY_SEPARATOR === '\\' ? ' >NUL 2>&1' : ' >/dev/null 2>&1 &'), $output, $exitCode);

            if ($exitCode !== 0) {
                throw new \RuntimeException('Impossible de lancer l ouverture du brouillon dans Outlook.');
            }

            return;
        }

        if ($this->isPhpFunctionAvailable('system')) {
            ob_start();
            $exitCode = 0;
            @system($commandLine . (DIRECTORY_SEPARATOR === '\\' ? ' >NUL 2>&1' : ' >/dev/null 2>&1 &'), $exitCode);
            ob_end_clean();

            if ($exitCode !== 0) {
                throw new \RuntimeException('Impossible de lancer l ouverture du brouillon dans Outlook.');
            }

            return;
        }

        throw new \RuntimeException('Le serveur PHP ne permet pas de lancer Outlook automatiquement.');
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     *
     * @return array{success: bool, output: string, errorOutput: string, exitCode: int}
     */
    private function runCommandWithoutProcess(array $command, ?string $workingDirectory = null, array $environment = []): array
    {
        $commandLine = $this->buildShellCommand($command, $workingDirectory, $environment);

        if ($this->isPhpFunctionAvailable('exec')) {
            $output = [];
            $exitCode = 0;
            @exec($commandLine . ' 2>&1', $output, $exitCode);
            $combinedOutput = trim(implode(PHP_EOL, $output));

            return [
                'success' => $exitCode === 0,
                'output' => $combinedOutput,
                'errorOutput' => $combinedOutput,
                'exitCode' => $exitCode,
            ];
        }

        if ($this->isPhpFunctionAvailable('system')) {
            ob_start();
            $exitCode = 0;
            @system($commandLine . ' 2>&1', $exitCode);
            $combinedOutput = trim((string) ob_get_clean());

            return [
                'success' => $exitCode === 0,
                'output' => $combinedOutput,
                'errorOutput' => $combinedOutput,
                'exitCode' => $exitCode,
            ];
        }

        throw new \RuntimeException('Le serveur PHP ne permet pas d executer les commandes systeme requises.');
    }

    /**
     * @param list<string> $command
     * @param array<string, string> $environment
     */
    private function buildShellCommand(array $command, ?string $workingDirectory = null, array $environment = []): string
    {
        $commandParts = array_map(
            static fn (string $part): string => escapeshellarg($part),
            array_map(static fn ($part): string => (string) $part, $command)
        );
        $commandLine = implode(' ', $commandParts);

        if (DIRECTORY_SEPARATOR === '\\') {
            $prefixParts = [];

            if ($workingDirectory !== null && trim($workingDirectory) !== '') {
                $prefixParts[] = 'cd /d ' . escapeshellarg($workingDirectory);
            }

            if ($environment !== []) {
                foreach ($environment as $name => $value) {
                    $normalizedName = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '';
                    if ($normalizedName === '') {
                        continue;
                    }

                    $prefixParts[] = 'set "' . $normalizedName . '=' . str_replace('"', '""', (string) $value) . '"';
                }
            }

            if ($prefixParts !== []) {
                $commandLine = implode(' && ', $prefixParts) . ' && ' . $commandLine;
            }

            return 'cmd /d /c ' . escapeshellarg($commandLine);
        }

        $environmentPrefix = '';
        if ($environment !== []) {
            $assignments = [];
            foreach ($environment as $name => $value) {
                $normalizedName = preg_replace('/[^A-Za-z0-9_]/', '', $name) ?? '';
                if ($normalizedName === '') {
                    continue;
                }

                $assignments[] = $normalizedName . '=' . escapeshellarg((string) $value);
            }

            if ($assignments !== []) {
                $environmentPrefix = implode(' ', $assignments) . ' ';
            }
        }

        if ($workingDirectory !== null && trim($workingDirectory) !== '') {
            return 'cd ' . escapeshellarg($workingDirectory) . ' && ' . $environmentPrefix . $commandLine;
        }

        return $environmentPrefix . $commandLine;
    }

    private function isPhpFunctionAvailable(string $functionName): bool
    {
        if (!function_exists($functionName)) {
            return false;
        }

        $disabledFunctions = preg_split('/[\s,]+/', (string) ini_get('disable_functions')) ?: [];

        return !in_array($functionName, array_filter($disabledFunctions, static fn ($value): bool => is_string($value) && $value !== ''), true);
    }

    /**
     * @return array<string, string>
     */
    private function getLibreOfficeEnvironment(string $outputDirectory): array
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return [];
        }

        $currentHome = trim((string) ($_ENV['HOME'] ?? $_SERVER['HOME'] ?? ''));
        if ($currentHome !== '' && is_dir($currentHome) && is_writable($currentHome)) {
            return [];
        }

        $fallbackHome = trim((string) sys_get_temp_dir());
        if ($fallbackHome === '' || !is_dir($fallbackHome) || !is_writable($fallbackHome)) {
            $fallbackHome = $outputDirectory;
        }

        return ['HOME' => $fallbackHome];
    }

    private function getLibreOfficeWorkingDirectory(string $outputDirectory): ?string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return null;
        }

        $temporaryDirectory = trim((string) sys_get_temp_dir());
        if ($temporaryDirectory !== '' && is_dir($temporaryDirectory) && is_writable($temporaryDirectory)) {
            return $temporaryDirectory;
        }

        return $outputDirectory;
    }
}
