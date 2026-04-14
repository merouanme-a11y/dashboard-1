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
        'table',
        'histogram',
        'counter',
    ];
    private const ALLOWED_AGGREGATIONS = ['count', 'sum', 'avg', 'percentage'];
    private const ALLOWED_ALIGNMENTS = ['left', 'center', 'right'];
    public function __construct(
        private UserPagePreferenceRepository $preferenceRepository,
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

        $preference = $this->preferenceRepository->findOneForUserAndPage($user, self::PAGE_KEY);

        return $this->normalizePreferences($preference?->getPreferencePayload() ?? []);
    }

    public function saveForUser(Utilisateur $user, array $preferences): array
    {
        $this->ensureModuleExists();

        $normalized = $this->normalizePreferences($preferences);
        $preference = $this->preferenceRepository->findOneForUserAndPage($user, self::PAGE_KEY);

        if (!$this->hasAnyPreferences($normalized)) {
            if ($preference instanceof UserPagePreference) {
                $this->em->remove($preference);
                $this->em->flush();
            }

            return $normalized;
        }

        if (!$preference instanceof UserPagePreference) {
            $preference = (new UserPagePreference())
                ->setUtilisateur($user)
                ->setPageKey(self::PAGE_KEY);
            $this->em->persist($preference);
        }

        $preference->setPreferencePayload($normalized);
        $this->em->flush();

        return $normalized;
    }

    private function normalizePreferences(array $preferences): array
    {
        $normalized = [
            'defaultConnection' => $this->normalizeScalar($preferences['defaultConnection'] ?? '', 120),
            'defaultFile' => $this->normalizeScalar($preferences['defaultFile'] ?? '', 255),
            'selectedPageId' => $this->normalizeScalar($preferences['selectedPageId'] ?? '', 80),
            'pages' => [],
        ];

        $rawPages = is_array($preferences['pages'] ?? null) ? $preferences['pages'] : [];
        foreach ($rawPages as $rawPage) {
            if (!is_array($rawPage)) {
                continue;
            }

            $page = $this->normalizePage($rawPage);
            if ($page === null) {
                continue;
            }

            $normalized['pages'][] = $page;
            if (count($normalized['pages']) >= self::MAX_PAGES) {
                break;
            }
        }

        if ($normalized['pages'] === []) {
            $normalized['pages'][] = $this->createDefaultPage();
        }

        $selectedPageId = $normalized['selectedPageId'];
        $pageIds = array_column($normalized['pages'], 'id');
        if ($selectedPageId === '' || !in_array($selectedPageId, $pageIds, true)) {
            $normalized['selectedPageId'] = (string) ($normalized['pages'][0]['id'] ?? 'page-1');
        }

        return $normalized;
    }

    private function normalizePage(array $page): ?array
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
        ];
    }

    private function createDefaultPage(): array
    {
        return [
            'id' => 'page-bi-1',
            'name' => 'Page BI principale',
            'connectionId' => '',
            'fileId' => '',
            'filters' => [],
            'widgets' => [],
        ];
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
        $value = $this->normalizeScalar($filter['value'] ?? '', 160);
        if ($column === '' || $value === '') {
            return null;
        }

        return [
            'id' => $this->normalizeScalar($filter['id'] ?? '', 80) ?: 'filter-1',
            'column' => $column,
            'value' => $value,
        ];
    }

    private function hasAnyPreferences(array $preferences): bool
    {
        foreach ((array) ($preferences['pages'] ?? []) as $page) {
            if (!empty($page['widgets']) || trim((string) ($page['connectionId'] ?? '')) !== '' || trim((string) ($page['fileId'] ?? '')) !== '') {
                return true;
            }
        }

        return trim((string) ($preferences['defaultConnection'] ?? '')) !== ''
            || trim((string) ($preferences['defaultFile'] ?? '')) !== '';
    }
}
