<?php

namespace Condoedge\Utils\Services;

use Illuminate\Support\Facades\Http;

abstract class AbstractApiClientService
{
    protected $name = 'API Service';
    protected $baseUrl;
    protected $apiKey;

    public function __construct($apiKey = null)
    {
        $this->baseUrl = $this->getBaseUrl();
        $this->apiKey = $apiKey;
    }

    protected function request($method, $endpoint, $data = [])
    {
        $baseUrl = rtrim($this->baseUrl, '/');
        $endpoint = ltrim($endpoint, '/');

        $url = $baseUrl . '/' . $endpoint;

        $response = Http::acceptJson()
            ->withToken($this->apiKey)
            ->$method($url, $data);

        if ($response->failed()) {
            throw new \Exception($this->name . ' request failed: ' . $response->body());
        }

        return $response->json();
    }

    abstract protected function getBaseUrl();
}