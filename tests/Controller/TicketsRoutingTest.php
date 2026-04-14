<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TicketsRoutingTest extends KernelTestCase
{
    public function testPatchFormPathMatchesDedicatedRoute(): void
    {
        self::bootKernel();

        $router = static::getContainer()->get('router');

        $patchMatch = $router->match('/tickets/formulaire-patch');
        self::assertSame('app_tickets_patch', $patchMatch['_route']);

        $detailMatch = $router->match('/tickets/MTN-42');
        self::assertSame('app_ticket_detail', $detailMatch['_route']);
        self::assertSame('MTN-42', $detailMatch['id']);
    }
}
