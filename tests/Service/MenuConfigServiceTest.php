<?php

namespace App\Tests\Service;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\MenuConfigService;
use App\Service\ModuleService;
use App\Service\PageDisplayService;
use App\Service\PagePermissionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

final class MenuConfigServiceTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $temporaryProjectDirs = [];

    public function testProfileCreateRouteIsHiddenForNonAdminUsers(): void
    {
        $pagePermissionService = $this->createMock(PagePermissionService::class);
        $pagePermissionService
            ->expects($this->never())
            ->method('canAccessRoute');

        $service = $this->createService($pagePermissionService);

        $user = (new Utilisateur())
            ->setRoles(['ROLE_EMPLOYE'])
            ->setProfileType('Employe');

        self::assertFalse($service->canUserAccessRoute($user, 'app_profile_create'));
    }

    public function testProfileCreateRouteIsVisibleForAdminWhenPagePermissionAllowsIt(): void
    {
        $user = (new Utilisateur())
            ->setRoles(['ROLE_ADMIN'])
            ->setProfileType('Admin');

        $pagePermissionService = $this->createMock(PagePermissionService::class);
        $pagePermissionService
            ->expects($this->once())
            ->method('canAccessRoute')
            ->with('app_profile_create', $user)
            ->willReturn(true);

        $service = $this->createService($pagePermissionService);

        self::assertTrue($service->canUserAccessRoute($user, 'app_profile_create'));
    }

    public function testExplicitProfileSidebarMenuDoesNotReintroduceDefaultItems(): void
    {
        $projectDir = $this->createProjectDirWithMenuConfig([
            'menus' => [
                'sidebar' => [
                    'default' => [],
                    'profiles' => [
                        'Responsable' => [
                            ['page' => 'app_stats'],
                        ],
                    ],
                    'users' => [],
                ],
            ],
        ]);

        $service = $this->createService(
            $this->createStub(PagePermissionService::class),
            projectDir: $projectDir,
            configurablePages: $this->buildConfigurablePages(),
        );

        $resolved = $service->resolveMenuConfigForSelector('profile', 'Responsable', 'sidebar');

        self::assertSame('profile', $resolved['source']);
        self::assertSame(['app_stats'], array_column($resolved['menu'], 'page'));
    }

    public function testUserInheritedProfileSidebarMenuDoesNotReintroduceDefaultItems(): void
    {
        $user = (new Utilisateur())
            ->setRoles(['ROLE_RESPONSABLE'])
            ->setProfileType('Responsable');
        $this->assignUserId($user, 5);

        $userRepository = $this->createMock(UtilisateurRepository::class);
        $userRepository
            ->method('find')
            ->with(5)
            ->willReturn($user);

        $projectDir = $this->createProjectDirWithMenuConfig([
            'menus' => [
                'sidebar' => [
                    'default' => [],
                    'profiles' => [
                        'Responsable' => [
                            ['page' => 'app_stats'],
                        ],
                    ],
                    'users' => [],
                ],
            ],
        ]);

        $service = $this->createService(
            $this->createStub(PagePermissionService::class),
            utilisateurRepository: $userRepository,
            projectDir: $projectDir,
            configurablePages: $this->buildConfigurablePages(),
        );

        $resolved = $service->resolveMenuConfigForSelector('user', '5', 'sidebar');

        self::assertSame('profile', $resolved['source']);
        self::assertSame('Responsable', $resolved['inherited_profile']);
        self::assertSame(['app_stats'], array_column($resolved['menu'], 'page'));
    }

    public function testDefaultSidebarMenuStillCompletesMissingBaseEntries(): void
    {
        $projectDir = $this->createProjectDirWithMenuConfig([
            'menus' => [
                'sidebar' => [
                    'default' => [
                        ['page' => 'app_stats'],
                    ],
                    'profiles' => [],
                    'users' => [],
                ],
            ],
        ]);

        $service = $this->createService(
            $this->createStub(PagePermissionService::class),
            projectDir: $projectDir,
            configurablePages: $this->buildConfigurablePages(),
        );

        $resolved = $service->resolveMenuConfigForSelector('', '', 'sidebar');

        self::assertSame('default', $resolved['source']);
        self::assertSame(['app_stats', 'app_dashboard'], array_column($resolved['menu'], 'page'));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryProjectDirs as $projectDir) {
            if (!is_dir($projectDir)) {
                continue;
            }

            $items = array_diff(scandir($projectDir) ?: [], ['.', '..']);
            foreach ($items as $item) {
                $path = $projectDir . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            @rmdir($projectDir);
        }

        $this->temporaryProjectDirs = [];
    }

    private function createService(
        PagePermissionService $pagePermissionService,
        ?UtilisateurRepository $utilisateurRepository = null,
        ?string $projectDir = null,
        ?array $configurablePages = null,
    ): MenuConfigService
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel
            ->method('getProjectDir')
            ->willReturn($projectDir ?? __DIR__);

        $router = $this->createMock(RouterInterface::class);
        $router
            ->method('generate')
            ->willReturnCallback(static function (string $routeName): string {
                return match ($routeName) {
                    'app_dashboard' => '/',
                    'app_stats' => '/stats',
                    'app_annuaire' => '/annuaire',
                    'app_profile_create' => '/profile/create',
                    default => '/' . ltrim(str_replace('_', '-', $routeName), '/'),
                };
            });

        $pageDisplayService = $this->createStub(PageDisplayService::class);
        $pageDisplayService
            ->method('getConfigurablePages')
            ->willReturn($configurablePages ?? [
                [
                    'page_path' => 'app_profile_create',
                    'route_name' => 'app_profile_create',
                    'route_parameters' => [],
                    'route_path' => '/profile/create',
                    'default_title' => 'Creer un utilisateur',
                    'folder' => 'pages',
                ],
            ]);
        $pageDisplayService
            ->method('resolveNavigationLabel')
            ->willReturnCallback(static fn (string $_routeName, string $fallback): string => $fallback);
        $pageDisplayService
            ->method('renderNavigationIcon')
            ->willReturn('<i class="bi bi-person-plus"></i>');
        $pageDisplayService
            ->method('getDefaultIconValue')
            ->willReturn('bi-file-earmark-text');

        $moduleService = $this->createStub(ModuleService::class);
        $moduleService
            ->method('getAllModules')
            ->willReturn([]);
        $moduleService
            ->method('getActiveModules')
            ->willReturn([]);
        $moduleService
            ->method('getModuleForRoute')
            ->willReturn(null);
        $moduleService
            ->method('isRouteEnabled')
            ->willReturn(true);

        if ($utilisateurRepository === null) {
            $utilisateurRepository = $this->createMock(UtilisateurRepository::class);
            $utilisateurRepository
                ->method('findDistinctProfileTypes')
                ->willReturn([]);
            $utilisateurRepository
                ->method('findAllSorted')
                ->willReturn([]);
            $utilisateurRepository
                ->method('find')
                ->willReturn(null);
        }

        return new MenuConfigService(
            $kernel,
            $router,
            $utilisateurRepository,
            $moduleService,
            $pageDisplayService,
            $pagePermissionService,
        );
    }

    private function buildConfigurablePages(): array
    {
        return [
            [
                'page_path' => 'app_dashboard',
                'route_name' => 'app_dashboard',
                'route_parameters' => [],
                'route_path' => '/',
                'default_title' => 'Accueil',
                'folder' => 'pages',
            ],
            [
                'page_path' => 'app_stats',
                'route_name' => 'app_stats',
                'route_parameters' => [],
                'route_path' => '/stats',
                'default_title' => 'Statistiques',
                'folder' => 'pages',
            ],
            [
                'page_path' => 'app_annuaire',
                'route_name' => 'app_annuaire',
                'route_parameters' => [],
                'route_path' => '/annuaire',
                'default_title' => 'Annuaire des contacts',
                'folder' => 'pages',
            ],
            [
                'page_path' => 'app_profile_create',
                'route_name' => 'app_profile_create',
                'route_parameters' => [],
                'route_path' => '/profile/create',
                'default_title' => 'Creer un utilisateur',
                'folder' => 'pages',
            ],
        ];
    }

    private function createProjectDirWithMenuConfig(array $menuConfig): string
    {
        $projectDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dashboard-menu-test-' . bin2hex(random_bytes(6));
        if (!@mkdir($projectDir, 0777, true) && !is_dir($projectDir)) {
            self::fail('Impossible de creer le repertoire de test temporaire.');
        }

        $encoded = json_encode($menuConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || @file_put_contents($projectDir . DIRECTORY_SEPARATOR . 'menu_config.json', $encoded . PHP_EOL) === false) {
            self::fail('Impossible de creer le fichier menu_config.json temporaire.');
        }

        $this->temporaryProjectDirs[] = $projectDir;

        return $projectDir;
    }

    private function assignUserId(Utilisateur $user, int $id): void
    {
        $reflectionProperty = new \ReflectionProperty($user, 'id');
        $reflectionProperty->setValue($user, $id);
    }
}
