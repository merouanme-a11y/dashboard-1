<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class YouTrackApiService
{
    public const TICKETS_MODULE = 'tickets';

    private const LIST_CACHE_PREFIX = 'youtrack_tickets_list_v2_';
    private const DETAIL_CACHE_PREFIX = 'youtrack_ticket_detail_v1_';
    private const PROJECT_TICKETS_CACHE_PREFIX = 'youtrack_project_tickets_v1_';
    private const PROJECTS_CACHE_KEY = 'youtrack_projects_v1';
    private const LIST_CACHE_FRESH_TTL = 300;
    private const LIST_CACHE_STALE_TTL = 3300;
    private const DETAIL_CACHE_FRESH_TTL = 300;
    private const DETAIL_CACHE_STALE_TTL = 3300;
    private const PROJECTS_CACHE_FRESH_TTL = 1800;
    private const PROJECTS_CACHE_STALE_TTL = 41400;
    private const PROJECT_TICKETS_CACHE_FRESH_TTL = 300;
    private const PROJECT_TICKETS_CACHE_STALE_TTL = 3300;
    private const SERVICE_FIELD = 'Service';
    private const STATE_FIELD = 'State';
    private const PRIORITY_FIELD = 'Priority';
    private const DUE_DATE_FIELD = 'Date echeance';
    private const ASSIGNEE_FIELD = 'Assignee';
    private const LIEN_RM_FIELD = 'Lien RM';
    private const ACTION_FIELD = 'Type Action';
    private const DEFAULT_STATE_FILTER = 'A FAIRE';
    private const LIST_PAGE_SIZE = 100;
    private const LIST_FIELDS = 'id,idReadable,summary,created,updated,reporter(id,login,name,fullName),customFields(name,value(id,login,name,presentation,text,fullName,login))';
    private const DETAIL_FIELDS = 'id,idReadable,summary,description,created,updated,reporter(id,login,name,fullName),customFields(name,value(id,login,name,presentation,text,fullName,login)),attachments(id,name,url),comments($top,id,text,created,updated,author(id,login,name,fullName),attachments(id,name,url))';
    private const RESOLVED_STATE_WORDS = [
        'resolu',
        'clos',
        'cloture',
        'cloturee',
        'termine',
        'done',
        'fixed',
        'completed',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private DirectoryServiceManager $directoryServiceManager,
        private ApiResultCacheService $apiResultCache,
        private string $youTrackUrl = 'https://maintenance.adep.com',
        private string $youTrackToken = 'perm-TWVyb3Vhbg==.NTAtMTA=.VmHAxQFu0LsdFFg1p86ORO4nkelUus',
        private string $youTrackProject = 'MTN',
    ) {}

    public function getTicketsPayloadForUser(Utilisateur $user, bool $forceRefresh = false): array
    {
        $basePayload = $this->apiResultCache->remember(
            $this->getListCacheKey($user),
            self::LIST_CACHE_FRESH_TTL,
            self::LIST_CACHE_STALE_TTL,
            fn (): array => $this->buildTicketsPayloadForUser($user),
            $forceRefresh,
        );

        return $this->decorateTicketsPayload($basePayload, $user);
    }

    public function getTicketDetailPayloadForUser(string $ticketId, Utilisateur $user, bool $forceRefresh = false): array
    {
        $ticketId = trim($ticketId);
        if ($ticketId === '') {
            return ['_error' => 'ID ticket manquant.'];
        }

        $payload = $this->apiResultCache->remember(
            self::DETAIL_CACHE_PREFIX . md5($ticketId),
            self::DETAIL_CACHE_FRESH_TTL,
            self::DETAIL_CACHE_STALE_TTL,
            fn (): array => $this->buildTicketDetailPayload($ticketId),
            $forceRefresh,
        );

        if (($payload['_error'] ?? '') !== '') {
            return $payload;
        }

        $ticket = is_array($payload['ticket'] ?? null) ? $payload['ticket'] : null;
        if (!is_array($ticket) || !$this->canUserSeeTicket($ticket, $user)) {
            return ['_error' => 'Ticket introuvable ou inaccessible.'];
        }

        return $payload;
    }

    public function getAvailableProjectsPayload(bool $forceRefresh = false): array
    {
        return $this->apiResultCache->remember(
            self::PROJECTS_CACHE_KEY,
            self::PROJECTS_CACHE_FRESH_TTL,
            self::PROJECTS_CACHE_STALE_TTL,
            function (): array {
            $response = $this->request('GET', '/api/admin/projects', [
                'query' => [
                    'fields' => 'id,name,shortName,lead(id,login,name)',
                ],
            ]);

            if (($response['_error'] ?? '') !== '') {
                return $response;
            }

            $projects = [];
            foreach ($response as $project) {
                if (!is_array($project)) {
                    continue;
                }

                $projectId = trim((string) ($project['shortName'] ?? $project['id'] ?? ''));
                $projectName = trim((string) ($project['name'] ?? $projectId));
                if ($projectId === '') {
                    continue;
                }

                $projects[] = [
                    'id' => $projectId,
                    'name' => $projectName !== '' ? $projectName : $projectId,
                ];
            }

            usort($projects, static function (array $left, array $right): int {
                return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            });

            return [
                'projects' => $projects,
            ];
            },
            $forceRefresh,
        );
    }

    public function getProjectTicketsPayload(string $projectId, bool $forceRefresh = false): array
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            return ['_error' => 'Projet manquant.'];
        }

        return $this->apiResultCache->remember(
            self::PROJECT_TICKETS_CACHE_PREFIX . md5($this->normalizeCompareValue($projectId)),
            self::PROJECT_TICKETS_CACHE_FRESH_TTL,
            self::PROJECT_TICKETS_CACHE_STALE_TTL,
            fn (): array => $this->buildProjectTicketsPayload($projectId),
            $forceRefresh,
        );
    }

    public function peekTicketsPayloadForUser(Utilisateur $user): ?array
    {
        $payload = $this->apiResultCache->peek($this->getListCacheKey($user));

        return is_array($payload) ? $this->decorateTicketsPayload($payload, $user) : null;
    }

    public function peekAvailableProjectsPayload(): ?array
    {
        return $this->apiResultCache->peek(self::PROJECTS_CACHE_KEY);
    }

    public function peekProjectTicketsPayload(string $projectId): ?array
    {
        $projectId = trim($projectId);
        if ($projectId === '') {
            return null;
        }

        return $this->apiResultCache->peek(
            self::PROJECT_TICKETS_CACHE_PREFIX . md5($this->normalizeCompareValue($projectId))
        );
    }

    private function buildTicketsPayloadForUser(Utilisateur $user): array
    {
        $isAdmin = $this->isAdminUser($user);
        $service = trim((string) ($user->getService() ?? ''));

        if (!$isAdmin && $service === '') {
            return [
                'tickets' => [],
                'service' => '',
                'isAdmin' => false,
            ];
        }

        $tickets = [];
        $skip = 0;

        do {
            $response = $this->request('GET', '/api/issues', [
                'query' => [
                    'query' => '',
                    '$skip' => $skip,
                    '$top' => self::LIST_PAGE_SIZE,
                    'fields' => self::LIST_FIELDS,
                ],
            ]);

            if (($response['_error'] ?? '') !== '') {
                return $response;
            }

            if (!is_array($response)) {
                break;
            }

            foreach ($response as $issue) {
                if (!is_array($issue)) {
                    continue;
                }

                $ticket = $this->normalizeTicket($issue);
                if ($ticket === null || !$this->canUserSeeTicket($ticket, $user)) {
                    continue;
                }

                $tickets[] = $ticket;
            }

            $skip += self::LIST_PAGE_SIZE;
        } while (count($response) === self::LIST_PAGE_SIZE);

        usort($tickets, static function (array $left, array $right): int {
            return ((int) ($right['issueNumber'] ?? 0)) <=> ((int) ($left['issueNumber'] ?? 0));
        });

        return [
            'tickets' => $tickets,
            'service' => $isAdmin ? '(Tous - Admin)' : $service,
            'isAdmin' => $isAdmin,
        ];
    }

    private function buildProjectTicketsPayload(string $projectId): array
    {
        $tickets = [];
        $skip = 0;

        do {
            $response = $this->request('GET', '/api/issues', [
                'query' => [
                    'query' => 'project: ' . $projectId,
                    '$skip' => $skip,
                    '$top' => self::LIST_PAGE_SIZE,
                    'fields' => self::LIST_FIELDS,
                ],
            ]);

            if (($response['_error'] ?? '') !== '') {
                return $response;
            }

            if (!is_array($response)) {
                break;
            }

            foreach ($response as $issue) {
                if (!is_array($issue)) {
                    continue;
                }

                $ticket = $this->normalizeTicket($issue);
                if ($ticket === null || trim((string) ($ticket['service'] ?? '')) === '') {
                    continue;
                }

                $tickets[] = $ticket;
            }

            $skip += self::LIST_PAGE_SIZE;
        } while (count($response) === self::LIST_PAGE_SIZE);

        usort($tickets, static function (array $left, array $right): int {
            return ((int) ($right['issueNumber'] ?? 0)) <=> ((int) ($left['issueNumber'] ?? 0));
        });

        return [
            'project' => $projectId,
            'tickets' => $tickets,
            'serviceColors' => $this->directoryServiceManager->getServiceColorMap(),
        ];
    }

    private function decorateTicketsPayload(array $basePayload, Utilisateur $user): array
    {
        if (($basePayload['_error'] ?? '') !== '') {
            return $basePayload;
        }

        $tickets = is_array($basePayload['tickets'] ?? null) ? $basePayload['tickets'] : [];
        $service = (string) ($basePayload['service'] ?? '');
        $isAdmin = (bool) ($basePayload['isAdmin'] ?? false);
        $cacheMeta = is_array($basePayload['_cache'] ?? null) ? $basePayload['_cache'] : null;

        $payload = [
            'tickets' => $tickets,
            'service' => $service,
            'serviceColors' => $this->directoryServiceManager->getServiceColorMap(),
            'isAdmin' => $isAdmin,
            'defaultFilters' => [
                'state' => $this->resolveDefaultState($tickets),
                'service' => $isAdmin
                    ? 'all'
                    : ($service !== '' ? $service : 'all'),
                'responsable' => $this->resolveDefaultResponsable($user, $tickets),
                'typeAction' => 'all',
            ],
            'identityHints' => $this->buildIdentityHints($user),
        ];

        if ($cacheMeta !== null) {
            $payload['_cache'] = $cacheMeta;
        }

        return $payload;
    }

    private function buildTicketDetailPayload(string $ticketId): array
    {
        $response = $this->request('GET', '/api/issues/' . rawurlencode($ticketId), [
            'query' => [
                'fields' => self::DETAIL_FIELDS,
            ],
        ]);

        if (($response['_error'] ?? '') !== '') {
            return $response;
        }

        if (!is_array($response) || trim((string) ($response['idReadable'] ?? '')) === '') {
            return ['_error' => 'Ticket introuvable.'];
        }

        $attachments = $this->normalizeAttachments(
            is_array($response['attachments'] ?? null) ? $response['attachments'] : [],
            (string) ($response['idReadable'] ?? ''),
        );
        $description = $this->normalizeImageUrlsInText(
            (string) ($response['description'] ?? ''),
            (string) ($response['idReadable'] ?? ''),
            $attachments,
        );

        $comments = [];
        foreach ((array) ($response['comments'] ?? []) as $comment) {
            if (!is_array($comment)) {
                continue;
            }

            $commentAttachments = $this->normalizeAttachments(
                is_array($comment['attachments'] ?? null) ? $comment['attachments'] : [],
                (string) ($response['idReadable'] ?? ''),
            );
            $commentText = $this->normalizeImageUrlsInText(
                (string) ($comment['text'] ?? ''),
                (string) ($response['idReadable'] ?? ''),
                $commentAttachments,
            );

            $comments[] = [
                'id' => (string) ($comment['id'] ?? ''),
                'text' => $commentText,
                'created' => $this->formatYouTrackDate($comment['created'] ?? null),
                'updated' => $this->formatYouTrackDate($comment['updated'] ?? null),
                'author' => $this->resolveUserLabel(is_array($comment['author'] ?? null) ? $comment['author'] : []),
                'attachments' => $commentAttachments,
            ];
        }

        return [
            'ticket' => [
                'idReadable' => (string) ($response['idReadable'] ?? ''),
                'summary' => trim((string) ($response['summary'] ?? '')),
                'description' => $description,
                'state' => $this->getIssueCustomFieldText($response, self::STATE_FIELD),
                'priority' => $this->getIssueCustomFieldText($response, self::PRIORITY_FIELD),
                'created' => $this->formatYouTrackDate($response['created'] ?? null),
                'updated' => $this->formatYouTrackDate($response['updated'] ?? null),
                'dueDate' => $this->getIssueCustomFieldDate($response),
                'service' => $this->getIssueCustomFieldText($response, self::SERVICE_FIELD),
                'assignee' => $this->getIssueCustomFieldText($response, self::ASSIGNEE_FIELD),
                'lienRm' => $this->getIssueCustomFieldText($response, self::LIEN_RM_FIELD),
                'action' => $this->getIssueCustomFieldText($response, self::ACTION_FIELD),
                'reporter' => $this->resolveUserLabel(is_array($response['reporter'] ?? null) ? $response['reporter'] : []),
                'url' => $this->buildIssueUrl((string) ($response['idReadable'] ?? '')),
            ],
            'comments' => $comments,
            'attachments' => $attachments,
        ];
    }

    private function normalizeTicket(array $issue): ?array
    {
        $idReadable = trim((string) ($issue['idReadable'] ?? ''));
        if ($idReadable === '') {
            return null;
        }

        $state = $this->getIssueCustomFieldText($issue, self::STATE_FIELD);
        $priority = $this->getIssueCustomFieldText($issue, self::PRIORITY_FIELD);
        $service = $this->getIssueCustomFieldText($issue, self::SERVICE_FIELD);
        $assignee = $this->getIssueCustomFieldText($issue, self::ASSIGNEE_FIELD);
        $reporter = $this->resolveUserLabel(is_array($issue['reporter'] ?? null) ? $issue['reporter'] : []);

        return [
            'idReadable' => $idReadable,
            'project' => $this->extractProjectKey($idReadable),
            'issueNumber' => $this->extractIssueNumber($idReadable),
            'summary' => trim((string) ($issue['summary'] ?? '')),
            'state' => $state,
            'priority' => $priority,
            'created' => $this->formatYouTrackDate($issue['created'] ?? null),
            'updated' => $this->formatYouTrackDate($issue['updated'] ?? null),
            'dueDate' => $this->getIssueCustomFieldDate($issue),
            'service' => $service,
            'assignee' => $assignee,
            'lienRm' => $this->getIssueCustomFieldText($issue, self::LIEN_RM_FIELD),
            'action' => $this->getIssueCustomFieldText($issue, self::ACTION_FIELD),
            'reporter' => $reporter,
            'isUnresolved' => !$this->isResolvedState($state),
            'url' => $this->buildIssueUrl($idReadable),
        ];
    }

    private function canUserSeeTicket(array $ticket, Utilisateur $user): bool
    {
        if ($this->isAdminUser($user)) {
            return true;
        }

        $userService = $this->normalizeCompareValue((string) ($user->getService() ?? ''));
        $ticketService = $this->normalizeCompareValue((string) ($ticket['service'] ?? ''));

        return $userService !== '' && $ticketService !== '' && $userService === $ticketService;
    }

    private function getListCacheKey(Utilisateur $user): string
    {
        if ($this->isAdminUser($user)) {
            return self::LIST_CACHE_PREFIX . 'admin';
        }

        return self::LIST_CACHE_PREFIX . md5($this->normalizeCompareValue((string) ($user->getService() ?? '')));
    }

    private function resolveDefaultState(array $tickets): string
    {
        foreach ($tickets as $ticket) {
            if ($this->normalizeCompareValue((string) ($ticket['state'] ?? '')) === $this->normalizeCompareValue(self::DEFAULT_STATE_FILTER)) {
                return self::DEFAULT_STATE_FILTER;
            }
        }

        return 'all';
    }

    private function resolveDefaultResponsable(Utilisateur $user, array $tickets): string
    {
        $identityHints = $this->buildIdentityHints($user);
        if ($identityHints === []) {
            return 'all';
        }

        foreach ($tickets as $ticket) {
            foreach ([(string) ($ticket['assignee'] ?? ''), (string) ($ticket['reporter'] ?? '')] as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '') {
                    continue;
                }

                if ($this->matchesIdentityHints($candidate, $identityHints)) {
                    return $candidate;
                }
            }
        }

        return 'all';
    }

    private function buildIdentityHints(Utilisateur $user): array
    {
        $fullName = trim(trim((string) ($user->getPrenom() ?? '')) . ' ' . trim((string) ($user->getNom() ?? '')));
        $reverseName = trim(trim((string) ($user->getNom() ?? '')) . ' ' . trim((string) ($user->getPrenom() ?? '')));
        $email = trim((string) ($user->getEmail() ?? ''));
        $emailLogin = $email !== '' && str_contains($email, '@') ? (string) strstr($email, '@', true) : '';

        $hints = [];
        foreach ([$fullName, $reverseName, $email, $emailLogin] as $value) {
            $normalized = $this->normalizeIdentityValue($value);
            if ($normalized !== '' && !in_array($normalized, $hints, true)) {
                $hints[] = $normalized;
            }
        }

        return $hints;
    }

    private function matchesIdentityHints(string $value, array $identityHints): bool
    {
        $normalizedValue = $this->normalizeIdentityValue($value);
        if ($normalizedValue === '') {
            return false;
        }

        foreach ($identityHints as $hint) {
            if ($hint === '' || $normalizedValue !== $hint) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function normalizeIdentityValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value);

        return trim((string) $value);
    }

    private function normalizeAttachments(array $attachments, string $ticketId): array
    {
        $normalized = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $url = trim((string) ($attachment['url'] ?? ''));
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                if (str_starts_with($url, '/')) {
                    $url = $this->youTrackUrl . $url;
                } else {
                    $url = rtrim($this->youTrackUrl, '/') . '/api/issues/' . rawurlencode($ticketId) . '/attachments/' . ltrim($url, '/');
                }
            }

            $normalized[] = [
                'id' => (string) ($attachment['id'] ?? ''),
                'name' => (string) ($attachment['name'] ?? ''),
                'url' => $url,
            ];
        }

        return $normalized;
    }

    private function normalizeImageUrlsInText(string $text, string $ticketId, array $attachments): string
    {
        if ($text === '' || $attachments === []) {
            return $text;
        }

        $attachmentMap = $this->buildAttachmentMap($attachments);

        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\s*\(\s*([^)]+)\s*\)/i',
            function (array $matches) use ($attachmentMap, $ticketId): string {
                $alt = (string) ($matches[1] ?? '');
                $source = (string) ($matches[2] ?? '');
                $resolvedUrl = $this->resolveAttachmentUrl($source, $attachmentMap, $ticketId);
                if ($resolvedUrl === '') {
                    return (string) ($matches[0] ?? '');
                }

                return '![' . $alt . '](' . $resolvedUrl . ')';
            },
            $text,
        ) ?? $text;

        $text = preg_replace_callback(
            '/<img([^>]+)src=["\']([^"\']+)["\']([^>]*)>/i',
            function (array $matches) use ($attachmentMap, $ticketId): string {
                $before = (string) ($matches[1] ?? '');
                $src = trim((string) ($matches[2] ?? ''));
                $after = (string) ($matches[3] ?? '');

                if ($src === '') {
                    return '<img' . $before . $after . '>';
                }

                $resolvedSrc = $this->resolveAttachmentUrl($src, $attachmentMap, $ticketId);
                if ($resolvedSrc === '') {
                    return '<img' . $before . $after . '>';
                }

                return '<img' . $before . 'src="' . htmlspecialchars($resolvedSrc, ENT_QUOTES, 'UTF-8') . '"' . $after . '>';
            },
            $text,
        ) ?? $text;

        $text = preg_replace_callback(
            '/<a([^>]+)href=["\']([^"\']+)["\']([^>]*)>/i',
            function (array $matches) use ($attachmentMap, $ticketId): string {
                $before = (string) ($matches[1] ?? '');
                $href = trim((string) ($matches[2] ?? ''));
                $after = (string) ($matches[3] ?? '');

                if ($href === '') {
                    return '<a' . $before . $after . '>';
                }

                $resolvedHref = $this->resolveAttachmentUrl($href, $attachmentMap, $ticketId);
                if ($resolvedHref === '') {
                    return '<a' . $before . $after . '>';
                }

                return '<a' . $before . 'href="' . htmlspecialchars($resolvedHref, ENT_QUOTES, 'UTF-8') . '"' . $after . '>';
            },
            $text,
        ) ?? $text;

        return $text;
    }

    private function buildAttachmentMap(array $attachments): array
    {
        $attachmentMap = [];

        foreach ($attachments as $attachment) {
            $name = trim((string) ($attachment['name'] ?? ''));
            $url = trim((string) ($attachment['url'] ?? ''));
            if ($name === '' || $url === '') {
                continue;
            }

            foreach ([$name, basename($name)] as $candidate) {
                $lookupKey = $this->normalizeAttachmentLookupKey($candidate);
                if ($lookupKey !== '') {
                    $attachmentMap[$lookupKey] = $url;
                }

                $nameWithoutExt = preg_replace('/\.[^.]+$/', '', $candidate);
                if (is_string($nameWithoutExt) && $nameWithoutExt !== '') {
                    $lookupKey = $this->normalizeAttachmentLookupKey($nameWithoutExt);
                    if ($lookupKey !== '') {
                        $attachmentMap[$lookupKey] = $url;
                    }
                }
            }
        }

        return $attachmentMap;
    }

    private function resolveAttachmentUrl(string $source, array $attachmentMap, string $ticketId): string
    {
        $source = trim($source);
        if ($source === '') {
            return '';
        }

        if (preg_match('/^!\[[^\]]*\]\(([^)]+)\)$/', $source, $matches) === 1) {
            $source = trim((string) ($matches[1] ?? ''));
        }

        if (preg_match('/^<([^>]+)>$/', $source, $matches) === 1) {
            $source = trim((string) ($matches[1] ?? ''));
        }

        if ($source === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $source)) {
            return $source;
        }

        if (str_starts_with($source, '/')) {
            return rtrim($this->youTrackUrl, '/') . $source;
        }

        foreach ([$source, basename($source)] as $candidate) {
            $lookupKey = $this->normalizeAttachmentLookupKey($candidate);
            if ($lookupKey !== '' && isset($attachmentMap[$lookupKey])) {
                return $attachmentMap[$lookupKey];
            }

            $candidateWithoutExt = preg_replace('/\.[^.]+$/', '', $candidate);
            if (is_string($candidateWithoutExt) && $candidateWithoutExt !== '') {
                $lookupKey = $this->normalizeAttachmentLookupKey($candidateWithoutExt);
                if ($lookupKey !== '' && isset($attachmentMap[$lookupKey])) {
                    return $attachmentMap[$lookupKey];
                }
            }
        }

        return rtrim($this->youTrackUrl, '/') . '/api/issues/' . rawurlencode($ticketId) . '/attachments/' . ltrim($source, '/');
    }

    private function normalizeAttachmentLookupKey(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/[#?].*$/', '', $value) ?? $value;

        return mb_strtolower(trim($value));
    }

    private function getIssueCustomFieldText(array $issue, string $fieldName): string
    {
        $value = $this->getIssueCustomFieldValue($issue, $fieldName);
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return '';
        }

        foreach (['presentation', 'fullName', 'name', 'login', 'text'] as $key) {
            $text = trim((string) ($value[$key] ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function getIssueCustomFieldDate(array $issue): string
    {
        foreach ([self::DUE_DATE_FIELD] as $fieldName) {
            $value = $this->getIssueCustomFieldValue($issue, $fieldName);
            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                $timestampMs = (int) $value;
                if ($timestampMs > 0) {
                    return date('d/m/Y', (int) floor($timestampMs / 1000));
                }
            }
        }

        return '';
    }

    private function getIssueCustomFieldValue(array $issue, string $fieldName): mixed
    {
        foreach ((array) ($issue['customFields'] ?? []) as $customField) {
            if (!is_array($customField)) {
                continue;
            }

            $name = trim((string) ($customField['name'] ?? ''));
            if ($this->normalizeCompareValue($name) !== $this->normalizeCompareValue($fieldName)) {
                continue;
            }

            return $customField['value'] ?? null;
        }

        return null;
    }

    private function resolveUserLabel(array $user): string
    {
        foreach (['fullName', 'name', 'login'] as $key) {
            $value = trim((string) ($user[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function formatYouTrackDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $timestampMs = (int) $value;
            if ($timestampMs > 0) {
                return date('d/m/Y H:i', (int) floor($timestampMs / 1000));
            }
        }

        return '';
    }

    private function buildIssueUrl(string $idReadable): string
    {
        return rtrim($this->youTrackUrl, '/') . '/issue/' . rawurlencode(trim($idReadable));
    }

    private function extractIssueNumber(string $idReadable): int
    {
        if (preg_match('/-(\d+)$/', $idReadable, $matches) !== 1) {
            return 0;
        }

        return (int) ($matches[1] ?? 0);
    }

    private function extractProjectKey(string $idReadable): string
    {
        if (preg_match('/^([A-Za-z0-9_]+)-\d+$/', trim($idReadable), $matches) !== 1) {
            return '';
        }

        return trim((string) ($matches[1] ?? ''));
    }

    private function isResolvedState(string $state): bool
    {
        $normalized = $this->normalizeCompareValue($state);
        if ($normalized === '') {
            return false;
        }

        foreach (self::RESOLVED_STATE_WORDS as $word) {
            if (str_contains($normalized, $this->normalizeCompareValue($word))) {
                return true;
            }
        }

        return false;
    }

    private function normalizeCompareValue(string $value): string
    {
        $value = trim($value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }
        $value = preg_replace('/\s+/', ' ', $value);

        return mb_strtolower((string) $value);
    }

    private function isAdminUser(Utilisateur $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true)
            || $this->normalizeCompareValue($user->getEffectiveProfileType()) === 'admin';
    }

    private function request(string $method, string $path, array $options = []): array
    {
        $headers = array_merge(
            [
                'Authorization' => 'Bearer ' . $this->youTrackToken,
                'Accept' => 'application/json',
            ],
            is_array($options['headers'] ?? null) ? $options['headers'] : [],
        );
        $options['headers'] = $headers;
        $options['timeout'] = $options['timeout'] ?? 30;
        $options['verify_peer'] = $options['verify_peer'] ?? false;
        $options['verify_host'] = $options['verify_host'] ?? false;

        try {
            $response = $this->httpClient->request($method, rtrim($this->youTrackUrl, '/') . $path, $options);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            return [
                '_error' => 'Erreur de connexion YouTrack.',
                '_detail' => $exception->getMessage(),
            ];
        }

        $decoded = json_decode($content, true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($statusCode < 200 || $statusCode >= 300) {
            return [
                '_error' => 'HTTP ' . $statusCode,
                '_detail' => trim((string) ($decoded['error_description'] ?? $decoded['error'] ?? $decoded['message'] ?? $content)),
            ];
        }

        return $decoded;
    }
}
