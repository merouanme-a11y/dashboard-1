<?php

namespace App\Service;

use App\Entity\Module;
use App\Entity\UserPagePreference;
use App\Entity\Utilisateur;
use App\Repository\ModuleRepository;
use App\Repository\UserPagePreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

class BIConfigurationService
{
    public const PAGE_KEY = 'bi';
    public const MODULE_NAME = 'bi';
    private const WIDGET_TEXT_SIZE_MIN = 0;
    private const WIDGET_TEXT_SIZE_MAX = 150;
    private const WIDGET_TEXT_SIZE_DEFAULT = 15;
    private const WIDGET_VALUE_SIZE_MIN = 0;
    private const WIDGET_VALUE_SIZE_MAX = 150;
    private const WIDGET_VALUE_SIZE_DEFAULT = 48;
    private const MAX_PAGES = 12;
    private const MAX_WIDGETS = 32;
    private const MAX_FILTERS = 8;
    private const MAX_WIDGET_FILTERS = 5;
    private const MAX_WIDGET_DIMENSIONS = 1;
    private const MAX_WIDGET_MEASURES = 1;
    private const MAX_ALLOWED_USERS = 25;
    private const MAX_ALLOWED_PROFILES = 10;
    private const ALLOWED_FRACTIONS = [
        '1/8',
        '2/8',
        '3/8',
        '4/8',
        '5/8',
        '6/8',
        '7/8',
        '8/8',
    ];
    private const ALLOWED_WIDGET_TYPES = [
        'bar',
        'bar-horizontal',
        'line',
        'pie',
        'doughnut',
        'kpi',
        'percentage',
        'distribution-table',
        'datatable',
        'table',
        'histogram',
        'counter',
    ];
    private const ALLOWED_AGGREGATIONS = ['count', 'sum', 'avg', 'percentage'];
    private const ALLOWED_ALIGNMENTS = ['left', 'center', 'right'];
    private const ALLOWED_SORT_DIRECTIONS = ['asc', 'desc'];
    private const ALLOWED_FILTER_OPERATORS = ['equals', 'contains'];
    private const ALLOWED_FILTER_INPUT_MODES = ['select', 'input'];
    private const ALLOWED_FILTER_STYLE_TARGETS = ['none', 'row', 'cell'];

    public function __construct(
        private UserPagePreferenceRepository $preferenceRepository,
        private ModuleRepository $moduleRepository,
        private EntityManagerInterface $em,
        private BIModuleSettingsService $biModuleSettingsService,
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
            ->setLabel('Business Intelligence')
            ->setIcon('bi-graph-up-arrow')
            ->setRouteName('app_bi')
            ->setIsActive(true)
            ->setSortOrder($maxSortOrder + 10);

        $this->em->persist($module);
        $this->em->flush();

        return $module;
    }

    public function getForUser(Utilisateur $user): array
    {
        $this->ensureModuleExists();

        $sharedPages = $this->getSharedPages();
        $visiblePages = [];

        foreach ($sharedPages as $page) {
            if (!$this->canViewPage($user, $page)) {
                continue;
            }

            $page['canEdit'] = $this->canEditPage($user, $page);
            $page['canManagePermissions'] = $this->canManagePagePermissions($user, $page);
            $visiblePages[] = $page;
        }

        if ($visiblePages === []) {
            $visiblePages[] = $this->createPlaceholderPage();
        }

        $state = $this->getUserState($user);
        $selectedPageId = $this->normalizeScalar($state['selectedPageId'] ?? '', 80);
        $pageIds = array_column($visiblePages, 'id');
        if ($selectedPageId === '' || !in_array($selectedPageId, $pageIds, true)) {
            $selectedPageId = (string) ($visiblePages[0]['id'] ?? 'page-bi-placeholder');
        }

        $selectedPage = $visiblePages[0];
        foreach ($visiblePages as $page) {
            if ((string) ($page['id'] ?? '') === $selectedPageId) {
                $selectedPage = $page;
                break;
            }
        }

        return [
            'defaultConnection' => (string) ($selectedPage['connectionId'] ?? ''),
            'defaultFile' => (string) ($selectedPage['fileId'] ?? ''),
            'selectedPageId' => $selectedPageId,
            'pages' => $visiblePages,
            'canCreatePages' => $this->canCreatePages($user),
            'canManageSettings' => $this->isAdmin($user),
        ];
    }

