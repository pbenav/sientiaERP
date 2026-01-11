<?php

namespace App\NexErpTui\Actions;

use App\NexErpTui\ErpClient;
use App\NexErpTui\Display\Screen;
use App\NexErpTui\Input\KeyHandler;

class DetailsActions
{
    private ErpClient $client;
    private Screen $screen;
    private KeyHandler $keyHandler;

    public function __construct(ErpClient $client, Screen $screen, KeyHandler $keyHandler)
    {
        $this->client = $client;
        $this->screen = $screen;
        $this->keyHandler = $keyHandler;
    }

    public function renderTercero(int $id): void
    {
        try {
            $tercero = $this->client->getTercero($id);
            $this->renderTerceroDetail($tercero);
            echo "\n  \033[33mPresione cualquier tecla para volver a la lista...\033[0m";
            $this->waitForKey();
        } catch (\Exception $e) {
            $this->renderError($e->getMessage());
        }
    }

    public function renderDocumento(int $id): void
    {
        try {
            $doc = $this->client->getDocumento($id);
            $this->renderDocumentoDetail($doc);
            echo "\n  \033[33mPresione cualquier tecla para volver a la lista...\033[0m";
            $this->waitForKey();
        } catch (\Exception $e) {
            $this->renderError($e->getMessage());
        }
    }

    private function waitForKey(): void
    {
        $this->keyHandler->clearStdin();
        $this->keyHandler->waitForKey();
    }

    private function renderTerceroDetail(array $tercero): void
    {
        $this->screen->clear();

        $green = "\033[32m";
        $cyan = "\033[36m";
        $yellow = "\033[33m";
        $white = "\033[37m";
        $reset = "\033[0m";

        echo "{$cyan}╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                           DETALLES DEL TERCERO                                ║\n";
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝{$reset}\n\n";

        echo "{$cyan}DATOS BÁSICOS{$reset}\n";
        echo "{$white}CÓDIGO:           {$yellow}" . ($tercero['codigo'] ?? '---') . "{$reset}\n";
        echo "{$white}NOMBRE COMERCIAL: {$yellow}" . ($tercero['nombre_comercial'] ?? '---') . "{$reset}\n";
        echo "{$white}RAZÓN SOCIAL:     {$yellow}" . ($tercero['razon_social'] ?? '---') . "{$reset}\n";
        echo "{$white}NIF/CIF:          {$yellow}" . ($tercero['nif_cif'] ?? '---') . "{$reset}\n";
        
        $tipos = array_column($tercero['tipos'] ?? [], 'nombre');
        echo "{$white}TIPOS:            {$green}" . implode(', ', $tipos) . "{$reset}\n\n";

        echo "{$cyan}CONTACTO{$reset}\n";
        echo "{$white}TELÉFONO:         {$yellow}" . ($tercero['telefono'] ?? '---') . "{$reset}\n";
        echo "{$white}EMAIL:            {$yellow}" . ($tercero['email'] ?? '---') . "{$reset}\n";
        echo "{$white}WEB:              {$yellow}" . ($tercero['web'] ?? '---') . "{$reset}\n\n";

        echo "{$cyan}DIRECCIÓN FISCAL{$reset}\n";
        echo "{$white}DIRECCIÓN:        {$yellow}" . ($tercero['direccion_fiscal'] ?? '---') . "{$reset}\n";
        echo "{$white}C.P. / CIUDAD:    {$yellow}" . ($tercero['cp_fiscal'] ?? '---') . " " . ($tercero['ciudad_fiscal'] ?? '') . "{$reset}\n";
        echo "{$white}PROVINCIA:        {$yellow}" . ($tercero['provincia_fiscal'] ?? '---') . "{$reset}\n\n";

        if (!empty($tercero['observaciones'])) {
            echo "{$cyan}OBSERVACIONES{$reset}\n";
            echo "{$white}" . wordwrap($tercero['observaciones'], 75) . "{$reset}\n";
        }
    }

