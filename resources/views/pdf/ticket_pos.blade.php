<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Ticket TPV {{ $ticket->numero }}</title>
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: 'Courier', 'Arial', sans-serif;
            font-size: {{ ($width ?? '80mm') === '58mm' ? '8px' : '10px' }};
            width: {{ ($width ?? '80mm') === '58mm' ? '48mm' : '70mm' }};
            margin: 0;
            padding: 2mm;
            line-height: 1.1;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .company-name {
            font-weight: bold;
            font-size: 14px;
            display: block;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }

        /* Usamos tablas para alineación perfecta en dompdf (flex no es fiable) */
        .info-table {
            width: 100%;
            margin-bottom: 2px;
        }

        .info-table td {
            padding: 1px 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .items-table th {
            text-align: left;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }

        .items-table td {
            vertical-align: top;
            padding: 3px 0;
        }

        .totals-table {
            width: 100%;
            margin-top: 5px;
        }

        .totals-table td {
            padding: 1px 0;
        }

        .total-row {
            font-weight: bold;
            font-size: 12px;
            border-top: 1px double #000;
        }

        .total-row td {
            padding-top: 5px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 8px;
        }
    </style>
</head>

<body>
    <div class="header">
        <span class="company-name">{{ App\Models\Setting::get('pdf_logo_text', 'sienteERP POS') }}</span>
        {!! App\Models\Setting::get('pdf_header_html', 'NIF: B12345678') !!}
    </div>

    <div class="divider"></div>

    <table class="info-table">
        <tr>
            <td width="30%">TICKET:</td>
            <td class="text-right"><strong>{{ $ticket->numero }}</strong></td>
        </tr>
        <tr>
            <td>Fecha:</td>
            <td class="text-right">{{ $ticket->created_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td>Operador:</td>
            <td class="text-right">{{ $ticket->user->name }}</td>
        </tr>
        @if ($ticket->tercero)
            <tr>
                <td>Cliente:</td>
                <td class="text-right">{{ substr($ticket->tercero->nombre_comercial, 0, 20) }}</td>
            </tr>
        @endif
    </table>

    <div class="divider"></div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="55%">Desc.</th>
                <th width="15%" class="text-right">Cant.</th>
                <th width="30%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ticket->items as $item)
                <tr>
                    <td>
                        {{ $item->product ? $item->product->name : 'Producto' }}
                        <div style="font-size: 8px; color: #000;">IVA: {{ (int) $item->tax_rate }}%</div>
                    </td>
                    <td class="text-right">{{ (int) $item->quantity }}</td>
                    <td class="text-right">{{ number_format($item->total, 2, ',', '.') }}€</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="divider"></div>

    <table class="totals-table">
        <tr>
            <td>Subtotal:</td>
            <td class="text-right">{{ number_format($ticket->subtotal, 2, ',', '.') }}€</td>
        </tr>
        {{-- Desglose de IVA --}}
        @php
            $taxGroups = $ticket->items->groupBy('tax_rate');
        @endphp
        @foreach ($taxGroups as $rate => $items)
            @php
                $rateAmount = $items->sum('tax_amount');
            @endphp
            @if ($rateAmount > 0)
                <tr>
                    <td style="font-size: 8px; color: #000; padding-left: 5px;">IVA {{ (int) $rate }}%:</td>
                    <td class="text-right" style="font-size: 8px; color: #000;">
                        {{ number_format($rateAmount, 2, ',', '.') }}€</td>
                </tr>
            @endif
        @endforeach

        <tr style="font-weight: bold;">
            <td>Total IVA:</td>
            <td class="text-right">{{ number_format($ticket->tax, 2, ',', '.') }}€</td>
        </tr>

        @if ($ticket->descuento_importe > 0 || $ticket->descuento_porcentaje > 0)
            <tr>
                <td>Descuento:</td>
                <td class="text-right">
                    @if ($ticket->descuento_porcentaje > 0)
                        {{ (float) $ticket->descuento_porcentaje }}%
                    @endif
                    @if ($ticket->descuento_importe > 0)
                        -{{ number_format($ticket->descuento_importe, 2, ',', '.') }}€
                    @endif
                </td>
            </tr>
        @endif
        <tr class="total-row">
            <td>TOTAL:</td>
            <td class="text-right">{{ number_format($ticket->total, 2, ',', '.') }}€</td>
        </tr>
    </table>

    <div style="margin-top: 10px; border-top: 1px dashed #000; padding-top: 5px;">
        <table class="info-table" style="font-size: 9px;">
            <tr>
                <td>Pagado ({{ strtoupper($ticket->payment_method) }}):</td>
                <td class="text-right">{{ number_format($ticket->amount_paid, 2, ',', '.') }}€</td>
            </tr>
            @if ($ticket->pago_efectivo > 0 && $ticket->pago_tarjeta > 0)
                <tr>
                    <td style="padding-left: 10px; font-size: 8px;">- Efec:</td>
                    <td class="text-right" style="font-size: 8px;">
                        {{ number_format($ticket->pago_efectivo, 2, ',', '.') }}€</td>
                </tr>
                <tr>
                    <td style="padding-left: 10px; font-size: 8px;">- Tarj:</td>
                    <td class="text-right" style="font-size: 8px;">
                        {{ number_format($ticket->pago_tarjeta, 2, ',', '.') }}€</td>
                </tr>
            @endif
            @if ($ticket->change_given > 0)
                <tr>
                    <td><strong>Cambio:</strong></td>
                    <td class="text-right"><strong>{{ number_format($ticket->change_given, 2, ',', '.') }}€</strong>
                    </td>
                </tr>
            @endif
        </table>
    </div>

    <div class="footer">
        {{ App\Models\Setting::get('pdf_footer_text', '¡Gracias por su confianza!') }}
        <br>
        Sientia ERP - {{ config('app.version') }}
        @if ($ticket->verifactu_qr_url)
            <div class="divider"></div>
            <div class="text-center" style="margin-top: 5px;">
                <div style="font-size: 8px; margin-bottom: 5px; font-weight: bold;">Factura verificable en la sede
                    electr&oacute;nica de la AEAT</div>
                <img src="data:image/svg+xml;base64,{!! base64_encode(
                    SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(100)->generate($ticket->verifactu_qr_url),
                ) !!}">
                <div style="font-size: 7px; margin-top: 5px; font-family: monospace; color: #000;">Huella:
                    {{ substr($ticket->verifactu_huella, 0, 20) }}...</div>
            </div>
        @endif
    </div>
</body>

</html>