    public function saveForUser(Utilisateur $user, array $preferences): array
    {
        $this->ensureModuleExists();

        $sharedPages = $this->getSharedPages();
        $incomingPages = $this->normalizeIncomingPages($preferences['pages'] ?? []);
        $incomingById = [];
        foreach ($incomingPages as $page) {
            $incomingById[(string) $page['id']] = $page;
        }

        $canCreatePages = $this->canCreatePages($user);
        $mergedPages = [];

        foreach ($sharedPages as $existingPage) {
            $pageId = (string) ($existingPage['id'] ?? '');
            if ($pageId === '') {
                continue;
            }

            if (!array_key_exists($pageId, $incomingById)) {
                if ($this->canEditPage($user, $existingPage)) {
                    continue;
                }

                $mergedPages[] = $existingPage;
                continue;
            }

            if (!$this->canEditPage($user, $existingPage)) {
                $mergedPages[] = $existingPage;
                unset($incomingById[$pageId]);
                continue;
            }

            $mergedPages[] = $this->mergeEditablePage($existingPage, $incomingById[$pageId]);
            unset($incomingById[$pageId]);
        }

        foreach ($incomingById as $incomingPage) {
            if (!$canCreatePages) {
                throw new \DomainException('Vous n avez pas les droits pour creer une page BI.');
            }

            $mergedPages[] = $this->initializeOwnedPage($incomingPage, $user);
        }

        $this->biModuleSettingsService->saveSharedPages($mergedPages);
        $this->saveUserState($user, [
            'selectedPageId' => $this->normalizeScalar($preferences['selectedPageId'] ?? '', 80),
        ]);

        return $this->getForUser($user);
    }

    public function updatePageVisibility(Utilisateur $user, string $pageId, array $userIds, array $profileTypes): array
    {
        $this->ensureModuleExists();

        $sharedPages = $this->getSharedPages();
        $updated = [];
        $found = false;

        foreach ($sharedPages as $page) {
            if ((string) ($page['id'] ?? '') !== trim($pageId)) {
                $updated[] = $page;
                continue;
            }

            if (!$this->canManagePagePermissions($user, $page)) {
                throw new \DomainException('Vous n avez pas les droits pour gerer la visibilite de cette page BI.');
            }

            $page['allowedUserIds'] = $this->normalizeIntegerCollection($userIds, self::MAX_ALLOWED_USERS);
            $page['allowedProfileTypes'] = $this->normalizeStringCollection($profileTypes, 80, self::MAX_ALLOWED_PROFILES);
            $updated[] = $page;
            $found = true;
        }

        if (!$found) {
            throw new \InvalidArgumentException('Page BI introuvable.');
        }

        $this->biModuleSettingsService->saveSharedPages($updated);

        return $this->getForUser($user);
    }

    public function canCreatePages(Utilisateur $user): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $permissions = $this->biModuleSettingsService->getPageCreationPermissions();
        $allowedUserIds = $this->normalizeIntegerCollection($permissions['userIds'] ?? [], self::MAX_ALLOWED_USERS);
        if (in_array((int) ($user->getId() ?? 0), $allowedUserIds, true)) {
            return true;
        }

        $allowedProfiles = array_map('mb_strtolower', $this->normalizeStringCollection($permissions['profileTypes'] ?? [], 80, self::MAX_ALLOWED_PROFILES));

