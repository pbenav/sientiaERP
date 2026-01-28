@php
    $currencySymbol = App\Models\Setting::get('currency_symbol', '€');
    $currencyPosition = App\Models\Setting::get('currency_position', 'suffix');
    
    function formatMoney($amount, $symbol, $position) {
        $formattedAmount = number_format($amount, 2, ',', '.');
        return $position === 'suffix' ? $formattedAmount . ' ' . $symbol : $symbol . ' ' . $formattedAmount;
    }
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $doc->numero }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; position: relative; }
        .header { margin-bottom: 30px; }
        .company-info { float: left; width: 50%; }
        .doc-info { float: right; width: 40%; text-align: right; }
        .clear { clear: both; }
        .billing-info { margin-bottom: 30px; border-top: 2px solid #eee; padding-top: 15px; }
        .client-info { float: left; width: 45%; }
        .delivery-info { float: right; width: 45%; }
        h1 { margin: 0; color: #2563eb; text-transform: uppercase; font-size: 24px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .table th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; padding: 10px; text-align: left; }
        .table td { border-bottom: 1px solid #e2e8f0; padding: 10px; }
        .text-right { text-align: right; }
        .totals { float: right; width: 30%; }
        .total-row { display: flex; justify-content: space-between; padding: 5px 0; }
        .grand-total { border-top: 2px solid #2563eb; margin-top: 10px; padding-top: 10px; font-weight: bold; font-size: 16px; color: #2563eb; }
        .footer { position: fixed; bottom: 0; width: 100%; font-size: 10px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 10px; }
        /* Marca de agua para documentos anulados */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            font-weight: bold;
            color: rgba(239, 68, 68, 0.2);
            text-transform: uppercase;
            z-index: 1000;
            pointer-events: none;
            white-space: nowrap;
            letter-spacing: 10px;
        }
    </style>
</head>
<body>
    {{-- Marca de agua para documentos anulados --}}
    @if($doc->estado === 'anulado')
    <div class="watermark">ANULADA</div>
    @endif
    
    <div class="header">
        <div class="company-info">
            @php
                $logoType = App\Models\Setting::get('pdf_logo_type', 'text');
                $logoText = App\Models\Setting::get('pdf_logo_text', 'sientiaERP System');
                $logoImage = App\Models\Setting::get('pdf_logo_image');
            @endphp
            
            @if($logoType === 'image' && $logoImage)
                <img src="{{ public_path('storage/' . $logoImage) }}" 
                     alt="Logo" 
                     style="max-width: 300px; height: auto; max-height: 80px; margin-bottom: 10px;">
            @else
                <h1>{{ $logoText }}</h1>
            @endif
            
            <p>
                {!! App\Models\Setting::get('pdf_header_html', '<strong>Sientia SL</strong><br>NIF: B12345678<br>Calle Falsa 123, 28001 Madrid') !!}
            </p>
        </div>
        <div class="doc-info">
            <h2 style="color: #64748b; margin: 0;">{{ strtoupper($doc->tipo) }}</h2>
            <p style="font-size: 18px; font-weight: bold; margin: 5px 0;">{{ $doc->numero }}</p>
            @if($doc->es_rectificativa && $doc->facturaRectificada)
                <p style="font-size: 10px; color: #64748b; margin-top: 5px;">Rectifica a: {{ $doc->facturaRectificada->numero }} ({{ $doc->facturaRectificada->fecha->format('d/m/Y') }})</p>
            @endif
            <p>Fecha: {{ $doc->fecha->format('d/m/Y') }}</p>
            @if($doc->fecha_entrega)
                <p>Fecha Entrega: {{ $doc->fecha_entrega->format('d/m/Y') }}</p>
            @endif
        </div>
        <div class="clear"></div>
    </div>

    <div class="billing-info">
        <div class="client-info">
            <h3 style="margin-top: 0; font-size: 14px; border-bottom: 1px solid #eee;">CLIENTE</h3>
            <strong>{{ $doc->tercero->nombre_comercial }}</strong><br>
            {{ $doc->tercero->razon_social }}<br>
            NIF/CIF: {{ $doc->tercero->nif_cif }}<br>
            {{ $doc->tercero->direccion_fiscal }}<br>
            {{ $doc->tercero->cp_fiscal }} {{ $doc->tercero->ciudad_fiscal }} ({{ $doc->tercero->provincia_fiscal }})
        </div>
        @if($doc->tercero->direccion_envio)
        <div class="delivery-info">
            <h3 style="margin-top: 0; font-size: 14px; border-bottom: 1px solid #eee;">DIRECCIÓN DE ENVÍO</h3>
            {{ $doc->tercero->direccion_envio }}<br>
            {{ $doc->tercero->cp_envio }} {{ $doc->tercero->ciudad_envio }} ({{ $doc->tercero->provincia_envio }})
        </div>
        @endif
        <div class="clear"></div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th width="50%">Descripción</th>
                <th width="10%" class="text-right">Cant.</th>
                <th width="20%" class="text-right">Precio</th>
                <th width="20%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($doc->lineas as $linea)
            <tr>
                <td>
                    <strong>{{ $linea->codigo }}</strong> - {{ $linea->descripcion }}
                </td>
                <td class="text-right">{{ number_format($linea->cantidad, 2, ',', '.') }}</td>
                <td class="text-right">{{ formatMoney($linea->precio_unitario, $currencySymbol, $currencyPosition) }}</td>
                <td class="text-right">{{ formatMoney($linea->total, $currencySymbol, $currencyPosition) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="width: 100%;">
        <div style="float: left; width: 60%;">
            @if($doc->observaciones)
            <h4 style="margin-bottom: 5px;">Observaciones:</h4>
            <p style="font-size: 10px;">{{ $doc->observaciones }}</p>
            @endif
        </div>
        <div class="totals">
            <div class="total-row">
                <span>Subtotal:</span>
                <span class="text-right">{{ formatMoney($doc->subtotal, $currencySymbol, $currencyPosition) }}</span>
            </div>
            <div class="total-row">
                <span>IVA:</span>
                <span class="text-right">{{ formatMoney($doc->iva, $currencySymbol, $currencyPosition) }}</span>
            </div>
            @if($doc->porcentaje_irpf > 0)
            <div class="total-row">
                <span>IRPF ({{ $doc->porcentaje_irpf }}%):</span>
                <span class="text-right">{{ formatMoney($doc->irpf, $currencySymbol, $currencyPosition) }}</span>
            </div>
            @endif
            <div class="grand-total">
                <span>TOTAL:</span>
                <span style="float: right;">{{ formatMoney($doc->total, $currencySymbol, $currencyPosition) }}</span>
            </div>
        </div>
        <div class="clear"></div>
    </div>

    <div class="footer">
        {{ App\Models\Setting::get('pdf_footer_text', 'sientiaERP System') }}
    </div>
</body>
</html>
