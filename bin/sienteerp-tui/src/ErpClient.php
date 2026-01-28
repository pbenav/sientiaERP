<?php

namespace App\SienteErpTui;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ErpClient
{
    private Client $client;
    private ?string $token = null;
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 10.0,
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

    private function request(string $method, string $endpoint, array $data = []): array
    {
        $options = [];
        
        if ($this->token) {
            $options['headers']['Authorization'] = "Bearer {$this->token}";
        }

        if (!empty($data)) {
            $options['json'] = $data;
        }

        try {
            $response = $this->client->request($method, $endpoint, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // Manejar errores de validación (422) y otros errores del cliente (4xx) de forma elegante
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
            $errorData = json_decode($responseBody, true);
            
            $mensaje = "Error en la petición";
            
            if (isset($errorData['error'])) {
                $mensaje = $errorData['error'];
                // Si hay detalles (ej: validación de campos), añadirlos
                if (isset($errorData['details']) && is_array($errorData['details'])) {
                   $detalles = [];
                   foreach ($errorData['details'] as $campo => $errores) {
                       $detalles[] = "$campo: " . implode(', ', $errores);
                   }
                   if (!empty($detalles)) {
                       $mensaje .= "\nDetalles:\n - " . implode("\n - ", $detalles);
                   }
                }
            } elseif (isset($errorData['message'])) {
                 $mensaje = $errorData['message'];
            }
            
            // Log para debug
            file_put_contents('/tmp/erp_api_error.log', date('Y-m-d H:i:s') . " Client Error {$method} {$endpoint}: " . $mensaje . "\n", FILE_APPEND);
            
            // Lanzar excepción con el mensaje limpio
            throw new \Exception($mensaje);
            
        } catch (GuzzleException $e) {
            file_put_contents('/tmp/erp_api_error.log', date('Y-m-d H:i:s') . " Error {$method} {$endpoint}: " . $e->getMessage() . "\n", FILE_APPEND);
            throw new \Exception("Error de conexión: " . $e->getMessage());
        }
    }

    /**
     * Login
     */
    public function login(string $email, string $password): array
    {
        $response = $this->request('POST', 'api/pos/login', [
            'email' => $email,
            'password' => $password,
        ]);

        if (isset($response['token'])) {
            $this->token = $response['token'];
        }

        return $response;
    }

    /**
     * Terceros
     */
    public function getTerceros(int $page = 1, int $perPage = 10, ?string $tipo = null): array
    {
        $query = "page={$page}&per_page={$perPage}";
        if ($tipo) {
            $query .= "&tipo={$tipo}";
        }
        return $this->request('GET', "/api/erp/terceros?{$query}");
    }

    public function getTercero(int $id): array
    {
        return $this->request('GET', "/api/erp/terceros/{$id}");
    }

    public function createTercero(array $data): array
    {
        return $this->request('POST', '/api/erp/terceros', $data);
    }

    public function updateTercero(int $id, array $data): array
    {
        return $this->request('PUT', "/api/erp/terceros/{$id}", $data);
    }

    public function deleteTercero(int $id): array
    {
        return $this->request('DELETE', "/api/erp/terceros/{$id}");
    }

    /**
     * Documentos (Presupuestos, Pedidos, etc.)
     */
    public function getDocumentos(string $tipo, int $page = 1, int $perPage = 10): array
    {
        $query = "tipo={$tipo}&page={$page}&per_page={$perPage}";
        return $this->request('GET', "/api/erp/documentos?{$query}");
    }

    public function getDocumento(int $id): array
    {
        return $this->request('GET', "/api/erp/documentos/{$id}");
    }

    public function createDocumento(array $data): array
    {
        return $this->request('POST', '/api/erp/documentos', $data);
    }

    public function updateDocumento(int $id, array $data): array
    {
        return $this->request('PUT', "/api/erp/documentos/{$id}", $data);
    }

    public function deleteDocumento(int $id): array
    {
        return $this->request('DELETE', "/api/erp/documentos/{$id}");
    }

    public function convertirDocumento(int $id, string $tipoDestino): array
    {
        return $this->request('POST', "/api/erp/documentos/{$id}/convertir", [
            'tipo_destino' => $tipoDestino,
        ]);
    }

    /**
     * Productos
     */
    public function getProductos(int $page = 1, int $perPage = 10): array
    {
        $query = "page={$page}&per_page={$perPage}";
        return $this->request('GET', "/api/erp/productos?{$query}");
    }

    public function getProducto(int $id): array
    {
        return $this->request('GET', "/api/erp/productos/{$id}");
    }

    public function searchProducto(string $query): array
    {
        return $this->request('GET', "/api/erp/productos/search?q=" . urlencode($query));
    }

    public function createProducto(array $data): array
    {
        return $this->request('POST', '/api/erp/productos', $data);
    }

    public function updateProducto(int $id, array $data): array
    {
        return $this->request('PUT', "/api/erp/productos/{$id}", $data);
    }

    public function deleteProducto(int $id): array
    {
        return $this->request('DELETE', "/api/erp/productos/{$id}");
    }
}
