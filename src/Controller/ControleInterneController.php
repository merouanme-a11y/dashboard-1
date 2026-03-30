<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ControleInterneController extends AbstractController
{
    #[Route('/controleinterne', name: 'app_controleinterne')]
    public function index(): Response
    {
        return $this->render('controleinterne/index.html.twig', [
            'controller_name' => 'ControleInterneController',
        ]);
    }
}
