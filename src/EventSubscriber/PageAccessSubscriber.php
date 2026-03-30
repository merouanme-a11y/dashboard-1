<?php

namespace App\EventSubscriber;

use App\Entity\Utilisateur;
use App\Service\MenuConfigService;
use App\Service\PagePermissionService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class PageAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private MenuConfigService $menuConfigService,
        private PagePermissionService $pagePermissionService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = trim((string) $request->attributes->get('_managed_page_path', $request->attributes->get('_route', '')));
        if ($routeName === '' || !$this->pagePermissionService->isManagedRoute($routeName)) {
            return;
        }

        $user = $this->security->getUser();
        if (!($user instanceof Utilisateur)) {
            return;
        }

        if ($this->pagePermissionService->canAccessRoute($routeName, $user)) {
            return;
        }

        $targetRoute = $this->menuConfigService->getFirstAccessibleRouteName($user);
        $targetUrl = $this->menuConfigService->getFirstAccessibleUrl($user);
        if ($targetRoute !== '' && $targetRoute !== $routeName && $targetUrl !== '') {
            $event->setController(static fn (): RedirectResponse => new RedirectResponse($targetUrl));

            return;
        }

        $event->setController(static fn (): Response => new Response('Acces refuse', Response::HTTP_FORBIDDEN));
    }
}
