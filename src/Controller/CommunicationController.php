<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommunicationController extends AbstractController
{
    #[Route('/communication', name: 'app_communication')]
    public function index(): Response
    {
        return $this->render('communication/index.html.twig', [
            'controller_name' => 'CommunicationController',
        ]);
    }
}
