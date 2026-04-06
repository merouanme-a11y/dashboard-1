<?php

namespace App\Service;

use App\Entity\ThemeSetting;
use App\Repository\ThemeSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class GanttViewStateService
{
    private const SETTING_KEY = 'gantt_shared_view_state';
    private const MAX_EXPANDED_PROJECT_IDS = 200;
    private const ALLOWED_DISPLAY_MODES = ['simple', 'detailed'];
    private const ALLOWED_BACKLOG_VIEWS = ['table', 'cards'];

    public function __construct(
        private ThemeSettingRepository $settingRepository,
        private EntityManagerInterface $em,
    ) {}

    public function getState(): array
    {
        $setting = $this->settingRepository->findByKey(self::SETTING_KEY);
        if (!$setting instanceof ThemeSetting) {
            return [];
        }

        $decoded = json_decode((string) $setting->getSettingValue(), true);

        return is_array($decoded) ? $this->normalizeState($decoded) : [];
    }

    public function saveState(array $state): array
    {
        $normalized = $this->normalizeState($state);
        $setting = $this->settingRepository->findByKey(self::SETTING_KEY);

        if ($normalized === []) {
            if ($setting instanceof ThemeSetting) {
                $this->em->remove($setting);
                $this->em->flush();
            }

            return [];
        }

        if (!$setting instanceof ThemeSetting) {
            $setting = (new ThemeSetting())
                ->setSettingKey(self::SETTING_KEY);
            $this->em->persist($setting);
        }

        $setting->setSettingValue(json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->em->flush();

        return $normalized;
    }

    private function normalizeState(array $state): array
    {
        $normalized = [];

        $timelineStart = trim((string) ($state['timelineStart'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}$/', $timelineStart) === 1) {
            $normalized['timelineStart'] = $timelineStart;
        }

        $visibleMonths = $this->normalizeInteger($state['visibleMonths'] ?? null, 1, 36);
        if ($visibleMonths !== null) {
            $normalized['visibleMonths'] = $visibleMonths;
        }

        $monthWidth = $this->normalizeInteger($state['monthWidth'] ?? null, 80, 320);
        if ($monthWidth !== null) {
            $normalized['monthWidth'] = $monthWidth;
        }

        $timelineZoom = $this->normalizeFloat($state['timelineZoom'] ?? null, 0.7, 1.5);
        if ($timelineZoom !== null) {
            $normalized['timelineZoom'] = $timelineZoom;
        }

        $defaultDuration = $this->normalizeInteger($state['defaultDuration'] ?? null, 1, 24);
        if ($defaultDuration !== null) {
            $normalized['defaultDuration'] = $defaultDuration;
        }

        foreach ([
            'showTodayMarker',
            'showTimelineProgress',
            'showPlanningSidebar',
            'showSettingsPanel',
        ] as $booleanKey) {
            if (array_key_exists($booleanKey, $state)) {
                $normalized[$booleanKey] = $this->normalizeBoolean($state[$booleanKey]);
            }
        }

        $displayMode = trim((string) ($state['displayMode'] ?? ''));
        if (in_array($displayMode, self::ALLOWED_DISPLAY_MODES, true)) {
            $normalized['displayMode'] = $displayMode;
        }

        $backlogView = trim((string) ($state['backlogView'] ?? ''));
        if (in_array($backlogView, self::ALLOWED_BACKLOG_VIEWS, true)) {
            $normalized['backlogView'] = $backlogView;
        }

        foreach (['serviceFilter', 'typeFilter', 'statusFilter'] as $filterKey) {
            $filterValue = mb_substr(trim((string) ($state[$filterKey] ?? '')), 0, 120);
            if ($filterValue !== '') {
                $normalized[$filterKey] = $filterValue;
            }
        }

        $expandedProjectIds = [];
        $rawExpandedProjectIds = $state['expandedProjectIds'] ?? null;
        if (is_array($rawExpandedProjectIds)) {
            foreach ($rawExpandedProjectIds as $projectId) {
                $projectId = mb_substr(trim((string) $projectId), 0, 120);
                if ($projectId === '' || in_array($projectId, $expandedProjectIds, true)) {
                    continue;
                }

                $expandedProjectIds[] = $projectId;
                if (count($expandedProjectIds) >= self::MAX_EXPANDED_PROJECT_IDS) {
                    break;
                }
            }
        }

        if ($expandedProjectIds !== []) {
            $normalized['expandedProjectIds'] = $expandedProjectIds;
        }

        return $normalized;
    }

    private function normalizeInteger(mixed $value, int $min, int $max): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        return max($min, min($max, (int) round((float) $value)));
    }

    private function normalizeFloat(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $normalized = max($min, min($max, (float) $value));

        return round($normalized, 2);
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        return in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
