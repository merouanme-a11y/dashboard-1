<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MicrosoftGraphAuthService
{
    private const SESSION_STATE_KEY = 'bi.microsoft_oauth.state';
    private const SESSION_TOKEN_KEY = 'bi.microsoft_oauth.tokens';
    private const GRAPH_SCOPE = 'https://graph.microsoft.com/Files.ReadWrite';

    public function __construct(
        private HttpClientInterface $httpClient,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
        private string $tenantId,
        private string $clientId,
        private string $clientSecret,
    ) {}

    public function isConfigured(): bool
    {
        return trim($this->tenantId) !== ''
            && trim($this->clientId) !== ''
            && trim($this->clientSecret) !== '';
    }

    public function hasConnectedAccount(): bool
    {
        $tokens = $this->getStoredTokens();

        return ($tokens['access_token'] ?? '') !== '' || ($tokens['refresh_token'] ?? '') !== '';
    }

    public function getConnectionStatus(): array
    {
        $tokens = $this->getStoredTokens();

        return [
            'configured' => $this->isConfigured(),
            'connected' => $this->hasConnectedAccount(),
            'displayName' => trim((string) ($tokens['user']['name'] ?? '')),
            'email' => trim((string) ($tokens['user']['email'] ?? '')),
            'expiresAt' => !empty($tokens['expires_at']) ? date(DATE_ATOM, (int) $tokens['expires_at']) : '',
        ];
    }

    public function getAuthorizationUrl(): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('La connexion Microsoft n est pas configuree. Ajoutez MICROSOFT_TENANT_ID, MICROSOFT_CLIENT_ID et MICROSOFT_CLIENT_SECRET.');
        }

        $session = $this->requireSession();
        $state = bin2hex(random_bytes(24));
        $session->set(self::SESSION_STATE_KEY, $state);

        $query = http_build_query([
            'client_id' => trim($this->clientId),
            'response_type' => 'code',
            'redirect_uri' => $this->getRedirectUri(),
            'response_mode' => 'query',
            'scope' => $this->getRequestedScopes(),
            'state' => $state,
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);

        return $this->getAuthorityBaseUrl() . '/authorize?' . $query;
    }

    public function handleAuthorizationCallback(string $code, string $state): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('La connexion Microsoft n est pas configuree.');
        }

        $session = $this->requireSession();
        $expectedState = (string) $session->get(self::SESSION_STATE_KEY, '');
        $session->remove(self::SESSION_STATE_KEY);

        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new \RuntimeException('La validation de la connexion Microsoft a echoue. Reessayez.');
        }

        if (trim($code) === '') {
            throw new \RuntimeException('Le code d autorisation Microsoft est manquant.');
        }

        $payload = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => trim($code),
            'redirect_uri' => $this->getRedirectUri(),
        ]);

        $this->storeTokens($payload);
    }

    public function disconnect(): void
    {
        $session = $this->getSession();
        if (!$session) {
            return;
        }

        $session->remove(self::SESSION_STATE_KEY);
        $session->remove(self::SESSION_TOKEN_KEY);
    }

    public function downloadSharedFile(string $sharingUrl): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('La connexion Microsoft n est pas configuree sur le dashboard.');
        }

        $accessToken = $this->getAccessToken();
        if ($accessToken === null) {
            throw new \RuntimeException('Connectez votre compte Microsoft dans les parametres BI pour lire ce fichier SharePoint prive.');
        }

        $download = $this->requestGraphFileDownload($sharingUrl, $accessToken);
        if (($download['status'] ?? 0) === 401) {
            $accessToken = $this->getAccessToken(true);
            if ($accessToken === null) {
                throw new \RuntimeException('La session Microsoft a expire. Reconnectez-vous dans les parametres BI.');
            }

            $download = $this->requestGraphFileDownload($sharingUrl, $accessToken);
        }

        $statusCode = (int) ($download['status'] ?? 0);
        if ($statusCode >= 400) {
            throw new \RuntimeException($this->buildGraphDownloadErrorMessage($download));
        }

        return [
            'content' => (string) ($download['content'] ?? ''),
            'contentType' => (string) ($download['contentType'] ?? ''),
        ];
    }

    private function getAccessToken(bool $forceRefresh = false): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $tokens = $this->getStoredTokens();
        if ($tokens === []) {
            return null;
        }

        $expiresAt = (int) ($tokens['expires_at'] ?? 0);
        $accessToken = trim((string) ($tokens['access_token'] ?? ''));
        if (!$forceRefresh && $accessToken !== '' && ($expiresAt === 0 || $expiresAt > (time() + 60))) {
            return $accessToken;
        }

        $refreshToken = trim((string) ($tokens['refresh_token'] ?? ''));
        if ($refreshToken === '') {
            $this->disconnect();

            return null;
        }

        try {
            $payload = $this->requestToken([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'redirect_uri' => $this->getRedirectUri(),
            ]);
        } catch (\Throwable) {
            $this->disconnect();

            return null;
        }

        $this->storeTokens($payload, $tokens);
        $refreshedTokens = $this->getStoredTokens();

        return trim((string) ($refreshedTokens['access_token'] ?? '')) ?: null;
    }

    private function requestToken(array $parameters): array
    {
        $payload = array_merge([
            'client_id' => trim($this->clientId),
            'client_secret' => trim($this->clientSecret),
            'scope' => $this->getRequestedScopes(),
        ], $parameters);

        try {
            $response = $this->httpClient->request('POST', $this->getAuthorityBaseUrl() . '/token', [
                'body' => $payload,
            ]);
            $content = $response->getContent(false);
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Impossible de finaliser la connexion Microsoft.', 0, $exception);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400 || !is_array($data)) {
            throw new \RuntimeException($this->buildTokenErrorMessage(is_array($data) ? $data : []));
        }

        return $data;
    }

    private function storeTokens(array $payload, array $currentTokens = []): void
    {
        $session = $this->requireSession();
        $claims = $this->decodeJwtClaims((string) ($payload['id_token'] ?? ''));
        $currentUser = is_array($currentTokens['user'] ?? null) ? $currentTokens['user'] : [];

        $session->set(self::SESSION_TOKEN_KEY, [
            'access_token' => trim((string) ($payload['access_token'] ?? '')),
            'refresh_token' => trim((string) ($payload['refresh_token'] ?? ($currentTokens['refresh_token'] ?? ''))),
            'expires_at' => time() + max(0, ((int) ($payload['expires_in'] ?? 3600)) - 60),
            'scope' => trim((string) ($payload['scope'] ?? '')),
            'user' => [
                'name' => trim((string) ($claims['name'] ?? $currentUser['name'] ?? '')),
                'email' => trim((string) ($claims['preferred_username'] ?? $claims['email'] ?? $claims['upn'] ?? $currentUser['email'] ?? '')),
            ],
        ]);
    }

    private function requestGraphFileDownload(string $sharingUrl, string $accessToken): array
    {
        $encodedSharingUrl = $this->encodeSharingUrl($sharingUrl);
        $graphUrl = 'https://graph.microsoft.com/v1.0/shares/' . rawurlencode($encodedSharingUrl) . '/driveItem/content';

        try {
            $response = $this->httpClient->request('GET', $graphUrl, [
                'auth_bearer' => $accessToken,
                'headers' => [
                    'Prefer' => 'redeemSharingLinkIfNecessary',
                ],
                'max_redirects' => 0,
            ]);
            $statusCode = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $contentType = (string) ($headers['content-type'][0] ?? '');

            if (in_array($statusCode, [301, 302, 303, 307, 308], true)) {
                $location = trim((string) ($headers['location'][0] ?? ''));
                if ($location === '') {
                    return [
                        'status' => 502,
                        'content' => '',
                        'contentType' => 'text/plain',
                        'errorMessage' => 'La redirection Microsoft Graph vers le fichier SharePoint est invalide.',
                    ];
                }

                $downloadResponse = $this->httpClient->request('GET', $location);

                return [
                    'status' => $downloadResponse->getStatusCode(),
                    'content' => $downloadResponse->getContent(false),
                    'contentType' => (string) (($downloadResponse->getHeaders(false)['content-type'][0] ?? '')),
                ];
            }

            return [
                'status' => $statusCode,
                'content' => $response->getContent(false),
                'contentType' => $contentType,
            ];
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Impossible de lire le fichier SharePoint avec Microsoft Graph.', 0, $exception);
        }
    }

    private function buildTokenErrorMessage(array $payload): string
    {
        $error = trim((string) ($payload['error'] ?? ''));
        $description = trim((string) ($payload['error_description'] ?? ''));

        if ($error === 'invalid_grant') {
            return 'La session Microsoft a expire ou a ete refusee. Reconnectez-vous dans les parametres BI.';
        }

        if ($description !== '') {
            return 'Connexion Microsoft impossible: ' . $this->sanitizeMicrosoftMessage($description);
        }

        return 'Connexion Microsoft impossible.';
    }

    private function buildGraphDownloadErrorMessage(array $download): string
    {
        $statusCode = (int) ($download['status'] ?? 0);
        $content = (string) ($download['content'] ?? '');
        $payload = json_decode($content, true);
        $message = is_array($payload)
            ? trim((string) ($payload['error']['message'] ?? $payload['message'] ?? ''))
            : '';

        if (in_array($statusCode, [401, 403], true)) {
            if ($message !== '') {
                return 'Microsoft Graph refuse l acces au fichier SharePoint: ' . $this->sanitizeMicrosoftMessage($message);
            }

            return sprintf('Microsoft Graph refuse l acces au fichier SharePoint (HTTP %d).', $statusCode);
        }

        if ($statusCode === 404) {
            return 'Le fichier SharePoint est introuvable via Microsoft Graph (HTTP 404).';
        }

        if (($download['errorMessage'] ?? '') !== '') {
            return (string) $download['errorMessage'];
        }

        if ($message !== '') {
            return 'Impossible de lire le fichier SharePoint via Microsoft Graph: ' . $this->sanitizeMicrosoftMessage($message);
        }

        return sprintf('Impossible de lire le fichier SharePoint via Microsoft Graph (HTTP %d).', $statusCode);
    }

    private function encodeSharingUrl(string $sharingUrl): string
    {
        $base64 = base64_encode($sharingUrl);
        if ($base64 === false) {
            throw new \RuntimeException('Encodage du lien SharePoint impossible.');
        }

        return 'u!' . rtrim(strtr($base64, '+/', '-_'), '=');
    }

    private function decodeJwtClaims(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return [];
        }

        $payload = strtr($parts[1], '-_', '+/');
        $padding = strlen($payload) % 4;
        if ($padding > 0) {
            $payload .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return [];
        }

        $claims = json_decode($decoded, true);

        return is_array($claims) ? $claims : [];
    }

    private function getRequestedScopes(): string
    {
        return implode(' ', [
            'openid',
            'profile',
            'email',
            'offline_access',
            self::GRAPH_SCOPE,
        ]);
    }

    private function getRedirectUri(): string
    {
        return $this->urlGenerator->generate('app_bi_microsoft_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function getAuthorityBaseUrl(): string
    {
        return 'https://login.microsoftonline.com/' . rawurlencode(trim($this->tenantId));
    }

    private function getStoredTokens(): array
    {
        $session = $this->getSession();
        $tokens = $session?->get(self::SESSION_TOKEN_KEY, []);

        return is_array($tokens) ? $tokens : [];
    }

    private function requireSession(): SessionInterface
    {
        $session = $this->getSession();
        if (!$session instanceof SessionInterface) {
            throw new \RuntimeException('La session Symfony est indisponible pour la connexion Microsoft.');
        }

        return $session;
    }

    private function getSession(): ?SessionInterface
    {
        try {
            $request = $this->requestStack->getCurrentRequest();
            if ($request !== null && $request->hasSession()) {
                return $request->getSession();
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function sanitizeMicrosoftMessage(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message)) ?? '';

        return preg_replace('/\[[^\]]*\]/', '', $message) ?? $message;
    }
}
