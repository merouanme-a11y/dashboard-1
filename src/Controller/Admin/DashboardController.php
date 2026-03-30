<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Department;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\Admin\UtilisateurCrudController;
use App\Controller\Admin\DepartmentCrudController;
use Symfony\Component\Routing\Attribute\Route;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/my_dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('ERP Admin Backend');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkTo(UtilisateurCrudController::class, 'Utilisateurs', 'fas fa-users');
        yield MenuItem::linkTo(DepartmentCrudController::class, 'Départements', 'fas fa-building');
        yield MenuItem::linkToRoute('Retour au Site', 'fas fa-arrow-left', 'app_dashboard');
    }
}