        return in_array(mb_strtolower($user->getEffectiveProfileType()), $allowedProfiles, true);
    }

    private function getSharedPages(): array
    {
        $rawPages = $this->biModuleSettingsService->getSharedPages();
        $normalizedPages = [];
        $knownIds = [];

        foreach ($rawPages as $rawPage) {
            if (!is_array($rawPage)) {
                continue;
            }

            $page = $this->normalizePage($rawPage);
            if ($page === null) {
                continue;
            }

            $page['id'] = $this->ensureUniquePageId($page['id'], $knownIds);
            $knownIds[$page['id']] = true;
            $normalizedPages[] = $page;
            if (count($normalizedPages) >= self::MAX_PAGES) {
                break;
            }
        }

        if ($normalizedPages !== []) {
            return $normalizedPages;
        }

        $migratedPages = $this->migrateLegacyPages();
        if ($migratedPages !== []) {
            return $migratedPages;
        }

        return [];
    }

    private function migrateLegacyPages(): array
    {
        $preferences = $this->preferenceRepository->findBy(['pageKey' => self::PAGE_KEY]);
        $normalizedPages = [];
        $knownIds = [];

        foreach ($preferences as $preference) {
            if (!$preference instanceof UserPagePreference) {
                continue;
            }

            $owner = $preference->getUtilisateur();
            if (!$owner instanceof Utilisateur) {
                continue;
            }

            $payload = $preference->getPreferencePayload();
            foreach ((array) ($payload['pages'] ?? []) as $rawPage) {
                if (!is_array($rawPage)) {
                    continue;
                }

                $page = $this->normalizePage($rawPage, $owner);
                if ($page === null) {
                    continue;
                }

                $page['id'] = $this->ensureUniquePageId($page['id'], $knownIds);
                $knownIds[$page['id']] = true;
                $normalizedPages[] = $page;
                if (count($normalizedPages) >= self::MAX_PAGES) {
                    break 2;
                }
            }
        }

        if ($normalizedPages !== []) {
            $this->biModuleSettingsService->saveSharedPages($normalizedPages);
        }

        return $normalizedPages;
    }

    private function normalizeIncomingPages(mixed $pages): array
    {
        $normalized = [];

        foreach (is_array($pages) ? $pages : [] as $rawPage) {
            if (!is_array($rawPage)) {
                continue;
            }

            $page = $this->normalizePage($rawPage);
            if ($page === null || !empty($page['isPlaceholder'])) {
                continue;
            }

            $normalized[] = $page;
            if (count($normalized) >= self::MAX_PAGES) {
                break;
            }
        }

        return $normalized;
    }

    private function mergeEditablePage(array $existingPage, array $incomingPage): array
    {
        return [
            'id' => (string) $existingPage['id'],
            'name' => (string) $incomingPage['name'],
            'connectionId' => (string) $incomingPage['connectionId'],
            'fileId' => (string) $incomingPage['fileId'],
            'filters' => $incomingPage['filters'],
            'widgets' => $incomingPage['widgets'],
            'ownerUserId' => (int) ($existingPage['ownerUserId'] ?? 0),
            'ownerEmail' => (string) ($existingPage['ownerEmail'] ?? ''),
            'ownerDisplayName' => (string) ($existingPage['ownerDisplayName'] ?? ''),
            'allowedUserIds' => $this->normalizeIntegerCollection($existingPage['allowedUserIds'] ?? [], self::MAX_ALLOWED_USERS),
            'allowedProfileTypes' => $this->normalizeStringCollection($existingPage['allowedProfileTypes'] ?? [], 80, self::MAX_ALLOWED_PROFILES),
        ];
    }

    private function initializeOwnedPage(array $page, Utilisateur $user): array
    {
        $page['ownerUserId'] = (int) ($user->getId() ?? 0);
        $page['ownerEmail'] = mb_strtolower(trim((string) ($user->getEmail() ?? '')));
        $page['ownerDisplayName'] = $this->formatUserDisplayName($user);
        $page['allowedUserIds'] = $this->normalizeIntegerCollection($page['allowedUserIds'] ?? [], self::MAX_ALLOWED_USERS);
        $page['allowedProfileTypes'] = $this->normalizeStringCollection($page['allowedProfileTypes'] ?? [], 80, self::MAX_ALLOWED_PROFILES);

        return $page;
    }

    private function getUserState(Utilisateur $user): array
    {
        $preference = $this->preferenceRepository->findOneForUserAndPage($user, self::PAGE_KEY);
        $payload = $preference?->getPreferencePayload() ?? [];

        return [
            'selectedPageId' => $this->normalizeScalar($payload['selectedPageId'] ?? '', 80),
        ];
    }

    private function saveUserState(Utilisateur $user, array $state): void
    {
        $preference = $this->preferenceRepository->findOneForUserAndPage($user, self::PAGE_KEY);
        if (!$preference instanceof UserPagePreference) {
            $preference = (new UserPagePreference())
                ->setUtilisateur($user)
                ->setPageKey(self::PAGE_KEY);
            $this->em->persist($preference);
        }

        $preference->setPreferencePayload([
            'selectedPageId' => $this->normalizeScalar($state['selectedPageId'] ?? '', 80),
        ]);
        $this->em->flush();
    }

    private function normalizePage(array $page, ?Utilisateur $owner = null): ?array
    {
        $id = $this->normalizeScalar($page['id'] ?? '', 80);
        $name = $this->normalizeScalar($page['name'] ?? '', 120);
        if ($id === '') {
            return null;
        }

        $normalized = [
            'id' => $id,
            'name' => $name !== '' ? $name : 'Page BI',
            'connectionId' => $this->normalizeScalar($page['connectionId'] ?? '', 120),
            'fileId' => $this->normalizeScalar($page['fileId'] ?? '', 255),
            'filters' => [],
            'widgets' => [],
            'ownerUserId' => (int) ($page['ownerUserId'] ?? ($owner?->getId() ?? 0)),
            'ownerEmail' => mb_strtolower($this->normalizeScalar($page['ownerEmail'] ?? ($owner?->getEmail() ?? ''), 180)),
            'ownerDisplayName' => $this->normalizeScalar($page['ownerDisplayName'] ?? ($owner ? $this->formatUserDisplayName($owner) : ''), 180),
            'allowedUserIds' => $this->normalizeIntegerCollection($page['allowedUserIds'] ?? [], self::MAX_ALLOWED_USERS),
            'allowedProfileTypes' => $this->normalizeStringCollection($page['allowedProfileTypes'] ?? [], 80, self::MAX_ALLOWED_PROFILES),
        ];

        $rawFilters = is_array($page['filters'] ?? null) ? $page['filters'] : [];
        foreach ($rawFilters as $rawFilter) {
            if (!is_array($rawFilter)) {
                continue;
            }

            $column = $this->normalizeScalar($rawFilter['column'] ?? '', 120);
            $value = $this->normalizeScalar($rawFilter['value'] ?? '', 120);
            if ($column === '' || $value === '') {
                continue;
            }

            $normalized['filters'][] = [
                'column' => $column,
                'value' => $value,
            ];

            if (count($normalized['filters']) >= self::MAX_FILTERS) {
                break;
            }
        }

        $rawWidgets = is_array($page['widgets'] ?? null) ? $page['widgets'] : [];
        foreach ($rawWidgets as $rawWidget) {
            if (!is_array($rawWidget)) {
                continue;
            }

            $widget = $this->normalizeWidget($rawWidget);
            if ($widget === null) {
                continue;
            }

            $normalized['widgets'][] = $widget;
            if (count($normalized['widgets']) >= self::MAX_WIDGETS) {
                break;
            }
        }

        return $normalized;
    }

    private function normalizeWidget(array $widget): ?array
    {
        $id = $this->normalizeScalar($widget['id'] ?? '', 80);
        $type = $this->normalizeScalar($widget['type'] ?? '', 40);

        if ($id === '' || !in_array($type, self::ALLOWED_WIDGET_TYPES, true)) {
            return null;
        }

        $layout = $this->normalizeScalar($widget['layout'] ?? '4/8', 10);
        if (!in_array($layout, self::ALLOWED_FRACTIONS, true)) {
            $layout = '4/8';
        }

        $aggregation = $this->normalizeScalar($widget['aggregation'] ?? 'count', 40);
        if (!in_array($aggregation, self::ALLOWED_AGGREGATIONS, true)) {
            $aggregation = 'count';
        }

        return [
            'id' => $id,
            'type' => $type,
            'title' => $this->normalizeScalar($widget['title'] ?? '', 160),
            'layout' => $layout,
            'dimensionColumn' => $this->normalizeScalar($widget['dimensionColumn'] ?? '', 120),
            'valueColumn' => $this->normalizeScalar($widget['valueColumn'] ?? '', 120),
            'aggregation' => $aggregation,
            'displayMode' => $this->normalizeScalar($widget['displayMode'] ?? '', 40),
            'format' => $this->normalizeScalar($widget['format'] ?? '', 40),
            'color' => $this->normalizeColor($widget['color'] ?? null),
            'bgColor' => $this->normalizeColor($widget['bgColor'] ?? null),
            'textColor' => $this->normalizeColor($widget['textColor'] ?? null),
            'titleColor' => $this->normalizeColor($widget['titleColor'] ?? null),
            'valueColor' => $this->normalizeColor($widget['valueColor'] ?? null),
            'alignment' => $this->normalizeAlignment($widget['alignment'] ?? 'left'),
            'textSize' => $this->normalizeInteger(
                $widget['textSize'] ?? self::WIDGET_TEXT_SIZE_DEFAULT,
                self::WIDGET_TEXT_SIZE_MIN,
                self::WIDGET_TEXT_SIZE_MAX,
                self::WIDGET_TEXT_SIZE_DEFAULT
            ),
            'valueSize' => $this->normalizeInteger(
                $widget['valueSize'] ?? self::WIDGET_VALUE_SIZE_DEFAULT,
                self::WIDGET_VALUE_SIZE_MIN,
                self::WIDGET_VALUE_SIZE_MAX,
                self::WIDGET_VALUE_SIZE_DEFAULT
            ),
            'cardHeight' => $this->normalizeInteger($widget['cardHeight'] ?? 75, 75, 520, 75),
            'hideTitle' => (bool) ($widget['hideTitle'] ?? false),
            'hideText' => (bool) ($widget['hideText'] ?? false),
            'hidden' => (bool) ($widget['hidden'] ?? false),
            'maxItems' => $this->normalizeInteger($widget['maxItems'] ?? 8, 3, 20, 8),
            'chartDimensions' => $this->normalizeDimensionCollection($widget['chartDimensions'] ?? [], $widget['dimensionColumn'] ?? ''),
            'rowDimensions' => $this->normalizeDimensionCollection($widget['rowDimensions'] ?? [], $type === 'table' ? ($widget['dimensionColumn'] ?? '') : ''),
            'measures' => $this->normalizeMeasureCollection($widget['measures'] ?? [], $widget),
            'widgetFilters' => $this->normalizeWidgetFilterCollection(
                $widget['widgetFilters'] ?? [],
                $widget['filterColumn'] ?? '',
                $widget['filterValue'] ?? '',
            ),
            'tableColumns' => $this->normalizeTableColumns($widget['tableColumns'] ?? []),
            'tableColumnStyles' => $this->normalizeTableColumnStyles($widget['tableColumnStyles'] ?? []),
            'tableStyles' => $this->normalizeDatatableStyleConfig($widget['tableStyles'] ?? []),
            'sortColumn' => $this->normalizeScalar($widget['sortColumn'] ?? '', 120),
            'sortDir' => $this->normalizeSortDirection($widget['sortDir'] ?? 'asc'),
        ];
    }

    private function createPlaceholderPage(): array
    {
        return [
            'id' => 'page-bi-placeholder',
            'name' => 'Page BI',
            'connectionId' => '',
            'fileId' => '',
            'filters' => [],
            'widgets' => [],
            'ownerUserId' => 0,
            'ownerEmail' => '',
            'ownerDisplayName' => '',
            'allowedUserIds' => [],
            'allowedProfileTypes' => [],
            'canEdit' => false,
            'canManagePermissions' => false,
            'isPlaceholder' => true,
        ];
    }

    private function canViewPage(Utilisateur $user, array $page): bool
    {
        if ($this->isAdmin($user) || $this->isOwner($user, $page)) {
            return true;
        }

        $allowedUserIds = $this->normalizeIntegerCollection($page['allowedUserIds'] ?? [], self::MAX_ALLOWED_USERS);
        if (in_array((int) ($user->getId() ?? 0), $allowedUserIds, true)) {
            return true;
        }

        $allowedProfiles = array_map('mb_strtolower', $this->normalizeStringCollection($page['allowedProfileTypes'] ?? [], 80, self::MAX_ALLOWED_PROFILES));
        if (in_array(mb_strtolower($user->getEffectiveProfileType()), $allowedProfiles, true)) {
            return true;
        }

        return $allowedUserIds === [] && $allowedProfiles === [];
    }

    private function canEditPage(Utilisateur $user, array $page): bool
    {
        if (!empty($page['isPlaceholder'])) {
            return false;
        }

        return $this->isAdmin($user) || $this->isOwner($user, $page);
    }

    private function canManagePagePermissions(Utilisateur $user, array $page): bool
    {
        return $this->canEditPage($user, $page);
    }

    private function isOwner(Utilisateur $user, array $page): bool
    {
        $ownerUserId = (int) ($page['ownerUserId'] ?? 0);
        if ($ownerUserId > 0 && $ownerUserId === (int) ($user->getId() ?? 0)) {
            return true;
        }

        $ownerEmail = mb_strtolower(trim((string) ($page['ownerEmail'] ?? '')));
        $userEmail = mb_strtolower(trim((string) ($user->getEmail() ?? '')));

        return $ownerEmail !== '' && $ownerEmail === $userEmail;
    }

    private function isAdmin(Utilisateur $user): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return strcasecmp($user->getEffectiveProfileType(), 'Admin') === 0;
    }

    private function ensureUniquePageId(string $pageId, array $knownIds): string
    {
        $candidate = $pageId !== '' ? $pageId : 'page-bi';
        $suffix = 1;

        while (isset($knownIds[$candidate])) {
            $suffix++;
            $candidate = $pageId . '-' . $suffix;
        }

        return $candidate;
    }

    private function formatUserDisplayName(Utilisateur $user): string
    {
        $displayName = trim((string) $user->getPrenom() . ' ' . (string) $user->getNom());

        return $displayName !== '' ? $displayName : (string) ($user->getEmail() ?? 'Utilisateur');
    }

    private function normalizeScalar(mixed $value, int $maxLength): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, $maxLength);
    }

    private function normalizeInteger(mixed $value, int $min, int $max, int $default): int
    {
        $normalized = (int) $value;
        if ($normalized < $min || $normalized > $max) {
            return $default;
        }

        return $normalized;
    }

    private function normalizeColor(mixed $value): ?string
    {
        $color = trim((string) $value);
        if ($color === '') {
            return null;
        }

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $color) !== 1) {
            return null;
        }

        return strtolower($color);
    }

    private function normalizeAlignment(mixed $value): string
    {
        $alignment = $this->normalizeScalar($value ?? 'left', 20);

        if (!in_array($alignment, self::ALLOWED_ALIGNMENTS, true)) {
            return 'left';
        }

        return $alignment;
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIntegerCollection(mixed $values, int $limit): array
    {
        $normalized = [];
        foreach (is_array($values) ? $values : [] as $value) {
            $integer = (int) $value;
            if ($integer > 0) {
                $normalized[$integer] = $integer;
            }
            if (count($normalized) >= $limit) {
                break;
            }
        }

        return array_values($normalized);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeStringCollection(mixed $values, int $maxLength, int $limit): array
    {
        $normalized = [];
        foreach (is_array($values) ? $values : [] as $value) {
            $scalar = $this->normalizeScalar($value, $maxLength);
            if ($scalar !== '') {
                $normalized[$scalar] = $scalar;
            }
            if (count($normalized) >= $limit) {
                break;
            }
        }

        return array_values($normalized);
    }

    private function normalizeDimensionCollection(mixed $dimensions, mixed $legacyValue = ''): array
    {
        $items = is_array($dimensions) ? $dimensions : [];
        $normalized = [];

        foreach ($items as $dimension) {
            $value = $this->normalizeScalar($dimension ?? '', 120);
            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
            if (count($normalized) >= self::MAX_WIDGET_DIMENSIONS) {
                break;
            }
        }

        if ($normalized === []) {
            $legacyDimension = $this->normalizeScalar($legacyValue ?? '', 120);
            if ($legacyDimension !== '') {
                $normalized[] = $legacyDimension;
            }
        }

        return $normalized;
    }

    private function normalizeMeasureCollection(mixed $measures, array $widget): array
    {
        $items = is_array($measures) ? $measures : [];
        $normalized = [];

        foreach ($items as $measure) {
            if (!is_array($measure)) {
                continue;
            }

            $entry = $this->normalizeMeasure($measure);
            if ($entry === null) {
                continue;
            }

            $normalized[] = $entry;
            if (count($normalized) >= self::MAX_WIDGET_MEASURES) {
                break;
            }
        }

        if ($normalized !== []) {
            return $normalized;
        }

        $aggregation = $this->normalizeScalar($widget['aggregation'] ?? 'count', 40);
        if (!in_array($aggregation, self::ALLOWED_AGGREGATIONS, true)) {
            $aggregation = 'count';
        }

        if (($widget['type'] ?? '') === 'percentage' && $aggregation === 'percentage') {
            $aggregation = $this->normalizeScalar($widget['valueColumn'] ?? '', 120) !== '' ? 'sum' : 'count';
        }

        return [[
            'id' => 'measure-1',
            'column' => $this->normalizeScalar($widget['valueColumn'] ?? '', 120),
            'aggregation' => $aggregation,
            'matchValue' => '',
        ]];
    }

    private function normalizeMeasure(array $measure): ?array
    {
        $aggregation = $this->normalizeScalar($measure['aggregation'] ?? 'count', 40);
        if (!in_array($aggregation, self::ALLOWED_AGGREGATIONS, true)) {
            $aggregation = 'count';
        }

        return [
            'id' => $this->normalizeScalar($measure['id'] ?? '', 80) ?: 'measure-1',
            'column' => $this->normalizeScalar($measure['column'] ?? '', 120),
            'aggregation' => $aggregation,
            'matchValue' => $this->normalizeScalar($measure['matchValue'] ?? '', 160),
        ];
    }

    private function normalizeWidgetFilterCollection(mixed $filters, mixed $legacyColumn = '', mixed $legacyValue = ''): array
    {
        $items = is_array($filters) ? $filters : [];
        $normalized = [];

        foreach ($items as $filter) {
            if (!is_array($filter)) {
                continue;
            }

            $entry = $this->normalizeWidgetFilter($filter);
            if ($entry === null) {
                continue;
            }

            $normalized[] = $entry;
            if (count($normalized) >= self::MAX_WIDGET_FILTERS) {
                break;
            }
        }

        if ($normalized !== []) {
            return $normalized;
        }

        $legacyFilter = $this->normalizeWidgetFilter([
            'id' => 'filter-1',
            'column' => $legacyColumn,
            'value' => $legacyValue,
        ]);

        return $legacyFilter === null ? [] : [$legacyFilter];
    }

    private function normalizeWidgetFilter(array $filter): ?array
    {
        $column = $this->normalizeScalar($filter['column'] ?? '', 120);
        $inputMode = $this->normalizeFilterInputMode($filter['inputMode'] ?? 'select');
        $values = $inputMode === 'select'
            ? $this->normalizeFilterValues($filter['values'] ?? [], $filter['value'] ?? null)
            : [];
        $value = $inputMode === 'input'
            ? $this->normalizeScalar($filter['value'] ?? '', 160)
            : '';
        if ($column === '' || ($inputMode === 'input' ? $value === '' : $values === [])) {
            return null;
        }

        return [
            'id' => $this->normalizeScalar($filter['id'] ?? '', 80) ?: 'filter-1',
            'column' => $column,
            'operator' => $this->normalizeFilterOperator($filter['operator'] ?? 'equals'),
            'inputMode' => $inputMode,
            'value' => $value,
            'values' => $values,
            'valueStyles' => $inputMode === 'select'
                ? $this->normalizeWidgetFilterValueStyles(
                    $filter['valueStyles'] ?? [],
                    $values,
                    $this->normalizeFilterStyleTarget($filter['styleTarget'] ?? 'none'),
                    $this->normalizeColor($filter['bgColor'] ?? null),
                    $this->normalizeColor($filter['textColor'] ?? null),
                )
                : [],
            'styleTarget' => $this->normalizeFilterStyleTarget($filter['styleTarget'] ?? 'none'),
            'bgColor' => $this->normalizeColor($filter['bgColor'] ?? null),
            'textColor' => $this->normalizeColor($filter['textColor'] ?? null),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeFilterValues(mixed $values, mixed $fallbackValue): array
    {
        $normalized = [];
        $seen = [];
        $source = is_array($values) ? $values : [$fallbackValue];

        foreach ($source as $value) {
            $current = $this->normalizeScalar($value, 160);
            if ($current === '' || isset($seen[$current])) {
                continue;
            }

            $normalized[] = $current;
            $seen[$current] = true;

            if (count($normalized) >= 50) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $allowedValues
     * @return array<int, array{value:string,styleTarget:string,bgColor:?string,textColor:?string}>
     */
    private function normalizeWidgetFilterValueStyles(
        mixed $valueStyles,
        array $allowedValues,
        string $legacyStyleTarget,
        ?string $legacyBgColor,
        ?string $legacyTextColor,
    ): array {
        $normalized = [];
        $seen = [];
        $allowedLookup = array_fill_keys($allowedValues, true);

        foreach (is_array($valueStyles) ? $valueStyles : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $value = $this->normalizeScalar($entry['value'] ?? '', 160);
            if ($value === '' || !isset($allowedLookup[$value]) || isset($seen[$value])) {
                continue;
            }

            $normalized[] = [
                'value' => $value,
                'styleTarget' => $this->normalizeFilterStyleTarget($entry['styleTarget'] ?? 'none'),
                'bgColor' => $this->normalizeColor($entry['bgColor'] ?? null),
                'textColor' => $this->normalizeColor($entry['textColor'] ?? null),
            ];
            $seen[$value] = true;
        }

        foreach ($allowedValues as $value) {
            if (isset($seen[$value])) {
                continue;
            }

            $normalized[] = [
                'value' => $value,
                'styleTarget' => $legacyStyleTarget,
                'bgColor' => $legacyBgColor,
                'textColor' => $legacyTextColor,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTableColumns(mixed $columns): array
    {
        $normalized = [];

        foreach (is_array($columns) ? $columns : [] as $column) {
            $value = $this->normalizeScalar($column, 120);
            if ($value === '') {
                continue;
            }

            $normalized[$value] = $value;
            if (count($normalized) >= 24) {
                break;
            }
        }

        return array_values($normalized);
    }

    /**
     * @return array<int, array{key:string,bgColor:?string,textColor:?string}>
     */
    private function normalizeTableColumnStyles(mixed $styles): array
    {
        $normalized = [];
        $seen = [];

        foreach (is_array($styles) ? $styles : [] as $style) {
            if (!is_array($style)) {
                continue;
            }

            $key = $this->normalizeScalar($style['key'] ?? '', 120);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $bgColor = $this->normalizeColor($style['bgColor'] ?? null);
            $textColor = $this->normalizeColor($style['textColor'] ?? null);
            if ($bgColor === null && $textColor === null) {
                continue;
            }

            $normalized[] = [
                'key' => $key,
                'bgColor' => $bgColor,
                'textColor' => $textColor,
            ];
            $seen[$key] = true;

            if (count($normalized) >= 24) {
                break;
            }
        }

        return $normalized;
    }

    private function normalizeDatatableStyleConfig(mixed $styles): array
    {
        $safe = is_array($styles) ? $styles : [];

        return [
            'headerBgColor' => $this->normalizeColor($safe['headerBgColor'] ?? null),
            'headerTextColor' => $this->normalizeColor($safe['headerTextColor'] ?? null),
            'rowBgColor' => $this->normalizeColor($safe['rowBgColor'] ?? null),
            'rowAltBgColor' => $this->normalizeColor($safe['rowAltBgColor'] ?? null),
            'cellBgColor' => $this->normalizeColor($safe['cellBgColor'] ?? null),
            'cellTextColor' => $this->normalizeColor($safe['cellTextColor'] ?? null),
        ];
    }

    private function normalizeSortDirection(mixed $value): string
    {
        $direction = $this->normalizeScalar($value ?? 'asc', 4);

        return in_array($direction, self::ALLOWED_SORT_DIRECTIONS, true) ? $direction : 'asc';
    }

    private function normalizeFilterOperator(mixed $value): string
    {
        $operator = $this->normalizeScalar($value ?? 'equals', 12);

        return in_array($operator, self::ALLOWED_FILTER_OPERATORS, true) ? $operator : 'equals';
    }

    private function normalizeFilterInputMode(mixed $value): string
    {
        $inputMode = $this->normalizeScalar($value ?? 'select', 12);

        return in_array($inputMode, self::ALLOWED_FILTER_INPUT_MODES, true) ? $inputMode : 'select';
    }

    private function normalizeFilterStyleTarget(mixed $value): string
    {
        $target = $this->normalizeScalar($value ?? 'none', 12);

        return in_array($target, self::ALLOWED_FILTER_STYLE_TARGETS, true) ? $target : 'none';
    }
}
