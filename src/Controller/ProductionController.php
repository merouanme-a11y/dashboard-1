<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProductionController extends AbstractController
{
    #[Route('/production', name: 'app_production')]
    public function index(): Response
    {
        return $this->render('production/index.html.twig', [
            'controller_name' => 'ProductionController',
        ]);
    }
}
