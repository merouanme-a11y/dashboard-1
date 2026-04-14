<?php

namespace App\Service;

use App\Entity\Module;
use App\Repository\ModuleRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class QuittancementGeneratorService
{
    public const MODULE_NAME = 'quittancement_generator';
    public const ROUTE_NAME = 'app_quittancement_generator';
    public const APP_VERSION = '1.0.0';

    private const RISKS = [
        ['id' => 4, 'name' => 'Assistance Individuel'],
        ['id' => 14, 'name' => 'Automobile (A1)'],
        ['id' => 13, 'name' => 'Multirisque Habitation'],
        ['id' => 11, 'name' => 'Prévoyance Individuelle'],
        ['id' => 1, 'name' => 'Santé'],
        ['id' => 2, 'name' => 'Santé Collectif (gestion individuelle)'],
        ['id' => 12, 'name' => 'Santé Collective'],
        ['id' => 10, 'name' => 'Vie/Obsèques'],
    ];

    private const MONTH_NAMES = [
        1 => 'Janvier',
        2 => 'Fevrier',
        3 => 'Mars',
        4 => 'Avril',
        5 => 'Mai',
        6 => 'Juin',
        7 => 'Juillet',
        8 => 'Aout',
        9 => 'Septembre',
        10 => 'Octobre',
        11 => 'Novembre',
        12 => 'Decembre',
    ];

    private const SQL_PLANIFICATION_CIE_ID = 7;
    private const SQL_PLANIFICATION_USER_ID = 1;
    private const SQL_NOTIFICATION_RECIPIENTS = 'exploitation@adep.com,a.sanchez@adep.com,m.massol@adep.com,m.hamzaoui@adep.com,s.at@adep.com';

    public function __construct(
        private ModuleRepository $moduleRepository,
        private EntityManagerInterface $em,
    ) {}

    public function ensureModuleExists(): Module
    {
        $module = $this->moduleRepository->findByName(self::MODULE_NAME);
        if ($module instanceof Module) {
            return $module;
        }

        $maxSortOrder = 0;
        foreach ($this->moduleRepository->findAllSorted() as $existingModule) {
            $maxSortOrder = max($maxSortOrder, (int) $existingModule->getSortOrder());
        }

        $module = (new Module())
            ->setName(self::MODULE_NAME)
            ->setLabel('Generateur de quittancements')
            ->setIcon('bi-calendar2-check')
            ->setRouteName(self::ROUTE_NAME)
            ->setIsActive(true)
            ->setSortOrder($maxSortOrder + 10);

        $this->em->persist($module);
        $this->em->flush();

        return $module;
    }

    public function buildPageData(array $requestData): array
    {
        $currentYear = (int) date('Y');
        $availableYears = [$currentYear, $currentYear + 1];

        $year = (int) ($requestData['year'] ?? $currentYear);
        if (!in_array($year, $availableYears, true)) {
            $year = $currentYear;
        }

        $month = (int) ($requestData['month'] ?? (int) date('n'));
        $month = max(1, min(12, $month));

        $dateOverrides = $this->getDateOverridesFromRequest($requestData);
        $emailOverrides = $this->getEmailOverridesFromRequest($requestData);

        $dates = $this->applyDateOverrides(
            $this->calculateDates($year, $month),
            $dateOverrides
        );

        $defaultEmailData = $this->generateEmailData($dates);
        $emailData = $this->applyEmailOverrides($defaultEmailData, $emailOverrides);
        $sqlSections = $this->getSqlQuerySections($dates);

        $jsDates = [];
        foreach ($dates as $key => $dateData) {
            $jsDates[$key] = [
                'date_formatted' => $dateData['date_formatted'],
                'date_only' => $dateData['date_only'],
                'time' => $dateData['time'],
            ];
        }

        return [
            'appVersion' => self::APP_VERSION,
            'year' => $year,
            'month' => $month,
            'availableYears' => $availableYears,
            'monthNames' => self::MONTH_NAMES,
            'dateOverrides' => $dateOverrides,
            'emailCustomized' => $this->isEmailCustomized($emailOverrides),
            'dates' => $dates,
            'jsDates' => $jsDates,
            'defaultEmailData' => $defaultEmailData,
            'emailData' => $emailData,
            'sqlSections' => $sqlSections,
        ];
    }

    /**
     * @return array{d3:string,d15:string,d25:string,d27:string}
     */
    public function getDateOverridesFromRequest(array $source): array
    {
        return [
            'd3' => $this->normalizeDateInput($source['d3_date'] ?? ''),
            'd15' => $this->normalizeDateInput($source['d15_date'] ?? ''),
            'd25' => $this->normalizeDateInput($source['d25_date'] ?? ''),
            'd27' => $this->normalizeDateInput($source['d27_date'] ?? ''),
        ];
    }

    /**
     * @return array{to:string,cc:string,subject:string,body:string,customized:string}
     */
    public function getEmailOverridesFromRequest(array $source): array
    {
        return [
            'to' => trim((string) ($source['email_to'] ?? '')),
            'cc' => trim((string) ($source['email_cc'] ?? '')),
            'subject' => (string) ($source['email_subject'] ?? ''),
            'body' => (string) ($source['email_body'] ?? ''),
            'customized' => (string) ($source['email_customized'] ?? '0'),
        ];
    }

    public function isEmailCustomized(array $emailOverrides): bool
    {
        return ($emailOverrides['customized'] ?? '0') === '1';
    }

    /**
     * @return array<string, array{date:DateTimeImmutable,date_formatted:string,date_only:string,time:string}>
     */
    public function calculateDates(int $year, int $month): array
    {
        $d3 = $this->getNthFridayOfMonth($year, $month, 3);
        $d15 = $d3->modify('+7 days');
        $d25 = $this->getD25Date($year, $month);
        $d27 = $d25->modify('-2 days');

        return [
            'd3' => $this->createDateEntry($d3, '23h00'),
            'd15' => $this->createDateEntry($d15, '23h00'),
            'd25' => $this->createDateEntry($d25, $this->getCutoffTime($d25)),
            'd27' => $this->createDateEntry($d27, $this->getCutoffTime($d27)),
        ];
    }

    /**
     * @param array<string, array{date:DateTimeImmutable,date_formatted:string,date_only:string,time:string}> $dates
     * @param array{d3:string,d15:string,d25:string,d27:string} $overrides
     *
     * @return array<string, array{date:DateTimeImmutable,date_formatted:string,date_only:string,time:string}>
     */
    public function applyDateOverrides(array $dates, array $overrides): array
    {
        if ($this->isValidDateInput($overrides['d3'])) {
            $d3Date = new DateTimeImmutable($overrides['d3']);
            $dates['d3'] = $this->createDateEntry($d3Date, '23h00');

            if (!$this->isValidDateInput($overrides['d15'])) {
                $dates['d15'] = $this->createDateEntry($d3Date->modify('+7 days'), '23h00');
            }
        }

        if ($this->isValidDateInput($overrides['d15'])) {
            $dates['d15'] = $this->createDateEntry(new DateTimeImmutable($overrides['d15']), '23h00');
        }

        if ($this->isValidDateInput($overrides['d25'])) {
            $d25Date = new DateTimeImmutable($overrides['d25']);
            $dates['d25'] = $this->createDateEntry($d25Date, $this->getCutoffTime($d25Date));

            if (!$this->isValidDateInput($overrides['d27'])) {
                $d27Date = $d25Date->modify('-2 days');
                $dates['d27'] = $this->createDateEntry($d27Date, $this->getCutoffTime($d27Date));
            }
        }

        if ($this->isValidDateInput($overrides['d27'])) {
            $d27Date = new DateTimeImmutable($overrides['d27']);
            $dates['d27'] = $this->createDateEntry($d27Date, $this->getCutoffTime($d27Date));
        }

        return $dates;
    }

    /**
     * @param array<string, array{date:DateTimeImmutable,date_formatted:string,date_only:string,time:string}> $dates
     *
     * @return array{to:string,cc:string,subject:string,body:string}
     */
    public function generateEmailData(array $dates): array
    {
        $to = 'marjorie.massol@adep.com;AURELIE.SANCHEZ@adep.com;E.GONZALES@adep.com;jm.garcia@adep.com;relationclients@adep.com;Marion.Garcia@adep.com;alice.tabarie@adep.com';
        $cc = 'm.hamzaoui@adep.com;s.at@adep.com';

        $d3 = $dates['d3']['date'];
        $d15 = $dates['d15']['date'];
        $d25 = $dates['d25']['date'];
        $d27 = $dates['d27']['date'];
        $historicRange = $this->getHistoricPlanificationRange($d3);
        $forwardRange = $this->getForwardPlanificationRange($d25);

        $planningMonth = $this->getMonthNameFr((int) $d15->format('n'));
        $subject = sprintf(
            'Planification quittancement + ajustement sur le mois de %s %s',
            $planningMonth,
            $d15->format('Y')
        );

        $body = <<<TEXT
Bonjour,

Le rattrapage des quittancements des mois de {$this->getMonthNameFr($historicRange['start_month'])} {$historicRange['start_year']} au mois de {$this->getMonthNameFr($historicRange['end_month'])} {$historicRange['end_year']} aura lieu le {$d3->format('d/m/Y')} à partir de {$this->formatEmailTime($dates['d3']['time'])} pour l'ensemble des risques.
Le quittancement du mois de {$this->getMonthNameFr($forwardRange['end_month'])} {$forwardRange['end_year']} se fera le {$d15->format('d/m/Y')} à partir de {$this->formatEmailTime($dates['d15']['time'])} pour tous les risques sauf santé collective avec un rattrapage depuis le mois de {$this->getMonthNameFr($forwardRange['start_month'])} {$forwardRange['start_year']}.
Pour le risque Santé Collective spécifiquement, les ajustements seront lancés le {$d27->format('d/m/Y')} à {$this->formatEmailTime($dates['d27']['time'])}
Le quittancement de ce même risque pour le mois de {$this->getMonthNameFr($forwardRange['end_month'])} {$forwardRange['end_year']} sera lancé le {$d25->format('d/m/Y')} à {$this->formatEmailTime($dates['d25']['time'])} avec un rattrapage depuis le mois de {$this->getMonthNameFr($forwardRange['start_month'])} {$forwardRange['start_year']}.
Pour rappel, le traitement d'ajustement est aussi exécuté tous les 14 du mois.

Bonne réception
Le service IT
TEXT;

        return [
            'to' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * @param array{to:string,cc:string,subject:string,body:string} $emailData
     * @param array{to:string,cc:string,subject:string,body:string,customized:string} $emailOverrides
     *
     * @return array{to:string,cc:string,subject:string,body:string}
     */
    public function applyEmailOverrides(array $emailData, array $emailOverrides): array
    {
        if (!$this->isEmailCustomized($emailOverrides)) {
            return $emailData;
        }

        return [
            'to' => $emailOverrides['to'],
            'cc' => $emailOverrides['cc'],
            'subject' => $emailOverrides['subject'],
            'body' => $emailOverrides['body'],
        ];
    }

    /**
     * @param array<string, array{date:DateTimeImmutable,date_formatted:string,date_only:string,time:string}> $dates
     *
     * @return array{rattrapage:string,quittancement:string,sante_collective:string}
     */
    public function getSqlQuerySections(array $dates): array
    {
        $allRisks = self::RISKS;
        $healthRisk = $this->getHealthCollectiveRisk();
        $historicRange = $this->getHistoricPlanificationRange($dates['d3']['date']);
        $forwardRange = $this->getForwardPlanificationRange($dates['d25']['date']);
        $currentMonthRisks = array_values(array_filter(
            self::RISKS,
            static fn (array $risk): bool => (int) ($risk['id'] ?? 0) !== 12
        ));

        return [
            'rattrapage' => $this->buildPlanificationChainSql(
                'quittancement',
                $allRisks,
                $historicRange,
                $this->formatSqlLaunchTimestamp($dates['d3'])
            ),
            'quittancement' => $this->buildPlanificationChainSql(
                'quittancement',
                $currentMonthRisks,
                $forwardRange,
                $this->formatSqlLaunchTimestamp($dates['d15'])
            ),
            'sante_collective' => $this->buildStandalonePlanificationSql(
                $healthRisk,
                $forwardRange,
                $this->formatSqlLaunchTimestamp($dates['d25']),
                $this->buildStandaloneHealthCollectiveName($healthRisk, $forwardRange)
            ),
        ];
    }

    /**
     * @param array<string, array{date:DateTimeImmutable,date_formatted:string,date_only:string,time:string}> $dates
     */
    public function generateSqlQueries(array $dates): string
    {
        return implode("\n\n", $this->getSqlQuerySections($dates));
    }

    /**
     * @param array<string, array{date:DateTimeImmutable,date_formatted:string,date_only:string,time:string}> $dates
     */
    public function generateSqlFile(array $dates): string
    {
        $sql = "-- =====================================================\n";
        $sql .= "-- Quittancements - Generation automatique\n";
        $sql .= '-- Generees le: ' . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- =====================================================\n\n";
        $sql .= $this->generateSqlQueries($dates);
        $sql .= "\n\n-- =====================================================\n";
        $sql .= "-- FIN DES REQUETES\n";
        $sql .= "-- =====================================================\n";

        return $sql;
    }

    private function getNthFridayOfMonth(int $year, int $month, int $occurrence): DateTimeImmutable
    {
        $date = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));

        while ((int) $date->format('w') !== 5) {
            $date = $date->modify('+1 day');
        }

        if ($occurrence > 1) {
            $date = $date->modify('+' . (7 * ($occurrence - 1)) . ' days');
        }

        return $date;
    }

    private function getD25Date(int $year, int $month): DateTimeImmutable
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $day = max(1, $daysInMonth - 1);

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    private function isValidDateInput(mixed $dateString): bool
    {
        if (!is_string($dateString) || trim($dateString) === '') {
            return false;
        }

        $normalized = trim($dateString);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $normalized;
    }

    /**
     * @return array{date:DateTimeImmutable,date_formatted:string,date_only:string,time:string}
     */
    private function createDateEntry(DateTimeImmutable $date, string $time): array
    {
        return [
            'date' => $date,
            'date_formatted' => $this->formatDateFr($date),
            'date_only' => $date->format('Y-m-d'),
            'time' => $time,
        ];
    }

    private function formatDateFr(DateTimeImmutable $date): string
    {
        $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

        return $date->format('d/m/Y') . ' (' . $days[(int) $date->format('w')] . ')';
    }

    private function getMonthNameFr(int $month): string
    {
        $months = [
            1 => 'janvier',
            2 => 'février',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'août',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'décembre',
        ];

        return $months[$month] ?? 'mois';
    }

    private function formatEmailTime(string $time): string
    {
        $normalized = trim($time);
        if ($normalized === '') {
            return '';
        }

        return str_replace('h', ':', $normalized);
    }

    private function getCutoffTime(DateTimeImmutable $date): string
    {
        return in_array((int) $date->format('w'), [0, 6], true) ? '12h00' : '23h00';
    }

    /**
     * @return array{start_month:int,start_year:int,end_month:int,end_year:int}
     */
    private function getHistoricPlanificationRange(DateTimeImmutable $referenceDate, int $monthsBack = 2): array
    {
        $startDate = $referenceDate->modify('-' . $monthsBack . ' months');

        return [
            'start_month' => (int) $startDate->format('n'),
            'start_year' => (int) $startDate->format('Y'),
            'end_month' => (int) $referenceDate->format('n'),
            'end_year' => (int) $referenceDate->format('Y'),
        ];
    }

    /**
     * @return array{start_month:int,start_year:int,end_month:int,end_year:int}
     */
    private function getForwardPlanificationRange(DateTimeImmutable $referenceDate): array
    {
        $endDate = $referenceDate->modify('+1 month');

        return [
            'start_month' => (int) $referenceDate->format('n'),
            'start_year' => (int) $referenceDate->format('Y'),
            'end_month' => (int) $endDate->format('n'),
            'end_year' => (int) $endDate->format('Y'),
        ];
    }

    /**
     * @param array{date:DateTimeImmutable,date_formatted:string,date_only:string,time:string} $dateData
     */
    private function formatSqlLaunchTimestamp(array $dateData): string
    {
        $time = str_contains($dateData['time'], '23') ? '23:00:00' : '12:00:00';

        return $dateData['date_only'] . ' ' . $time;
    }

    private function buildLaunchValue(?string $timestamp): string
    {
        if ($timestamp === null || $timestamp === '') {
            return 'null';
        }

        return "'" . $timestamp . "'";
    }

    private function escapeSqlString(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /**
     * @param array{id:int,name:string} $risk
     * @param array{start_month:int,start_year:int,end_month:int,end_year:int} $range
     */
    private function buildPlanificationName(int $index, int $total, array $risk, array $range): string
    {
        return sprintf(
            'Risque %d/%d - [%s] - %04d/%02d au %04d/%02d',
            $index,
            $total,
            $risk['name'],
            $range['start_year'],
            $range['start_month'],
            $range['end_year'],
            $range['end_month']
        );
    }

    /**
     * @param array{id:int,name:string} $risk
     * @param array{start_month:int,start_year:int,end_month:int,end_year:int} $range
     */
    private function buildPlanificationInsertSql(
        array $risk,
        array $range,
        string $launchValue,
        string $parentValue,
        ?int $index,
        ?int $total,
        bool $withReturning = false,
        bool $valuesOnNextLine = false,
        ?string $customName = null,
    ): string {
        $resolvedName = $customName ?? $this->buildPlanificationName((int) $index, (int) $total, $risk, $range);
        $name = $this->escapeSqlString($resolvedName);
        $valuesSeparator = $valuesOnNextLine ? "\n" : ' ';

        $sql = sprintf(
            "insert into oa_requittancement_planification (create_uid, create_date, write_uid, write_date, risque_id, name, cie_id, date_lancement_planifie, mois, annee, mois_fin, annee_fin, notification_on, notification_termine_on, notification_calcul_fait_on, notification_destinataires, state_requittancement_planification, compensation_auto_on, planification_parent_id) values%s(%d,now(),%d,now(),%d,'%s',%d,%s,%d,%d,%d,%d,True,True,True,'%s','valide', True, %s)",
            $valuesSeparator,
            self::SQL_PLANIFICATION_USER_ID,
            self::SQL_PLANIFICATION_USER_ID,
            $risk['id'],
            $name,
            self::SQL_PLANIFICATION_CIE_ID,
            $launchValue,
            $range['start_month'],
            $range['start_year'],
            $range['end_month'],
            $range['end_year'],
            self::SQL_NOTIFICATION_RECIPIENTS,
            $parentValue
        );

        if ($withReturning) {
            $sql .= ' returning id ';
        }

        return $sql;
    }

    /**
     * @param list<array{id:int,name:string}> $risks
     * @param array{start_month:int,start_year:int,end_month:int,end_year:int} $range
     */
    private function buildPlanificationChainSql(string $prefix, array $risks, array $range, string $launchTimestamp): string
    {
        $total = count($risks);
        if ($total === 0) {
            return '';
        }

        if ($total === 1) {
            return $this->buildPlanificationInsertSql(
                $risks[0],
                $range,
                $this->buildLaunchValue($launchTimestamp),
                'null',
                1,
                1
            ) . "\n;";
        }

        $lines = [];
        $lines[] = 'with ' . $prefix . '_1 as (';
        $lines[] = $this->buildPlanificationInsertSql(
            $risks[0],
            $range,
            $this->buildLaunchValue($launchTimestamp),
            'null',
            1,
            $total,
            true,
            true
        );
        $lines[] = $total > 2 ? ') ,' : ') ';

        for ($position = 2; $position < $total; $position++) {
            $lines[] = $prefix . '_' . $position . ' as ( ' . $this->buildPlanificationInsertSql(
                $risks[$position - 1],
                $range,
                'null',
                '(select id from ' . $prefix . '_' . ($position - 1) . ')',
                $position,
                $total,
                true
            ) . ($position < $total - 1 ? '), ' : ') ');
        }

        $lines[] = $this->buildPlanificationInsertSql(
            $risks[$total - 1],
            $range,
            'null',
            '(select id from ' . $prefix . '_' . ($total - 1) . ')',
            $total,
            $total
        );
        $lines[] = ';';

        return implode("\n", $lines);
    }

    /**
     * @param array{id:int,name:string} $risk
     * @param array{start_month:int,start_year:int,end_month:int,end_year:int} $range
     */
    private function buildStandalonePlanificationSql(
        array $risk,
        array $range,
        string $launchTimestamp,
        ?string $customName = null,
    ): string {
        return $this->buildPlanificationInsertSql(
            $risk,
            $range,
            $this->buildLaunchValue($launchTimestamp),
            'null',
            null,
            null,
            false,
            false,
            $customName
        ) . "\n;";
    }

    /**
     * @return array{id:int,name:string}
     */
    private function getHealthCollectiveRisk(): array
    {
        foreach (self::RISKS as $risk) {
            if ((int) ($risk['id'] ?? 0) === 12) {
                return $risk;
            }
        }

        return self::RISKS[0];
    }

    /**
     * @param array{id:int,name:string} $risk
     * @param array{start_month:int,start_year:int,end_month:int,end_year:int} $range
     */
    private function buildStandaloneHealthCollectiveName(array $risk, array $range): string
    {
        return sprintf(
            'Risque [%s] - %04d/%02d au %04d/%02d',
            $risk['name'],
            $range['start_year'],
            $range['start_month'],
            $range['end_year'],
            $range['end_month']
        );
    }

    private function normalizeDateInput(mixed $value): string
    {
        $normalized = trim((string) $value);

        return $this->isValidDateInput($normalized) ? $normalized : '';
    }
}
