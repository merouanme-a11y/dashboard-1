<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Utilisateur;
use App\EventSubscriber\DynamicPageRequestSubscriber;
use App\EventSubscriber\PageAccessSubscriber;
use App\Service\DynamicPageService;
use App\Service\MenuConfigService;
use App\Service\ModuleService;
use App\Service\PagePermissionService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PageAccessSubscriberTest extends TestCase
{
    public function testAllowedManagedRouteKeepsOriginalController(): void
    {
        $user = (new Utilisateur())
            ->setRoles(['ROLE_ADMIN'])
            ->setProfileType('Admin');

        $security = $this->createMock(Security::class);
        $security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $menuConfigService = $this->createStub(MenuConfigService::class);

        $pagePermissionService = $this->createMock(PagePermissionService::class);
        $pagePermissionService
            ->expects($this->once())
            ->method('isManagedRoute')
            ->with('dynamic_page_15')
            ->willReturn(true);
        $pagePermissionService
            ->expects($this->once())
            ->method('canAccessRoute')
            ->with('dynamic_page_15', $user)
            ->willReturn(true);

        $subscriber = new PageAccessSubscriber(
            $security,
            $menuConfigService,
            $pagePermissionService,
        );

        $request = new Request();
        $request->attributes->set('_route', 'app_dynamic_page_show');
        $request->attributes->set('_managed_page_path', 'dynamic_page_15');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $controller = static fn () => null;
        $event = new ControllerEvent(
            $kernel,
            $controller,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $subscriber->onKernelController($event);

        self::assertSame($controller, $event->getController());
    }

    public function testDynamicPageRequestSubscriberSetsManagedPagePathForActivePage(): void
    {
        $page = new \App\Entity\Page();
        $this->assignPageId($page, 15);
        $page->setSlug('procedure-rh')->setTitle('Procedure RH')->setIsActive(true);

        $dynamicPageService = $this->createMock(DynamicPageService::class);
        $dynamicPageService
            ->expects($this->once())
            ->method('findActiveBySlug')
            ->with('procedure-rh')
            ->willReturn($page);
        $dynamicPageService
            ->expects($this->once())
            ->method('buildManagedPagePath')
            ->with($page)
            ->willReturn('dynamic_page_15');

        $moduleService = $this->createMock(ModuleService::class);
        $moduleService
            ->expects($this->once())
            ->method('isActive')
            ->with(DynamicPageService::PAGES_MODULE)
            ->willReturn(true);

        $subscriber = new DynamicPageRequestSubscriber($dynamicPageService, $moduleService);

        $request = new Request();
        $request->attributes->set('_route', DynamicPageService::PUBLIC_ROUTE);
        $request->attributes->set('slug', 'procedure-rh');

        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $subscriber->onKernelRequest($event);

        self::assertSame('dynamic_page_15', $request->attributes->get('_managed_page_path'));
        self::assertSame($page, $request->attributes->get('_dynamic_page'));
    }

    private function assignPageId(\App\Entity\Page $page, int $id): void
    {
        $reflectionProperty = new \ReflectionProperty($page, 'id');
        $reflectionProperty->setValue($page, $id);
    }
}
