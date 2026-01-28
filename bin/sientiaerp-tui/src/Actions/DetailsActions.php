<?php

namespace App\SientiaErpTui\Actions;

use App\SientiaErpTui\ErpClient;
use App\SientiaErpTui\Display\Screen;
use App\SientiaErpTui\Display\Window;
use App\SientiaErpTui\Input\KeyHandler;
use App\SientiaErpTui\Input\FunctionKeyMapper;

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
        } catch (\Exception $e) {
            $this->renderError($e->getMessage());
        }
    }

    public function renderDocumento(int $id): void
    {
        try {
            $doc = $this->client->getDocumento($id);
            $this->renderDocumentoDetail($doc);
        } catch (\Exception $e) {
            $this->renderError($e->getMessage());
        }
    }

    private function renderTerceroDetail(array $tercero): void
    {
        $window = new Window($this->screen, 'DETALLES DEL TERCERO');
        
        // Datos básicos
        $window->addField('codigo', 'Código', $tercero['codigo'] ?? '---', readonly: true)
               ->addField('nombre_comercial', 'Nombre Comercial', $tercero['nombre_comercial'] ?? '---', readonly: true)
               ->addField('razon_social', 'Razón Social', $tercero['razon_social'] ?? '---', readonly: true)
               ->addField('nif_cif', 'NIF/CIF', $tercero['nif_cif'] ?? '---', readonly: true);
        
        // Tipos
        $tipos = array_column($tercero['tipos'] ?? [], 'nombre');
        $window->addField('tipos', 'Tipos', implode(', ', $tipos), readonly: true);
        
        // Contacto
        $window->addField('telefono', 'Teléfono', $tercero['telefono'] ?? '---', readonly: true)
               ->addField('email', 'Email', $tercero['email'] ?? '---', readonly: true)
               ->addField('web', 'Web', $tercero['web'] ?? '---', readonly: true);
        
        // Dirección fiscal
        $window->addField('direccion_fiscal', 'Dirección Fiscal', $tercero['direccion_fiscal'] ?? '---', readonly: true)
               ->addField('cp_ciudad', 'C.P. / Ciudad', 
                   ($tercero['cp_fiscal'] ?? '---') . ' ' . ($tercero['ciudad_fiscal'] ?? ''), readonly: true)
               ->addField('provincia_fiscal', 'Provincia', $tercero['provincia_fiscal'] ?? '---', readonly: true);
        
        // Solo F12 para volver
        $window->setFunctionKeys(['F12' => 'Volver']);
        
        $window->render();
        
        // Esperar F12 o ESC
        while (true) {
            $rawKey = $this->keyHandler->waitForKey();
            $key = FunctionKeyMapper::mapKey($rawKey);
            
            if ($key === 'F12' || $key === 'ESC') {
                break;
            }
        }
    }

    private function renderDocumentoDetail(array $doc): void
    {
        // Usar FullScreenLayout para consistencia
        $layout = new \App\SientiaErpTui\Display\FullScreenLayout($this->screen);
        $tipoLabel = strtoupper($doc['tipo'] ?? 'DOCUMENTO');
        $numero = $doc['numero'] ?? '---';
        
        $layout->setCompanyName('sientiaERP')
               ->setTitle("{$tipoLabel}: {$numero}");
               
        // Renderizar dentro del layout
        $layout->render(function($width, $height) use ($doc) {
            $this->renderDetailContent($width, $height, $doc);
        });

        // Esperar tecla
        $this->keyHandler->clearStdin();
        $this->keyHandler->waitForKey();
    }

    private function renderDetailContent(int $width, int $height, array $doc): void
    {
        $green = "\033[32m";
        $cyan = "\033[36m";
        $yellow = "\033[33m";
        $white = "\033[37m";
        $red = "\033[31m";
        $reset = "\033[0m";
        
        // Cabecera del documento
        $fechaLimpia = substr($doc['fecha'] ?? '', 0, 10);
        
        $estadoColor = match($doc['estado'] ?? 'borrador') {
            'confirmado', 'cobrado', 'pagado' => $green,
            'anulado' => $red,
            default => $yellow,
        };
        $estado = strtoupper($doc['estado'] ?? 'BORRADOR');
        
        $clienteNombre = $doc['tercero']['nombre_comercial'] ?? '---';
        $clienteNif = $doc['tercero']['nif_cif'] ?? '';

        // Línea 1: Fecha y Estado
        echo "{$white}FECHA:   {$yellow}{$fechaLimpia}{$reset}";
        // Alinear estado a la derecha
        $estadoText = "ESTADO: {$estado}"; 
        $paddingEstado = $width - 20 - mb_strlen("FECHA:   {$fechaLimpia}") - mb_strlen($estadoText) + mb_strlen($estadoColor); 
        // Simple: Fecha a la izq, estado a la derecha en la misma línea? O mejor:
        echo str_repeat(" ", 5) . "{$white}ESTADO:  {$estadoColor}{$estado}{$reset}\n";
        
        // Línea 2: Cliente
        echo "{$white}CLIENTE: {$yellow}{$clienteNombre} {$white}({$clienteNif}){$reset}\n\n";

        // Tabla Dinámica
        // Anchos fijos
        $wCant = 10;
        $wPrecio = 13;
        $wTotal = 13;
        $wFixed = $wCant + $wPrecio + $wTotal;
        
        // Ancho Descripción
        $wDesc = $width - $wFixed; 
        if ($wDesc < 20) $wDesc = 20; // Mínimo seguridad
        
        echo "{$cyan}";
        echo $this->mb_str_pad("Descripción", $wDesc);
        echo $this->mb_str_pad("Cant.", $wCant, " ", STR_PAD_LEFT);
        echo $this->mb_str_pad("Precio", $wPrecio, " ", STR_PAD_LEFT);
        echo $this->mb_str_pad("Total", $wTotal, " ", STR_PAD_LEFT);
        echo "{$reset}\n";
        
        echo "{$cyan}" . str_repeat("─", $width) . "{$reset}\n";

        $lineas = $doc['lineas'] ?? [];
        // Limitar líneas si exceden altura (para scroll futuro, ahora solo truncamos visualización si es masivo)
        
        foreach ($lineas as $linea) {
            $cantidad = number_format($linea['cantidad'] ?? 0, 2, ',', '.');
            
            $precioNum = number_format($linea['precio_unitario'] ?? 0, 2, ',', '.');
            $precioStr = $this->mb_str_pad($precioNum, $wPrecio - 2, " ", STR_PAD_LEFT) . ' €'; // -2 para ' €'
            // Ajuste fino: formatCurrency hace padding interno
            $precioStr = str_pad($precioNum, $wPrecio - 2, " ", STR_PAD_LEFT) . ' €';
            
            $totalNum = number_format($linea['total'] ?? 0, 2, ',', '.');
            $totalStr = str_pad($totalNum, $wTotal - 2, " ", STR_PAD_LEFT) . ' €';

            echo "{$white}";
            // Truncar descripción
            echo $this->mb_str_pad(mb_substr($linea['descripcion'] ?? '', 0, $wDesc - 1), $wDesc);
            
            echo $this->mb_str_pad($cantidad, $wCant, " ", STR_PAD_LEFT);
            echo $this->mb_str_pad($precioStr, $wPrecio, " ", STR_PAD_LEFT);
            echo $this->mb_str_pad($totalStr, $wTotal, " ", STR_PAD_LEFT);
            echo "{$reset}\n";
        }

        echo "{$cyan}" . str_repeat("─", $width) . "{$reset}\n";
        
        // Totales alineados a la derecha
        $subtotal = number_format($doc['subtotal'] ?? 0, 2, ',', '.') . ' €';
        $iva = number_format($doc['iva'] ?? 0, 2, ',', '.') . ' €';
        $total = number_format($doc['total'] ?? 0, 2, ',', '.') . ' €';
        
        // Padding para alinear los importes con la columna Total
        $labelWidth = $width - $wTotal - 1;
        
        echo $this->mb_str_pad("{$white}SUBTOTAL:{$reset}", $labelWidth, " ", STR_PAD_LEFT) . 
             $this->mb_str_pad($subtotal, $wTotal, " ", STR_PAD_LEFT) . "\n";
             
        echo $this->mb_str_pad("{$white}IVA:{$reset}", $labelWidth, " ", STR_PAD_LEFT) . 
             $this->mb_str_pad($iva, $wTotal, " ", STR_PAD_LEFT) . "\n";
             
        echo "{$cyan}" . str_repeat(" ", $labelWidth) . str_repeat("═", $wTotal) . "{$reset}\n";
        
        echo $this->mb_str_pad("{$green}TOTAL:{$reset}", $labelWidth, " ", STR_PAD_LEFT) . 
             $this->mb_str_pad("{$green}" . $total . "{$reset}", $wTotal, " ", STR_PAD_LEFT) . "\n\n";
             
        if (!empty($doc['observaciones'])) {
            echo "{$cyan}OBSERVACIONES:{$reset}\n";
            echo "{$white}" . wordwrap($doc['observaciones'], $width - 4) . "{$reset}\n";
        }
        
        echo "\n  {$yellow}Presione cualquier tecla para volver...{$reset}";
    }
    
    // Helper
    private function mb_str_pad($input, $pad_length, $pad_string = " ", $pad_type = STR_PAD_RIGHT) {
        $diff = strlen($input) - mb_strlen($input);
        return str_pad($input, max(0, $pad_length + $diff), $pad_string, $pad_type);
    }
    
    private function renderError(string $message): void
    {
        $this->screen->clear();
        echo "\033[31m\n\n  ❌ Error: {$message}\033[0m\n\n";
        echo "  Presione cualquier tecla para continuar...";
        
        $this->keyHandler->clearStdin();
        $this->keyHandler->waitForKey();
    }
}
