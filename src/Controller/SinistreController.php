<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SinistreController extends AbstractController
{
    #[Route('/sinistre', name: 'app_sinistre')]
    public function index(): Response
    {
        return $this->render('sinistre/index.html.twig', [
            'controller_name' => 'SinistreController',
        ]);
    }
}
