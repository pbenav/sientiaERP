<?php

namespace App\PosTui;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class PosClient
{
    private Client $http;
    private string $baseUrl;
    private ?string $token = null;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        
        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 2.0,
            'connect_timeout' => 1.0,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function login(string $email, string $password): array
    {
        $response = $this->http->post('/api/pos/login', [
            'json' => compact('email', 'password'),
        ]);

        if ($response->getStatusCode() !== 200) {
            $error = json_decode($response->getBody(), true);
            throw new \Exception($error['error'] ?? 'Error de autenticación');
        }

        $data = json_decode($response->getBody(), true);
        $this->token = $data['token'];
        
        return $data;
    }

    public function getProduct(string $code): array
    {
        return $this->request('GET', "/api/pos/product/{$code}");
    }

    public function createTicket(): array
    {
        return $this->request('POST', '/api/pos/ticket/create');
    }

    public function addItem(string $sessionId, int $productId, int $quantity = 1): array
    {
        return $this->request('POST', '/api/pos/ticket/add-item', [
            'session_id' => $sessionId,
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);
    }

    public function removeItem(int $itemId): array
    {
        return $this->request('DELETE', "/api/pos/ticket/remove-item/{$itemId}");
    }

    public function getCurrentTicket(string $sessionId): array
    {
        return $this->request('GET', '/api/pos/ticket/current', [
            'session_id' => $sessionId,
        ]);
    }

    public function checkout(string $sessionId, float $amountPaid, string $paymentMethod = 'cash'): array
    {
        return $this->request('POST', '/api/pos/ticket/checkout', [
            'session_id' => $sessionId,
            'amount_paid' => $amountPaid,
            'payment_method' => $paymentMethod,
        ]);
    }

    public function getTotals(): array
    {
        return $this->request('GET', '/api/pos/totals');
    }

    private function request(string $method, string $uri, array $data = []): array
    {
        $options = [
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
            ],
        ];

        if ($method === 'GET' && !empty($data)) {
            $options['query'] = $data;
        } elseif (!empty($data)) {
            $options['json'] = $data;
        }

        $response = $this->http->request($method, $uri, $options);

        if ($response->getStatusCode() >= 400) {
            $error = json_decode($response->getBody(), true);
            throw new \Exception($error['error'] ?? 'Error en la petición API');
        }

        return json_decode($response->getBody(), true);
    }
}
