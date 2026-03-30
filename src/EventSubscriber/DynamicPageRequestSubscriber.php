<?php

namespace App\EventSubscriber;

use App\Service\DynamicPageService;
use App\Service\ModuleService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DynamicPageRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private DynamicPageService $dynamicPageService,
        private ModuleService $moduleService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ((string) $request->attributes->get('_route', '') !== DynamicPageService::PUBLIC_ROUTE) {
            return;
        }

        if (!$this->moduleService->isActive(DynamicPageService::PAGES_MODULE)) {
            return;
        }

        $slug = trim((string) $request->attributes->get('slug', ''));
        $page = $this->dynamicPageService->findActiveBySlug($slug);
        if (!$page) {
            return;
        }

        $request->attributes->set('_managed_page_path', $this->dynamicPageService->buildManagedPagePath($page));
        $request->attributes->set('_dynamic_page', $page);
    }
}
