<?php

namespace App\HttpCache;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;

final class AppCache extends HttpCache
{
    protected function invalidate(Request $request, bool $catch = false): Response
    {
        return parent::invalidate($request, $catch);
    }
}
