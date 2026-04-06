<?php

namespace App\Service;

use App\Entity\Permission;
use App\Entity\Utilisateur;
use App\Repository\PermissionRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;

class PagePermissionService
{
    private const ALWAYS_ACCESSIBLE_ROUTES = ['admin_menus'];
    private const PROFILE_RESTRICTED_ROUTES = [
        'app_livre_de_caisse_management' => ['Gestionnaire'],
    ];

    /**
     * @var array<string, bool>|null
     */
    private ?array $managedRoutesCache = null;

    /**
     * @var array<int|string, array<string, Permission>>
     */
    private array $userPermissionsCache = [];

    /**
     * @var array<string, array<string, Permission>>
     */
    private array $rolePermissionsCache = [];

    /**
     * @var array<string, array<int, array<string, int|string>>>
     */
    private array $permissionMatrixCache = [];

    public function __construct(
        private PermissionRepository $permissionRepository,
        private UtilisateurRepository $utilisateurRepository,
        private PageDisplayService $pageDisplayService,
        private EntityManagerInterface $em,
    ) {}

    public function getPermissionsForSelector(string $selectorType, string $selectorValue): array
    {
        $selectorType = trim($selectorType);
        $selectorValue = trim($selectorValue);
        $cacheKey = $selectorType . ':' . $selectorValue;

        if (isset($this->permissionMatrixCache[$cacheKey])) {
            return $this->permissionMatrixCache[$cacheKey];
        }

        $pages = $this->getAvailablePermissionPages();

        if ($selectorType === 'profile' && $selectorValue !== '') {
            return $this->permissionMatrixCache[$cacheKey] = $this->buildPermissionMatrix($pages, $this->getRolePermissions($selectorValue));
        }

        if ($selectorType === 'user' && $selectorValue !== '') {
            $user = $this->utilisateurRepository->find((int) $selectorValue);
            if (!($user instanceof Utilisateur)) {
                return [];
            }

            $userPermissions = $this->getUserPermissions($user);
            if ($userPermissions !== []) {
                return $this->permissionMatrixCache[$cacheKey] = $this->buildPermissionMatrix($pages, $userPermissions);
            }

            $profileType = $user->getEffectiveProfileType();
            if ($profileType !== '') {
                return $this->permissionMatrixCache[$cacheKey] = $this->buildPermissionMatrix($pages, $this->getRolePermissions($profileType));
            }

            return $this->permissionMatrixCache[$cacheKey] = $this->buildPermissionMatrix($pages, []);
        }

        return $this->permissionMatrixCache[$cacheKey] = $pages;
    }

    public function updatePermissionForSelector(string $selectorType, string $selectorValue, string $pagePath, bool $isAllowed): bool
    {
        $selectorType = trim($selectorType);
        $selectorValue = trim($selectorValue);
        $pagePath = trim($pagePath);

        if ($pagePath === '' || !$this->isManagedRoute($pagePath)) {
            return false;
        }

        if ($selectorType === 'profile' && $selectorValue !== '') {
            $permission = $this->permissionRepository->findByPagePathAndRole($pagePath, $selectorValue) ?? new Permission();
            $permission->setPagePath($pagePath);
            $permission->setRole($selectorValue);
            $permission->setUtilisateur(null);
            $permission->setIsAllowed($isAllowed);
            $this->em->persist($permission);
            $this->em->flush();
            $this->clearRuntimeCaches();

            return true;
        }

        if ($selectorType === 'user' && $selectorValue !== '') {
            $user = $this->utilisateurRepository->find((int) $selectorValue);
            if (!($user instanceof Utilisateur)) {
                return false;
            }

            $permission = $this->permissionRepository->findByPagePathAndUser($pagePath, $user) ?? new Permission();
            $permission->setPagePath($pagePath);
            $permission->setUtilisateur($user);
            $permission->setRole(null);
            $permission->setIsAllowed($isAllowed);
            $this->em->persist($permission);
            $this->em->flush();
            $this->clearRuntimeCaches();

            return true;
        }

        return false;
    }