    private function renderDocumentoDetail(array $doc): void
    {
        $this->screen->clear();

        $green = "\033[32m";
        $cyan = "\033[36m";
        $yellow = "\033[33m";
        $white = "\033[37m";
        $red = "\033[31m";
        $reset = "\033[0m";

        $tipoLabel = strtoupper($doc['tipo'] ?? 'DOCUMENTO');

        echo "{$cyan}╔═══════════════════════════════════════════════════════════════════════════════╗\n";
        echo "║                           {$tipoLabel}: " . str_pad($doc['numero'] ?? '---', 40) . "║\n";
        echo "╚═══════════════════════════════════════════════════════════════════════════════╝{$reset}\n\n";

        echo "{$white}FECHA:   {$yellow}" . ($doc['fecha'] ?? '---') . "{$reset}  |  ";
        
        $estadoColor = match($doc['estado'] ?? 'borrador') {
            'confirmado', 'cobrado', 'pagado' => $green,
            'anulado' => $red,
            default => $yellow,
        };
        $estado = strtoupper($doc['estado'] ?? 'borrador');
        echo "{$white}ESTADO:  {$estadoColor}{$estado}{$reset}\n";

        $clienteNombre = $doc['tercero']['nombre_comercial'] ?? '---';
        $clienteNif = $doc['tercero']['nif_cif'] ?? '';
        echo "{$white}CLIENTE: {$yellow}{$clienteNombre} {$white}({$clienteNif}){$reset}\n\n";

        echo "{$cyan}";
        echo str_pad("Descripción", 45);
        echo str_pad("Cant.", 8, " ", STR_PAD_LEFT);
        echo str_pad("Precio", 12, " ", STR_PAD_LEFT);
        echo str_pad("Total", 12, " ", STR_PAD_LEFT);
        echo "{$reset}\n";
        echo "{$cyan}" . str_repeat("─", 77) . "{$reset}\n";

        foreach ($doc['lineas'] ?? [] as $linea) {
            echo "{$white}";
            echo str_pad(substr($linea['descripcion'] ?? '', 0, 43), 45);
            echo str_pad(number_format($linea['cantidad'] ?? 0, 2, ',', '.'), 8, " ", STR_PAD_LEFT);
            echo str_pad(number_format($linea['precio_unitario'] ?? 0, 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT);
            echo str_pad(number_format($linea['total'] ?? 0, 2, ',', '.') . ' €', 12, " ", STR_PAD_LEFT);
            echo "{$reset}\n";
        }

        echo "{$cyan}" . str_repeat("─", 77) . "{$reset}\n";
        $subtotalText = number_format($doc['subtotal'] ?? 0, 2, ',', '.') . ' €';
        $ivaText = number_format($doc['iva'] ?? 0, 2, ',', '.') . ' €';
        $totalText = number_format($doc['total'] ?? 0, 2, ',', '.') . ' €';

        echo str_pad("{$white}SUBTOTAL:{$reset}", 75, " ", STR_PAD_LEFT) . str_pad($subtotalText, 15, " ", STR_PAD_LEFT) . "\n";
        echo str_pad("{$white}IVA:{$reset}", 75, " ", STR_PAD_LEFT) . str_pad($ivaText, 15, " ", STR_PAD_LEFT) . "\n";
        echo "{$cyan}" . str_repeat(" ", 65) . str_repeat("═", 12) . "{$reset}\n";
        echo str_pad("{$green}TOTAL:{$reset}", 75, " ", STR_PAD_LEFT) . str_pad("{$green}" . $totalText . "{$reset}", 15, " ", STR_PAD_LEFT) . "\n\n";

        if (!empty($doc['observaciones'])) {
            echo "{$cyan}OBSERVACIONES:{$reset}\n";
            echo "{$white}" . wordwrap($doc['observaciones'], 75) . "{$reset}\n";
        }
    }

    private function renderError(string $message): void
    {
        $this->screen->clear();
        echo "\033[31m\n\n  ❌ Error: {$message}\033[0m\n\n";
        echo "  Presione cualquier tecla para continuar...";
        $this->waitForKey();
    }
}
