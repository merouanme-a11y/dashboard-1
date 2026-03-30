<?php

namespace App\Tests\Service;

use App\Entity\Permission;
use App\Entity\Utilisateur;
use App\Repository\PermissionRepository;
use App\Repository\UtilisateurRepository;
use App\Service\PageDisplayService;
use App\Service\PagePermissionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PagePermissionServiceTest extends TestCase
{
    public function testMenuRouteRemainsAccessibleForAdminUserEvenWhenUserPermissionIsBlocked(): void
    {
        $user = (new Utilisateur())
            ->setRoles(['ROLE_ADMIN'])
            ->setProfileType('Admin');
        $this->assignUserId($user, 99);

        $permission = (new Permission())
            ->setPagePath('admin_menus')
            ->setUtilisateur($user)
            ->setIsAllowed(false);

        $permissionRepository = $this->createMock(PermissionRepository::class);
        $permissionRepository
            ->expects($this->never())
            ->method('findAllIndexedByUser')
            ->willReturn(['admin_menus' => $permission]);

        $utilisateurRepository = $this->createStub(UtilisateurRepository::class);
        $pageDisplayService = $this->createMock(PageDisplayService::class);
        $pageDisplayService
            ->expects($this->once())
            ->method('getConfigurablePages')
            ->willReturn([
                ['page_path' => 'admin_menus', 'default_title' => 'Gestion du menu'],
            ]);

        $service = new PagePermissionService(
            $permissionRepository,
            $utilisateurRepository,
            $pageDisplayService,
            $this->createStub(EntityManagerInterface::class),
        );

        self::assertTrue($service->canAccessRoute('admin_menus', $user));
    }

    public function testNonBypassedAdminRouteStillRespectsBlockedProfilePermission(): void
    {
        $user = (new Utilisateur())
            ->setRoles(['ROLE_ADMIN'])
            ->setProfileType('Admin');
        $this->assignUserId($user, 99);

        $permission = (new Permission())
            ->setPagePath('admin_permissions')
            ->setRole('Admin')
            ->setIsAllowed(false);

        $permissionRepository = $this->createMock(PermissionRepository::class);
        $permissionRepository
            ->expects($this->once())
            ->method('findAllIndexedByUser')
            ->with($user)
            ->willReturn([]);
        $permissionRepository
            ->expects($this->once())
            ->method('findAllIndexedByRole')
            ->with('Admin')
            ->willReturn(['admin_permissions' => $permission]);

        $utilisateurRepository = $this->createStub(UtilisateurRepository::class);
        $pageDisplayService = $this->createMock(PageDisplayService::class);
        $pageDisplayService
            ->expects($this->once())
            ->method('getConfigurablePages')
            ->willReturn([
                ['page_path' => 'admin_permissions', 'default_title' => 'Acces utilisateur'],
            ]);

        $service = new PagePermissionService(
            $permissionRepository,
            $utilisateurRepository,
            $pageDisplayService,
            $this->createStub(EntityManagerInterface::class),
        );

        self::assertFalse($service->canAccessRoute('admin_permissions', $user));
    }

    private function assignUserId(Utilisateur $user, int $id): void
    {
        $reflectionProperty = new \ReflectionProperty($user, 'id');
        $reflectionProperty->setValue($user, $id);
    }
}
