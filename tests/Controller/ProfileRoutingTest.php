<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProfileRoutingTest extends KernelTestCase
{
    public function testProfileCreatePathMatchesCreateRoute(): void
    {
        self::bootKernel();

        $router = static::getContainer()->get('router');

        $createMatch = $router->match('/profile/create');
        self::assertSame('app_profile_create', $createMatch['_route']);

        $viewMatch = $router->match('/profile/42');
        self::assertSame('app_profile_view', $viewMatch['_route']);
        self::assertSame('42', $viewMatch['id']);
    }
}
