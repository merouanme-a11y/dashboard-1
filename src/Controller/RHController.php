<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RHController extends AbstractController
{
    #[Route('/rh', name: 'app_rh')]
    public function index(): Response
    {
        return $this->render('rh/index.html.twig', [
            'controller_name' => 'RHController',
        ]);
    }
}
