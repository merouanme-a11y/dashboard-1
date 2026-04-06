<?php

namespace App\Service;

use App\Repository\UtilisateurRepository;
use PDO;

class LivreDeCaisseManagementService
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepository,
        private LegacyDirectoryOptionsService $legacyDirectoryOptionsService,
    ) {}

    public function getDepartementOptions(): array
    {
        return $this->legacyDirectoryOptionsService->getDepartementOptions();
    }

    public function buildAgencyOverview(PDO $pdo, string $businessDate, string $departementFilter = '', string $statusFilter = 'all'): array
    {
        $agencies = $this->createAgencyIndex();
        $usersById = $this->buildUsersById();
        $rows = $this->fetchBookRowsForDate($pdo, $businessDate);

        foreach ($rows as $row) {
            $agency = $this->resolveAgencyFromRow($row, $usersById);
            if ($agency === null) {
                continue;
            }

            $agencyId = (string) $agency['id'];
            if (!isset($agencies[$agencyId])) {
                $agencies[$agencyId] = $this->createAgencyOverviewRow($agency);
            }

            if ((string) ($row['record_type'] ?? '') === 'entry') {
                $agencies[$agencyId]['entry_count']++;
                $agencies[$agencyId]['has_activity'] = true;
                continue;
            }

            if ((int) ($row['id'] ?? 0) >= (int) ($agencies[$agencyId]['daily_state_id'] ?? 0)) {
                $agencies[$agencyId]['daily_state_id'] = (int) ($row['id'] ?? 0);
                $agencies[$agencyId]['fond_fin'] = (float) ($row['fond_caisse_fin'] ?? 0);
                $agencies[$agencyId]['fond_debut'] = (float) ($row['fond_caisse_debut'] ?? 0);
                $agencies[$agencyId]['is_closed'] = (int) ($row['journee_cloturee'] ?? 0) === 1
                    || trim((string) ($row['journee_cloturee_at'] ?? '')) !== '';
                $agencies[$agencyId]['closed_at'] = trim((string) ($row['journee_cloturee_at'] ?? ''));
                $agencies[$agencyId]['has_activity'] = true;
            }
        }

        $filtered = [];
        foreach ($agencies as $agency) {
            $agency['status'] = $this->resolveStatus($agency);
            $agency['status_label'] = match ($agency['status']) {
                'closed' => 'Cloture',
                'in_progress' => 'En cours',
                default => 'Sans saisie',
            };
            $agency['detail_url'] = $agency['has_activity']
                ? null
                : null;

            if ($departementFilter !== '' && (string) $agency['departement'] !== $departementFilter) {
                continue;
            }

            if ($statusFilter !== 'all' && $agency['status'] !== $statusFilter) {
                continue;
            }

            $filtered[] = $agency;
        }

        usort($filtered, static function (array $left, array $right): int {
            $departementCompare = strnatcasecmp((string) ($left['departement'] ?? ''), (string) ($right['departement'] ?? ''));
            if ($departementCompare !== 0) {
                return $departementCompare;
            }

            return strnatcasecmp((string) ($left['agence'] ?? ''), (string) ($right['agence'] ?? ''));
        });

        return $filtered;
    }

    public function buildAgencyDetail(PDO $pdo, string $businessDate, string $departement, string $agence): array
    {
        $usersById = $this->buildUsersById();
        $rows = $this->fetchBookRowsForDate($pdo, $businessDate);
        $expectedAgencyId = $this->buildAgencyId($departement, $agence);
        $entries = [];
        $dailyState = null;

        foreach ($rows as $row) {
            $resolvedAgency = $this->resolveAgencyFromRow($row, $usersById);
            if ($resolvedAgency === null || (string) $resolvedAgency['id'] !== $expectedAgencyId) {
                continue;
            }

            if ((string) ($row['record_type'] ?? '') === 'entry') {
                $entries[] = ldcHydrateEntryRow($row);
                continue;
            }

            if ($dailyState === null || (int) ($row['id'] ?? 0) > (int) ($dailyState['id'] ?? 0)) {
                $dailyState = ldcHydrateEntryRow($row);
            }
        }

        usort($entries, static function (array $left, array $right): int {
            $chronoCompare = ((int) ($right['chrono'] ?? 0)) <=> ((int) ($left['chrono'] ?? 0));
            if ($chronoCompare !== 0) {
                return $chronoCompare;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        $totaux = ldcGetTotaux($entries);
        $isClosed = $dailyState !== null && ldcIsDailyClosed($dailyState);
        $fondDebut = $dailyState !== null && $dailyState['fond_caisse_debut'] !== null
            ? (float) $dailyState['fond_caisse_debut']
            : 0.0;
        $fondFin = $dailyState !== null && $dailyState['fond_caisse_fin'] !== null
            ? (float) $dailyState['fond_caisse_fin']
            : ($fondDebut + (float) ($totaux['total'] ?? 0));

        return [
            'agency' => [
                'id' => $expectedAgencyId,
                'departement' => trim($departement),
                'agence' => trim($agence),
                'label' => trim($agence . (trim($departement) !== '' ? ' (' . trim($departement) . ')' : '')),
            ],
            'daily_state' => $dailyState,
            'entries' => $entries,
            'totaux' => $totaux,
            'entry_count' => count($entries),
            'fond_debut' => $fondDebut,
            'fond_fin' => $fondFin,
            'is_closed' => $isClosed,
            'closed_at' => trim((string) ($dailyState['journee_cloturee_at'] ?? '')),
        ];
    }

    private function fetchBookRowsForDate(PDO $pdo, string $businessDate): array
    {
        $stmt = $pdo->prepare(
            "SELECT
                id,
                record_type,
                business_date,
                date_saisie,
                chrono,
                type_affaire,
                risque,
                nom_adherent,
                prenom_adherent,
                type_encaissement,
                montant,
                fond_caisse_debut,
                fond_caisse_fin,
                journee_cloturee,
                journee_cloturee_at,
                journee_cloturee_by,
                created_by,
                updated_by,
                departement,
                agence
             FROM livredecaisse
             WHERE business_date = ?
               AND record_type IN ('entry', 'daily_state')
             ORDER BY record_type ASC, id DESC"
        );
        $stmt->execute([$businessDate]);

        return $stmt->fetchAll() ?: [];
    }

    /**
     * @return array<string, array{id:string, departement:string, agence:string, label:string}>
     */
    private function buildUsersById(): array
    {
        $usersById = [];

        foreach ($this->utilisateurRepository->findAllSorted() as $user) {
            $userId = $user->getId();
            if ($userId === null) {
                continue;
            }

            $snapshot = $this->normalizeAgencySnapshot(
                trim((string) ($user->getDepartement() ?? '')),
                trim((string) ($user->getAgence() ?? ''))
            );

            if ($snapshot === null) {
                continue;
            }

            $usersById[(string) $userId] = $snapshot;
        }

        return $usersById;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function createAgencyIndex(): array
    {
        $agencies = [];

        foreach ($this->legacyDirectoryOptionsService->getAgenceOptions() as $option) {
            $snapshot = $this->normalizeAgencySnapshot(
                trim((string) ($option['departement'] ?? '')),
                trim((string) ($option['agence'] ?? ''))
            );

            if ($snapshot === null) {
                continue;
            }

            $agencies[(string) $snapshot['id']] = $this->createAgencyOverviewRow($snapshot);
        }

        foreach ($this->buildUsersById() as $snapshot) {
            $agencies[(string) $snapshot['id']] ??= $this->createAgencyOverviewRow($snapshot);
        }

        return $agencies;
    }

    /**
     * @param array{id:string, departement:string, agence:string, label:string} $agency
     * @return array<string, mixed>
     */
    private function createAgencyOverviewRow(array $agency): array
    {
        return [
            'id' => $agency['id'],
            'departement' => $agency['departement'],
            'agence' => $agency['agence'],
            'label' => $agency['label'],
            'entry_count' => 0,
            'fond_debut' => 0.0,
            'fond_fin' => 0.0,
            'is_closed' => false,
            'closed_at' => '',
            'daily_state_id' => 0,
            'has_activity' => false,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, array{id:string, departement:string, agence:string, label:string}> $usersById
     * @return array{id:string, departement:string, agence:string, label:string}|null
     */
    private function resolveAgencyFromRow(array $row, array $usersById): ?array
    {
        $rowSnapshot = $this->normalizeAgencySnapshot(
            trim((string) ($row['departement'] ?? '')),
            trim((string) ($row['agence'] ?? ''))
        );
        if ($rowSnapshot !== null) {
            return $rowSnapshot;
        }

        foreach ($this->resolveCandidateUserIds($row) as $userId) {
            if ($userId !== '' && isset($usersById[$userId])) {
                return $usersById[$userId];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     * @return string[]
     */
    private function resolveCandidateUserIds(array $row): array
    {
        $recordType = trim((string) ($row['record_type'] ?? ''));
        if ($recordType === 'daily_state') {
            return array_values(array_filter([
                trim((string) ($row['journee_cloturee_by'] ?? '')),
                trim((string) ($row['updated_by'] ?? '')),
                trim((string) ($row['created_by'] ?? '')),
            ]));
        }

        return array_values(array_filter([
            trim((string) ($row['updated_by'] ?? '')),
            trim((string) ($row['created_by'] ?? '')),
        ]));
    }

    /**
     * @return array{id:string, departement:string, agence:string, label:string}|null
     */
    private function normalizeAgencySnapshot(string $departement, string $agence): ?array
    {
        $departement = trim($departement);
        $agence = trim($agence);
        if ($departement === '' && $agence === '') {
            return null;
        }

        return [
            'id' => $this->buildAgencyId($departement, $agence),
            'departement' => $departement,
            'agence' => $agence,
            'label' => trim($agence . ($departement !== '' ? ' (' . $departement . ')' : '')),
        ];
    }

    private function buildAgencyId(string $departement, string $agence): string
    {
        return trim($departement . '_' . $agence, '_');
    }

    /**
     * @param array<string, mixed> $agency
     */
    private function resolveStatus(array $agency): string
    {
        if (!empty($agency['is_closed'])) {
            return 'closed';
        }

        if (!empty($agency['has_activity']) || (int) ($agency['entry_count'] ?? 0) > 0) {
            return 'in_progress';
        }

        return 'empty';
    }
}
