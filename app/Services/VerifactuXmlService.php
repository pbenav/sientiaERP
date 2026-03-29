<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class VerifactuXmlService
{
    /**
     * Generar el XML de Alta de Facturación según esquemas oficiales de la AEAT.
     *
     * Estructura correcta según SuministroLR.xsd + SuministroInformacion.xsd:
     *   sfLR:RegFactuSistemaFacturacion
     *     sfLR:Cabecera (sf:CabeceraType)
     *     sfLR:RegistroFactura  <-- WRAPPER obligatorio (puede repetirse hasta 1000)
     *       sf:RegistroAlta   <-- RegistroFacturacionAltaType en namespace sf:
     *         sf:IDVersion
     *         sf:IDFactura
     *         sf:NombreRazonEmisor
     *         sf:TipoFactura
     *         sf:DescripcionOperacion
     *         sf:FacturaSimplificadaArt7273 (opcional)
     *         sf:FacturaSinIdentifDestinatarioArt61d (opcional)
     *         sf:Macrodato (opcional)
     *         sf:EmitidaPorTerceroODestinatario (opcional)
     *         sf:Destinatarios (opcional)
     *           sf:IDDestinatario (hasta 1000)
     *         sf:Desglose
     *         sf:CuotaTotal
     *         sf:ImporteTotal
     *         sf:Encadenamiento
     *           sf:PrimerRegistro | sf:RegistroAnterior
     *         sf:SistemaInformatico
     *         sf:FechaHoraHusoGenRegistro
     *         sf:TipoHuella
     *         sf:Huella
     */
    public function generateAltaXml(Model $model): string
    {
        // Forzar carga de relaciones para integridad
        if (($model instanceof Documento || $model instanceof Ticket) && !$model->relationLoaded('tercero')) {
            $model->load('tercero');
        }

        $nifEmisor     = \App\Models\Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor', 'B00000000'));
        $nombreEmisor  = \App\Models\Setting::get('verifactu_nombre_emisor', config('verifactu.nombre_emisor', 'SIENTIA ERP DEMO'));
        $tipoFactura   = ($model instanceof Documento && $model->tipo === 'factura') ? 'F1' : 'F2';

        // Fecha en formato dd-mm-yyyy (requerido por AEAT)
        $fecha = $model->fecha
            ? $model->fecha->format('d-m-Y')
            : ($model->completed_at ? $model->completed_at->format('d-m-Y') : now()->format('d-m-Y'));

        // USAR EL TIMESTAMP PERSISTIDO para garantizar paridad con la huella
        $fechaHoraHuso = $model->verifactu_fecha_hora_huso ?: now()->toIso8601String();

        $total     = number_format((float)$model->total, 2, '.', '');
        $desglose  = $this->getDesgloseParaXml($model);

        // Calcular CuotaTotal (suma de cuotas de todos los tramos)
        $cuotaTotalVal = 0.0;
        foreach ($desglose as $linea) {
            $cuotaTotalVal += (float)$linea['cuota_iva'];
        }
        $cuotaTotal = number_format($cuotaTotalVal, 2, '.', '');

        $numeroLimpio = str_replace(' ', '', $model->numero);

        // ----------------------------------------------------------------
        // Construcción del XML con DOMDocument
        // ----------------------------------------------------------------
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $nsLR = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
        $nsSF = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

        $root = $doc->createElementNS($nsLR, 'sfLR:RegFactuSistemaFacturacion');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sf', $nsSF);
        $doc->appendChild($root);

        // ── CABECERA ────────────────────────────────────────────────────
        $cabecera = $doc->createElementNS($nsLR, 'sfLR:Cabecera');
        $root->appendChild($cabecera);

        $obligado = $doc->createElementNS($nsSF, 'sf:ObligadoEmision');
        $cabecera->appendChild($obligado);

        $obligado->appendChild($doc->createElementNS($nsSF, 'sf:NombreRazon', $nombreEmisor));
        $obligado->appendChild($doc->createElementNS($nsSF, 'sf:NIF', $nifEmisor));

        // ── REGISTRO FACTURA (wrapper obligatorio) ──────────────────────
        $regFactura = $doc->createElementNS($nsLR, 'sfLR:RegistroFactura');
        $root->appendChild($regFactura);

        $regAlta = $doc->createElementNS($nsSF, 'sf:RegistroAlta');
        $regFactura->appendChild($regAlta);

        // 1. IDVersion (obligatorio)
        $regAlta->appendChild($doc->createElementNS($nsSF, 'sf:IDVersion', '1.0'));

        // 2. IDFactura
        $idFactura = $doc->createElementNS($nsSF, 'sf:IDFactura');
        $regAlta->appendChild($idFactura);
        $idFactura->appendChild($doc->createElementNS($nsSF, 'sf:IDEmisorFactura', $nifEmisor));
        $idFactura->appendChild($doc->createElementNS($nsSF, 'sf:NumSerieFactura', $numeroLimpio));
        $idFactura->appendChild($doc->createElementNS($nsSF, 'sf:FechaExpedicionFactura', $fecha));

        // 3. NombreRazonEmisor
        $regAlta->appendChild($doc->createElementNS($nsSF, 'sf:NombreRazonEmisor', $nombreEmisor));

        // 4. TipoFactura
        $regAlta->appendChild($doc->createElementNS($nsSF, 'sf:TipoFactura', $tipoFactura));

        // 5. DescripcionOperacion
        $regAlta->appendChild($doc->createElementNS($nsSF, 'sf:DescripcionOperacion', 'Venta y servicios'));

        // 6. Indicadores opcionales (F2 requiere N en FacturaSimplificadaArt7273 según XSD)
        if ($tipoFactura !== 'F1') {
            $regAlta->appendChild($doc->createElementNS($nsSF, 'sf:FacturaSimplificadaArt7273', 'N'));
        }

        // 7. Destinatarios (opcional; excluir obligatoriamente en F2 y R5)
        if ($model->tercero && !in_array($tipoFactura, ['F2', 'R5'])) {
            $nifDest = trim($model->tercero->nif_cif ?? '');
            if (!empty($nifDest)) {
                $nombreDest = $model->tercero->razon_social ?: $model->tercero->nombre_comercial;
                $destinatarios = $doc->createElementNS($nsSF, 'sf:Destinatarios');
                $regAlta->appendChild($destinatarios);
                
                $idDestinatario = $doc->createElementNS($nsSF, 'sf:IDDestinatario');
                $destinatarios->appendChild($idDestinatario);
                $idDestinatario->appendChild($doc->createElementNS($nsSF, 'sf:NombreRazon', $nombreDest));
                $idDestinatario->appendChild($doc->createElementNS($nsSF, 'sf:NIF', $nifDest));
            }
        }

        // 8. Desglose
        $desgloseNode = $doc->createElementNS($nsSF, 'sf:Desglose');
        $regAlta->appendChild($desgloseNode);
        foreach ($desglose as $linea) {
            $base  = number_format((float)$linea['base'], 2, '.', '');
            $iva   = number_format((float)$linea['iva'], 1, '.', '');
            $cuota = number_format((float)$linea['cuota_iva'], 2, '.', '');
            
            $detalle = $doc->createElementNS($nsSF, 'sf:DetalleDesglose');
            $desgloseNode->appendChild($detalle);
            $detalle->appendChild($doc->createElementNS($nsSF, 'sf:Impuesto', '01'));
            $detalle->appendChild($doc->createElementNS($nsSF, 'sf:ClaveRegimen', '01'));
            $detalle->appendChild($doc->createElementNS($nsSF, 'sf:CalificacionOperacion', 'S1'));
            $detalle->appendChild($doc->createElementNS($nsSF, 'sf:TipoImpositivo', $iva));
            $detalle->appendChild($doc->createElementNS($nsSF, 'sf:BaseImponibleOimporteNoSujeto', $base));
            $detalle->appendChild($doc->createElementNS($nsSF, 'sf:CuotaRepercutida', $cuota));
        }

        // 9. CuotaTotal
        $regAlta->appendChild($doc->createElementNS($nsSF, 'sf:CuotaTotal', $cuotaTotal));

        // 10. ImporteTotal
        $regAlta->appendChild($doc->createElementNS($nsSF, 'sf:ImporteTotal', $total));

        // 11. Encadenamiento
        $encadenamiento = $doc->createElementNS($nsSF, 'sf:Encadenamiento');
        $regAlta->appendChild($encadenamiento);
        if ($model->verifactu_huella_anterior) {
            $class = get_class($model);
            $anterior = $class::where('verifactu_huella', $model->verifactu_huella_anterior)->first();

            if ($anterior) {
                $numAnt   = str_replace(' ', '', $anterior->numero);
                $fechaAnt = $anterior->fecha ? $anterior->fecha->format('d-m-Y') : ($anterior->completed_at ? $anterior->completed_at->format('d-m-Y') : '');
                
                $regAnterior = $doc->createElementNS($nsSF, 'sf:RegistroAnterior');
                $encadenamiento->appendChild($regAnterior);
                $regAnterior->appendChild($doc->createElementNS($nsSF, 'sf:IDEmisorFactura', $nifEmisor));
                $regAnterior->appendChild($doc->createElementNS($nsSF, 'sf:NumSerieFactura', $numAnt));
                $regAnterior->appendChild($doc->createElementNS($nsSF, 'sf:FechaExpedicionFactura', $fechaAnt));
                $regAnterior->appendChild($doc->createElementNS($nsSF, 'sf:Huella', $model->verifactu_huella_anterior));
            } else {
                $encadenamiento->appendChild($doc->createElementNS($nsSF, 'sf:PrimerRegistro', 'S'));
            }
        } else {
            $encadenamiento->appendChild($doc->createElementNS($nsSF, 'sf:PrimerRegistro', 'S'));
        }

        // 12. SistemaInformatico
        $sistema = $doc->createElementNS($nsSF, 'sf:SistemaInformatico');
        $regAlta->appendChild($sistema);
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:NombreRazon', 'SIENTIA ERP'));
        $nifDesarrollador = env('VERIFACTU_NIF_DESARROLLADOR', \App\Models\Setting::get('verifactu_nif_desarrollador', $nifEmisor));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:NIF', $nifDesarrollador));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:NombreSistemaInformatico', 'SIENTIA ERP'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:IdSistemaInformatico', '01'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:Version', '1.0'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:NumeroInstalacion', '01'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:TipoUsoPosibleSoloVerifactu', 'S'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:TipoUsoPosibleMultiOT', 'N'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:IndicadorMultiplesOT', 'N'));

        $regAlta->appendChild($doc->createElementNS($nsSF, 'sf:FechaHoraHusoGenRegistro', $fechaHoraHuso));
        $regAlta->appendChild($doc->createElementNS($nsSF, 'sf:TipoHuella', '01'));
        $regAlta->appendChild($doc->createElementNS($nsSF, 'sf:Huella', $model->verifactu_huella));

        Log::debug('VerifactuXmlService: XML Alta generado para ' . $model->numero);
        return $doc->saveXML();
    }

    /**
     * Generar el XML de Anulación de Facturación.
     */
    public function generateAnulacionXml(Model $model): string
    {
        $nifEmisor     = \App\Models\Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor', 'B00000000'));
        $nombreEmisor  = \App\Models\Setting::get('verifactu_nombre_emisor', config('verifactu.nombre_emisor', 'SIENTIA ERP'));
        
        $fecha = $model->fecha ? $model->fecha->format('d-m-Y') : ($model->completed_at ? $model->completed_at->format('d-m-Y') : now()->format('d-m-Y'));
        $fechaHoraHuso = $model->verifactu_fecha_hora_huso ?: now()->toIso8601String();
        $numeroLimpio = str_replace(' ', '', $model->numero);

        $nsLR = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
        $nsSF = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS($nsLR, 'sfLR:RegFactuSistemaFacturacion');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sf', $nsSF);
        $doc->appendChild($root);

        // CABECERA
        $cabecera = $doc->createElementNS($nsLR, 'sfLR:Cabecera');
        $root->appendChild($cabecera);

        $obligado = $doc->createElementNS($nsSF, 'sf:ObligadoEmision');
        $cabecera->appendChild($obligado);
        $obligado->appendChild($doc->createElementNS($nsSF, 'sf:NombreRazon', $nombreEmisor));
        $obligado->appendChild($doc->createElementNS($nsSF, 'sf:NIF', $nifEmisor));

        // REGISTRO FACTURA
        $regFactura = $doc->createElementNS($nsLR, 'sfLR:RegistroFactura');
        $root->appendChild($regFactura);

        $regAnulacion = $doc->createElementNS($nsSF, 'sf:RegistroAnulacion');
        $regFactura->appendChild($regAnulacion);

        $regAnulacion->appendChild($doc->createElementNS($nsSF, 'sf:IDVersion', '1.0'));

        $idFactura = $doc->createElementNS($nsSF, 'sf:IDFactura');
        $regAnulacion->appendChild($idFactura);
        $idFactura->appendChild($doc->createElementNS($nsSF, 'sf:IDEmisorFacturaAnulada', $nifEmisor));
        $idFactura->appendChild($doc->createElementNS($nsSF, 'sf:NumSerieFacturaAnulada', $numeroLimpio));
        $idFactura->appendChild($doc->createElementNS($nsSF, 'sf:FechaExpedicionFacturaAnulada', $fecha));

        $encadenamiento = $doc->createElementNS($nsSF, 'sf:Encadenamiento');
        $regAnulacion->appendChild($encadenamiento);
        if ($model->verifactu_huella_anterior) {
            $class = get_class($model);
            $anterior = $class::where('verifactu_huella', $model->verifactu_huella_anterior)->first();
            if ($anterior) {
                $numAnt   = str_replace(' ', '', $anterior->numero);
                $fechaAnt = $anterior->fecha ? $anterior->fecha->format('d-m-Y') : ($anterior->completed_at ? $anterior->completed_at->format('d-m-Y') : '');
                
                $regAnterior = $doc->createElementNS($nsSF, 'sf:RegistroAnterior');
                $encadenamiento->appendChild($regAnterior);
                $regAnterior->appendChild($doc->createElementNS($nsSF, 'sf:IDEmisorFactura', $nifEmisor));
                $regAnterior->appendChild($doc->createElementNS($nsSF, 'sf:NumSerieFactura', $numAnt));
                $regAnterior->appendChild($doc->createElementNS($nsSF, 'sf:FechaExpedicionFactura', $fechaAnt));
                $regAnterior->appendChild($doc->createElementNS($nsSF, 'sf:Huella', $model->verifactu_huella_anterior));
            } else {
                $encadenamiento->appendChild($doc->createElementNS($nsSF, 'sf:PrimerRegistro', 'S'));
            }
        } else {
            $encadenamiento->appendChild($doc->createElementNS($nsSF, 'sf:PrimerRegistro', 'S'));
        }

        $sistema = $doc->createElementNS($nsSF, 'sf:SistemaInformatico');
        $regAnulacion->appendChild($sistema);
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:NombreRazon', 'SIENTIA ERP'));
        $nifDesarrollador = env('VERIFACTU_NIF_DESARROLLADOR', \App\Models\Setting::get('verifactu_nif_desarrollador', $nifEmisor));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:NIF', $nifDesarrollador));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:NombreSistemaInformatico', 'SIENTIA ERP'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:IdSistemaInformatico', '01'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:Version', '1.0'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:NumeroInstalacion', '01'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:TipoUsoPosibleSoloVerifactu', 'S'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:TipoUsoPosibleMultiOT', 'N'));
        $sistema->appendChild($doc->createElementNS($nsSF, 'sf:IndicadorMultiplesOT', 'N'));

        $regAnulacion->appendChild($doc->createElementNS($nsSF, 'sf:FechaHoraHusoGenRegistro', $fechaHoraHuso));
        $regAnulacion->appendChild($doc->createElementNS($nsSF, 'sf:TipoHuella', '01'));
        $regAnulacion->appendChild($doc->createElementNS($nsSF, 'sf:Huella', $model->verifactu_huella));

        Log::debug('VerifactuXmlService: XML Anulación generado para ' . $model->numero);
        return $doc->saveXML();
    }

    /**
     * Obtener desglose de impuestos normalizado para el XML.
     */
    public function getDesgloseParaXml(Model $model): array
    {
        if ($model instanceof Documento) {
            return $model->getDesgloseImpuestos();
        }

        // Para Tickets, agrupar items por IVA
        $desglose = [];
        foreach ($model->items as $item) {
            $ivaKey = (string)($item->tax_rate ?? 0);
            if (!isset($desglose[$ivaKey])) {
                $desglose[$ivaKey] = ['iva' => (float)($item->tax_rate ?? 0), 'base' => 0.0, 'cuota_iva' => 0.0];
            }
            $desglose[$ivaKey]['base']     += (float)($item->subtotal ?? 0);
            $desglose[$ivaKey]['cuota_iva'] += (float)($item->tax_amount ?? 0);
        }
        return array_values($desglose);
    }
}