    public function canAccessRoute(string $routeName, ?Utilisateur $user): bool
    {
        $routeName = trim($routeName);
        if ($routeName === '' || !$this->isManagedRoute($routeName)) {
            return true;
        }

        if (!($user instanceof Utilisateur)) {
            return false;
        }

        if (in_array($routeName, self::ALWAYS_ACCESSIBLE_ROUTES, true)) {
            return true;
        }

        if (!$this->isAudienceAllowedForRoute($routeName, $user)) {
            return false;
        }

        $userPermissions = $this->getUserPermissions($user);
        if (isset($userPermissions[$routeName])) {
            $userPermission = $userPermissions[$routeName];

            return $userPermission->isAllowed();
        }

        $profileType = $user->getEffectiveProfileType();
        if ($profileType !== '') {
            $rolePermissions = $this->getRolePermissions($profileType);
            if (isset($rolePermissions[$routeName])) {
                $profilePermission = $rolePermissions[$routeName];

                return $profilePermission->isAllowed();
            }
        }

        return true;
    }

    private function isAudienceAllowedForRoute(string $routeName, Utilisateur $user): bool
    {
        $allowedProfiles = self::PROFILE_RESTRICTED_ROUTES[$routeName] ?? null;
        if (!is_array($allowedProfiles) || $allowedProfiles === []) {
            return true;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $effectiveProfileType = trim($user->getEffectiveProfileType());
        if ($effectiveProfileType === '') {
            return false;
        }

        foreach ($allowedProfiles as $allowedProfile) {
            if (strcasecmp($effectiveProfileType, (string) $allowedProfile) === 0) {
                return true;
            }
        }

        return false;
    }

    public function isManagedRoute(string $routeName): bool
    {
        $routeName = trim($routeName);
        if ($routeName === '') {
            return false;
        }

        return isset($this->getManagedRoutes()[$routeName]);
    }

    private function getAvailablePermissionPages(): array
    {
        $pages = [];

        foreach ($this->pageDisplayService->getConfigurablePages() as $page) {
            $pagePath = trim((string) ($page['page_path'] ?? ''));
            if ($pagePath === '') {
                continue;
            }

            $fallbackIcon = $this->pageDisplayService->getDefaultIconValue($pagePath, 'bootstrap', 'bi-file-earmark-text', 'bootstrap');
            $pages[] = [
                'page_path' => $pagePath,
                'page_name' => $this->pageDisplayService->resolveNavigationLabel(
                    $pagePath,
                    (string) ($page['default_title'] ?? $pagePath),
                ),
                'icon_html' => $this->pageDisplayService->renderNavigationIcon($pagePath, $fallbackIcon, 'bootstrap'),
                'is_allowed' => 1,
            ];
        }

        return $pages;
    }

    /**
     * @param array<string, Permission> $existingPermissions
     */
    private function buildPermissionMatrix(array $pages, array $existingPermissions): array
    {
        $matrix = [];

        foreach ($pages as $page) {
            $pagePath = (string) ($page['page_path'] ?? '');
            $permission = $existingPermissions[$pagePath] ?? null;

            $matrix[] = [
                'page_path' => $pagePath,
                'page_name' => (string) ($page['page_name'] ?? $pagePath),
                'icon_html' => (string) ($page['icon_html'] ?? ''),
                'is_allowed' => $permission instanceof Permission ? ($permission->isAllowed() ? 1 : 0) : 1,
            ];
        }

        return $matrix;
    }

    /**
     * @return array<string, bool>
     */
    private function getManagedRoutes(): array
    {
        if (is_array($this->managedRoutesCache)) {
            return $this->managedRoutesCache;
        }

        $managedRoutes = [];
        foreach ($this->pageDisplayService->getConfigurablePages() as $page) {
            $pagePath = trim((string) ($page['page_path'] ?? ''));
            if ($pagePath !== '') {
                $managedRoutes[$pagePath] = true;
            }
        }

        return $this->managedRoutesCache = $managedRoutes;
    }

    /**
     * @return array<string, Permission>
     */
    private function getUserPermissions(Utilisateur $user): array
    {
        $userId = $user->getId();
        if ($userId === null) {
            return [];
        }

        return $this->userPermissionsCache[$userId] ??= $this->permissionRepository->findAllIndexedByUser($user);
    }

    /**
     * @return array<string, Permission>
     */
    private function getRolePermissions(string $role): array
    {
        $role = trim($role);
        if ($role === '') {
            return [];
        }

        return $this->rolePermissionsCache[$role] ??= $this->permissionRepository->findAllIndexedByRole($role);
    }

    private function clearRuntimeCaches(): void
    {
        $this->userPermissionsCache = [];
        $this->rolePermissionsCache = [];
        $this->permissionMatrixCache = [];
    }
}
