<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceEntrepriseController extends AbstractController
{
    #[Route('/serviceentreprise', name: 'app_serviceentreprise')]
    public function index(): Response
    {
        return $this->render('serviceentreprise/index.html.twig', [
            'controller_name' => 'ServiceEntrepriseController',
        ]);
    }
}
