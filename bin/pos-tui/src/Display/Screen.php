<?php

namespace App\PosTui\Display;

class Screen
{
    private array $colors;

    public function __construct(array $colors)
    {
        $this->colors = $colors;
    }

    public function clear(): void
    {
        echo "\033[2J\033[H"; // Clear screen and move cursor to top
    }

    public function showLogo(): void
    {
        $green = $this->colors['fg_green'];
        $reset = $this->colors['reset'];
        
        echo "{$green}";
        echo "  ╔═══════════════════════════════════════════════════════════════════════════╗\n";
        echo "  ║                                                                           ║\n";
        echo "  ║    ██████╗  ██████╗ ███████╗    ████████╗██╗   ██╗██╗                    ║\n";
        echo "  ║    ██╔══██╗██╔═══██╗██╔════╝    ╚══██╔══╝██║   ██║██║                    ║\n";
        echo "  ║    ██████╔╝██║   ██║███████╗       ██║   ██║   ██║██║                    ║\n";
        echo "  ║    ██╔═══╝ ██║   ██║╚════██║       ██║   ██║   ██║██║                    ║\n";
        echo "  ║    ██║     ╚██████╔╝███████║       ██║   ╚██████╔╝██║                    ║\n";
        echo "  ║    ╚═╝      ╚═════╝ ╚══════╝       ╚═╝    ╚═════╝ ╚═╝                    ║\n";
        echo "  ║                                                                           ║\n";
        echo "  ║                    Sistema de Punto de Venta v1.0                        ║\n";
        echo "  ║                                                                           ║\n";
        echo "  ╚═══════════════════════════════════════════════════════════════════════════╝\n";
        echo "{$reset}";
    }

    public function render(array $data): void
    {
        $this->clear();
        
        // Header
        echo $this->renderHeader($data);
        echo str_repeat('═', 80) . "\n";
        
        // Items table
        echo $this->renderItemsTable($data['items']);
        echo str_repeat('─', 80) . "\n";
        
        // Totals
        echo $this->renderTotals($data['totals']);
        echo str_repeat('═', 80) . "\n";
        
        // Footer
        echo $this->renderFooter();
    }

    private function renderHeader(array $data): string
    {
        $green = $this->colors['fg_green'];
        $white = $this->colors['fg_white'];
        $reset = $this->colors['reset'];
        
        $time = date('H:i:s');
        $operator = str_pad($data['operator'], 25);
        $session = str_pad("Sesión: " . substr($data['session'], 0, 8), 25);
        
        return "{$green} {$operator} │ {$session} │ {$time} {$reset}\n";
    }

    private function renderItemsTable(array $items): string
    {
        $white = $this->colors['fg_white'];
        $reset = $this->colors['reset'];
        
        $output = "{$white}";
        $output .= sprintf("%-6s %-40s %8s %10s %12s\n", 
            'CANT', 'PRODUCTO', 'SKU', 'PRECIO', 'SUBTOTAL');
        
        if (empty($items)) {
            $output .= "\n" . str_pad('(Ticket vacío - Escanee un producto)', 80, ' ', STR_PAD_BOTH) . "\n\n";
        } else {
            foreach ($items as $item) {
                $output .= sprintf("%-6d %-40s %8s %9.2f € %11.2f €\n",
                    $item['quantity'],
                    mb_substr($item['product']['name'], 0, 40),
                    $item['product']['sku'],
                    $item['unit_price'],
                    $item['total']
                );
            }
        }
        
        return $output . "{$reset}";
    }

    private function renderTotals(array $totals): string
    {
        $green = $this->colors['fg_green'];
        $yellow = $this->colors['fg_yellow'];
        $reset = $this->colors['reset'];
        
        return "{$green}" . sprintf(
            "%60s %12.2f €\n%60s %12.2f €\n",
            'SUBTOTAL:', $totals['subtotal'],
            'IVA:', $totals['tax']
        ) . "{$reset}" . "{$yellow}" . sprintf(
            "%60s %12.2f €\n",
            'TOTAL:', $totals['total']
        ) . "{$reset}";
    }

    private function renderFooter(): string
    {
        $white = $this->colors['fg_white'];
        $reset = $this->colors['reset'];
        
        return "{$white} F1: Buscar │ F5: Cobrar │ F6: Totales │ F7: Salir │ ESC: Cancelar línea {$reset}\n";
    }

