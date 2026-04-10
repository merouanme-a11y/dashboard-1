<?php

namespace App\Tests\Service;

use App\Service\MicrosoftGraphAuthService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AllowMockObjectsWithoutExpectations]
final class MicrosoftGraphAuthServiceTest extends TestCase
{
    public function testGetAuthorizationUrlStoresStateAndScopes(): void
    {
        [$requestStack, $session] = $this->createRequestContext();
        $service = new MicrosoftGraphAuthService(
            new MockHttpClient([]),
            $requestStack,
            $this->createUrlGeneratorMock(),
            'organizations',
            'client-id',
            'client-secret',
        );

        $authorizationUrl = $service->getAuthorizationUrl();

        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);

        self::assertStringStartsWith('https://login.microsoftonline.com/organizations/authorize?', $authorizationUrl);
        self::assertSame('client-id', $query['client_id'] ?? null);
        self::assertSame('code', $query['response_type'] ?? null);
        self::assertSame('http://localhost/dashboard/bi/microsoft/callback', $query['redirect_uri'] ?? null);
        self::assertStringContainsString('offline_access', (string) ($query['scope'] ?? ''));
        self::assertStringContainsString('https://graph.microsoft.com/Files.ReadWrite', (string) ($query['scope'] ?? ''));
        self::assertSame($session->get('bi.microsoft_oauth.state'), $query['state'] ?? null);
    }

    public function testHandleAuthorizationCallbackStoresMicrosoftUserInSession(): void
    {
        [$requestStack, $session] = $this->createRequestContext();
        $session->set('bi.microsoft_oauth.state', 'expected-state');

        $service = new MicrosoftGraphAuthService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'access_token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                    'expires_in' => 3600,
                    'scope' => 'openid profile email offline_access https://graph.microsoft.com/Files.ReadWrite',
                    'id_token' => $this->createJwt([
                        'name' => 'Merouan Hamzaoui',
                        'preferred_username' => 'm.hamzaoui@adep.com',
                    ]),
                ], JSON_THROW_ON_ERROR)),
            ]),
            $requestStack,
            $this->createUrlGeneratorMock(),
            'organizations',
            'client-id',
            'client-secret',
        );

        $service->handleAuthorizationCallback('code-123', 'expected-state');

        $status = $service->getConnectionStatus();

        self::assertTrue($status['configured']);
        self::assertTrue($status['connected']);
        self::assertSame('Merouan Hamzaoui', $status['displayName']);
        self::assertSame('m.hamzaoui@adep.com', $status['email']);
        self::assertNotSame('', $status['expiresAt']);
    }

    public function testDownloadSharedFileUsesStoredMicrosoftSession(): void
    {
        [$requestStack, $session] = $this->createRequestContext();
        $session->set('bi.microsoft_oauth.tokens', [
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => time() + 3600,
            'scope' => 'openid profile email offline_access https://graph.microsoft.com/Files.ReadWrite',
            'user' => [
                'name' => 'Merouan Hamzaoui',
                'email' => 'm.hamzaoui@adep.com',
            ],
        ]);

        $service = new MicrosoftGraphAuthService(
            new MockHttpClient([
                new MockResponse('', [
                    'http_code' => 302,
                    'response_headers' => [
                        'location: https://download.example.test/file.csv',
                        'content-type: application/octet-stream',
                    ],
                ]),
                new MockResponse("nom;quantite\nAlice;12\n", [
                    'response_headers' => [
                        'content-type: text/csv; charset=utf-8',
                    ],
                ]),
            ]),
            $requestStack,
            $this->createUrlGeneratorMock(),
            'organizations',
            'client-id',
            'client-secret',
        );

        $download = $service->downloadSharedFile('https://adep0.sharepoint.com/sites/ServiceIT/Documents%20partages/Tickets/issues.csv');

        self::assertSame("nom;quantite\nAlice;12\n", $download['content'] ?? null);
        self::assertSame('text/csv; charset=utf-8', $download['contentType'] ?? null);
    }

    /**
     * @return array{0: RequestStack, 1: Session}
     */
    private function createRequestContext(): array
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();

        $request = Request::create('http://localhost/dashboard/bi');
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return [$requestStack, $session];
    }

    private function createUrlGeneratorMock(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->with('app_bi_microsoft_callback', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost/dashboard/bi/microsoft/callback');

        return $urlGenerator;
    }

    private function createJwt(array $claims): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'none', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));

        return $header . '.' . $payload . '.';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
