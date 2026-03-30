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

    private function createService(PagePermissionService $pagePermissionService): MenuConfigService
    {
        $router = $this->createMock(RouterInterface::class);
        $router
            ->method('generate')
            ->with('app_profile_create', [])
            ->willReturn('/profile/create');

        $pageDisplayService = $this->createStub(PageDisplayService::class);
        $pageDisplayService
            ->method('getConfigurablePages')
            ->willReturn([
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
            ->willReturn('Creer un utilisateur');
        $pageDisplayService
            ->method('renderNavigationIcon')
            ->willReturn('<i class="bi bi-person-plus"></i>');

        $moduleService = $this->createStub(ModuleService::class);
        $moduleService
            ->method('getAllModules')
            ->willReturn([]);

        return new MenuConfigService(
            $this->createStub(KernelInterface::class),
            $router,
            $this->createStub(UtilisateurRepository::class),
            $moduleService,
            $pageDisplayService,
            $pagePermissionService,
        );
    }
}
