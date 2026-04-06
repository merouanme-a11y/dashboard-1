<?php

namespace App\Service;

use App\Entity\Module;
use App\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;

class ModuleService
{
    private const ROUTE_MODULE_ALIASES = [
        'app_livre_de_caisse_listing' => 'app_livre_de_caisse',
        'app_livre_de_caisse_management' => 'app_livre_de_caisse',
        'app_bi_connections' => 'app_bi',
        'app_bi_files' => 'app_bi',
        'app_bi_dataset' => 'app_bi',
        'app_bi_preferences' => 'app_bi',
        'app_bi_settings' => 'app_bi',
        'app_bi_upload_source' => 'app_bi',
    ];

    /**
     * @var Module[]|null
     */
    private ?array $allModulesCache = null;

    /**
     * @var Module[]|null
     */
    private ?array $activeModulesCache = null;

    /**
     * @var array<string, Module>|null
     */
    private ?array $modulesByNameCache = null;

    /**
     * @var array<string, Module>|null
     */
    private ?array $modulesByRouteCache = null;

    public function __construct(
        private ModuleRepository $moduleRepository,
        private EntityManagerInterface $em,
    ) {}

    public function getActiveModules(): array
    {
        if (is_array($this->activeModulesCache)) {
            return $this->activeModulesCache;
        }

        return $this->activeModulesCache = array_values(array_filter(
            $this->getAllModules(),
            static fn (Module $module): bool => $module->isActive(),
        ));
    }

    public function getAllModules(): array
    {
        if (is_array($this->allModulesCache)) {
            return $this->allModulesCache;
        }

        $modules = $this->moduleRepository->findAllSorted();
        $modulesByName = [];

        foreach ($modules as $module) {
            if ($module instanceof Module && trim((string) $module->getName()) !== '') {
                $modulesByName[(string) $module->getName()] = $module;
            }
        }

        $this->modulesByNameCache = $modulesByName;

        return $this->allModulesCache = $modules;
    }

    public function isActive(string $moduleName): bool
    {
        $module = $this->getModulesByName()[trim($moduleName)] ?? null;

        return $module?->isActive() ?? false;
    }

    public function getModuleForRoute(string $routeName): ?Module
    {
        $routeName = trim($routeName);
        if ($routeName === '') {
            return null;
        }

        $resolvedRouteName = self::ROUTE_MODULE_ALIASES[$routeName] ?? $routeName;

        return $this->getModulesByRoute()[$resolvedRouteName] ?? null;
    }

    public function isRouteEnabled(string $routeName): bool
    {
        $module = $this->getModuleForRoute($routeName);

        return $module?->isActive() ?? true;
    }

    public function toggleModule(string $moduleName): bool
    {
        $module = $this->moduleRepository->findByName($moduleName);
        if (!$module) {
            return false;
        }

        $module->setIsActive(!$module->isActive());
        $this->em->flush();

        $this->clearRuntimeCaches();
        return true;
    }

    public function invalidateCache(): void
    {
        $this->clearRuntimeCaches();
    }

    /**
     * @return array<string, Module>
     */
    public function getModulesByName(): array
    {
        if (is_array($this->modulesByNameCache)) {
            return $this->modulesByNameCache;
        }

        $this->getAllModules();

        return $this->modulesByNameCache ?? [];
    }

    /**
     * @return array<string, Module>
     */
    public function getModulesByRoute(): array
    {
        if (is_array($this->modulesByRouteCache)) {
            return $this->modulesByRouteCache;
        }

        $modulesByRoute = [];
        foreach ($this->getAllModules() as $module) {
            $routeName = trim((string) $module->getRouteName());
            if ($routeName !== '') {
                $modulesByRoute[$routeName] = $module;
            }
        }

        return $this->modulesByRouteCache = $modulesByRoute;
    }

    private function clearRuntimeCaches(): void
    {
        $this->allModulesCache = null;
        $this->activeModulesCache = null;
        $this->modulesByNameCache = null;
        $this->modulesByRouteCache = null;
    }
}
