<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/permissions')]
#[IsGranted('ROLE_ADMIN')]
final class PermissionController extends AbstractController
{
    #[Route('', name: 'admin_permissions')]
    public function index(): RedirectResponse
    {
        $this->addFlash('success', 'La gestion des droits est maintenant centralisee dans la page Menus.');

        return $this->redirectToRoute('admin_menus');
    }
}
