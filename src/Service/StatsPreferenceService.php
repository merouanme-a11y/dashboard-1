<?php

namespace App\Service;

use App\Entity\UserPagePreference;
use App\Entity\Utilisateur;
use App\Repository\UserPagePreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

class StatsPreferenceService
{
    private const PAGE_KEY = 'stats';
    private const MAX_PROJECTS = 100;
    private const MAX_CARDS = 100;
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

    public function __construct(
        private UserPagePreferenceRepository $preferenceRepository,
        private EntityManagerInterface $em,
    ) {}

    public function getForUser(Utilisateur $user): array
    {
        $preference = $this->preferenceRepository->findOneForUserAndPage($user, self::PAGE_KEY);

        return $this->normalizePreferences($preference?->getPreferencePayload() ?? []);
    }

    public function saveForUser(Utilisateur $user, array $preferences): array
    {
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
            'defaultProject' => $this->normalizeKey($preferences['defaultProject'] ?? '', 120),
            'projects' => [],
        ];

        $rawProjects = is_array($preferences['projects'] ?? null) ? $preferences['projects'] : [];
        $projectCount = 0;

        foreach ($rawProjects as $projectId => $projectPreferences) {
            if ($projectCount >= self::MAX_PROJECTS) {
                break;
            }

            $projectKey = $this->normalizeKey($projectId, 120);
            if ($projectKey === '' || !is_array($projectPreferences)) {
                continue;
            }

            $normalizedProject = [
                'layout' => $this->normalizeLayoutCards($projectPreferences['layout'] ?? null),
                'visibility' => $this->normalizeVisibilityCards($projectPreferences['visibility'] ?? null),
                'colors' => $this->normalizeColorCards($projectPreferences['colors'] ?? null),
            ];

            if (!$this->projectHasPreferences($normalizedProject)) {
                continue;
            }

            $normalized['projects'][$projectKey] = $normalizedProject;
            ++$projectCount;
        }

        return $normalized;
    }

    private function normalizeLayoutCards(mixed $value): array
    {
        $cards = $this->extractCardsArray($value);
        $normalized = [];
        $seen = [];

        foreach ($cards as $card) {
            if (!is_array($card)) {
                continue;
            }

            $id = $this->normalizeKey($card['id'] ?? '', 120);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }

            $fraction = (string) ($card['fraction'] ?? '1/8');
            if (!in_array($fraction, self::ALLOWED_FRACTIONS, true)) {
                $fraction = '1/8';
            }

            $normalized[] = [
                'id' => $id,
                'fraction' => $fraction,
            ];
            $seen[$id] = true;

            if (count($normalized) >= self::MAX_CARDS) {
                break;
            }
        }

        return $normalized;
    }

    private function normalizeVisibilityCards(mixed $value): array
    {
        $cards = $this->extractCardsArray($value);
        $normalized = [];
        $seen = [];

        foreach ($cards as $card) {
            if (!is_array($card)) {
                continue;
            }

            $id = $this->normalizeKey($card['id'] ?? '', 120);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'hidden' => $this->normalizeBoolean($card['hidden'] ?? false),
            ];
            $seen[$id] = true;

            if (count($normalized) >= self::MAX_CARDS) {
                break;
            }
        }

        return $normalized;
    }

    private function normalizeColorCards(mixed $value): array
    {
        $cards = $this->extractCardsArray($value);
        $normalized = [];
        $seen = [];

        foreach ($cards as $card) {
            if (!is_array($card)) {
                continue;
            }

            $id = $this->normalizeKey($card['id'] ?? '', 120);
            if ($id === '' || isset($seen[$id])) {
                continue;
            }

            $bgColor = $this->normalizeColor($card['bgColor'] ?? null);
            $textColor = $this->normalizeColor($card['textColor'] ?? null);
            if ($bgColor === null && $textColor === null) {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'bgColor' => $bgColor,
                'textColor' => $textColor,
            ];
            $seen[$id] = true;

            if (count($normalized) >= self::MAX_CARDS) {
                break;
            }
        }

        return $normalized;
    }

    private function extractCardsArray(mixed $value): array
    {
        if (is_array($value) && array_is_list($value)) {
            return $value;
        }

        if (is_array($value) && is_array($value['cards'] ?? null)) {
            return $value['cards'];
        }

        return [];
    }

    private function normalizeKey(mixed $value, int $maxLength): string
    {
        $key = trim((string) $value);
        if ($key === '') {
            return '';
        }

        return mb_substr($key, 0, $maxLength);
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = mb_strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
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

        $color = strtolower($color);
        if (strlen($color) === 4) {
            return sprintf('#%s%s%s%s%s%s', $color[1], $color[1], $color[2], $color[2], $color[3], $color[3]);
        }

        return $color;
    }

    private function projectHasPreferences(array $projectPreferences): bool
    {
        return $projectPreferences['layout'] !== []
            || $projectPreferences['visibility'] !== []
            || $projectPreferences['colors'] !== [];
    }

    private function hasAnyPreferences(array $preferences): bool
    {
        return trim((string) ($preferences['defaultProject'] ?? '')) !== ''
            || !empty($preferences['projects']);
    }
}
