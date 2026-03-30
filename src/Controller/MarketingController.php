<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MarketingController extends AbstractController
{
    #[Route('/marketing', name: 'app_marketing')]
    public function index(): Response
    {
        return $this->render('marketing/index.html.twig', [
            'controller_name' => 'MarketingController',
        ]);
    }
}