    public function flashSuccess(string $message): void
    {
        $green = $this->colors['fg_green'];
        $reset = $this->colors['reset'];
        
        echo "\033[s"; // Save cursor position
        echo "\033[25;1H"; // Move to line 25
        echo "{$green} {$message} {$reset}";
        echo "\033[u"; // Restore cursor position
        usleep(300000); // 300ms
    }

    public function flashError(string $message): void
    {
        $red = $this->colors['fg_red'];
        $reset = $this->colors['reset'];
        
        echo "\033[s";
        echo "\033[25;1H";
        echo "{$red} {$message} {$reset}";
        echo "\033[u";
        usleep(800000); // 800ms
    }

    public function showMessage(string $message, string $type = 'info'): void
    {
        $color = match($type) {
            'success' => $this->colors['fg_green'],
            'error' => $this->colors['fg_red'],
            'warning' => $this->colors['fg_yellow'],
            default => $this->colors['fg_white'],
        };
        $reset = $this->colors['reset'];
        
        echo "{$color}  {$message}{$reset}\n";
    }

    public function showReceipt(array $result): void
    {
        $green = $this->colors['fg_green'];
        $white = $this->colors['fg_white'];
        $reset = $this->colors['reset'];
        
        echo "\n{$green}";
        echo "  ╔═══════════════════════════════════════════════════════════════════════════╗\n";
        echo "  ║                            TICKET DE VENTA                                ║\n";
        echo "  ╚═══════════════════════════════════════════════════════════════════════════╝\n";
        echo "{$reset}\n";
        
        $ticket = $result['ticket'];
        
        echo "{$white}  Ticket: #{$ticket['id']}\n";
        echo "  Fecha: " . date('d/m/Y H:i:s') . "\n\n";
        
        echo "  " . str_repeat('─', 75) . "\n";
        echo "  " . sprintf("%-40s %8s %10s %12s\n", 'PRODUCTO', 'CANT', 'PRECIO', 'TOTAL');
        echo "  " . str_repeat('─', 75) . "\n";
        
        foreach ($ticket['items'] as $item) {
            echo "  " . sprintf("%-40s %8d %9.2f € %11.2f €\n",
                mb_substr($item['product']['name'], 0, 40),
                $item['quantity'],
                $item['unit_price'],
                $item['total']
            );
        }
        
        echo "  " . str_repeat('─', 75) . "\n\n";
        
        echo "  " . sprintf("%60s %12.2f €\n", 'SUBTOTAL:', $ticket['subtotal']);
        echo "  " . sprintf("%60s %12.2f €\n", 'IVA:', $ticket['tax']);
        echo "{$green}  " . sprintf("%60s %12.2f €\n", 'TOTAL:', $ticket['total']);
        echo "{$white}\n";
        echo "  " . sprintf("%60s %12.2f €\n", 'PAGADO:', $ticket['amount_paid']);
        echo "{$green}  " . sprintf("%60s %12.2f €\n", 'CAMBIO:', $result['change']);
        echo "{$reset}\n";
        
        echo "  ╔═══════════════════════════════════════════════════════════════════════════╗\n";
        echo "  ║                      ¡GRACIAS POR SU COMPRA!                              ║\n";
        echo "  ╚═══════════════════════════════════════════════════════════════════════════╝\n";
    }

    public function showTotals(array $totals): void
    {
        $green = $this->colors['fg_green'];
        $white = $this->colors['fg_white'];
        $yellow = $this->colors['fg_yellow'];
        $reset = $this->colors['reset'];
        
        echo "\n{$green}";
        echo "  ╔═══════════════════════════════════════════════════════════════════════════╗\n";
        echo "  ║                         TOTALES DEL TURNO                                 ║\n";
        echo "  ╚═══════════════════════════════════════════════════════════════════════════╝\n";
        echo "{$reset}\n";
        
        echo "{$white}  Operador: {$totals['operator']}\n";
        echo "  Fecha: {$totals['date']}\n\n";
        
        echo "  " . str_repeat('─', 75) . "\n";
        echo "  Total de transacciones: {$totals['total_transactions']}\n";
        echo "  " . str_repeat('─', 75) . "\n\n";
        
        echo "  Ventas en efectivo:  " . number_format($totals['cash_sales'], 2, ',', '.') . " €\n";
        echo "  Ventas con tarjeta:  " . number_format($totals['card_sales'], 2, ',', '.') . " €\n";
        echo "  " . str_repeat('─', 75) . "\n";
        echo "{$yellow}  TOTAL VENTAS:        " . number_format($totals['total_sales'], 2, ',', '.') . " €\n";
        echo "{$reset}\n";
    }
}
