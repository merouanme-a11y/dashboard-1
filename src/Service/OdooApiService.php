<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OdooApiService
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getCustomers(): array
    {
        return []; // Placeholder
    }
}
