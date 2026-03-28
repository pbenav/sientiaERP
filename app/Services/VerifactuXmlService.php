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

        // FechaHoraHusoGenRegistro: ISO 8601 con timezone (requerido por XSD dateTime)
        $fechaHoraHuso = now()->toIso8601String(); // ej: 2026-03-25T09:00:00+01:00

        $total     = number_format((float)$model->total, 2, '.', '');
        $desglose  = $this->getDesgloseParaXml($model);

        // Calcular CuotaTotal (suma de cuotas de todos los tramos)
        $cuotaTotal = 0.0;
        foreach ($desglose as $linea) {
            $cuotaTotal += (float)$linea['cuota_iva'];
        }
        $cuotaTotal = number_format($cuotaTotal, 2, '.', '');

        $numeroLimpio = str_replace(' ', '', $model->numero);

        // ----------------------------------------------------------------
        // Construcción del XML
        // ----------------------------------------------------------------
        $NS_LR = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
        $NS_SF = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= "<sfLR:RegFactuSistemaFacturacion xmlns:sfLR=\"{$NS_LR}\" xmlns:sf=\"{$NS_SF}\">";

        // ── CABECERA ────────────────────────────────────────────────────
        $xml .= '<sfLR:Cabecera>';
        $xml .= '<sf:ObligadoEmision>';
        $xml .= "<sf:NombreRazon>{$nombreEmisor}</sf:NombreRazon>";
        $xml .= "<sf:NIF>{$nifEmisor}</sf:NIF>";
        $xml .= '</sf:ObligadoEmision>';
        $xml .= '</sfLR:Cabecera>';

        // ── REGISTRO FACTURA (wrapper obligatorio) ──────────────────────
        $xml .= '<sfLR:RegistroFactura>';
        $xml .= '<sf:RegistroAlta>';

        // 1. IDVersion (obligatorio)
        $xml .= '<sf:IDVersion>1.0</sf:IDVersion>';

        // 2. IDFactura
        $xml .= '<sf:IDFactura>';
        $xml .= "<sf:IDEmisorFactura>{$nifEmisor}</sf:IDEmisorFactura>";
        $xml .= "<sf:NumSerieFactura>{$numeroLimpio}</sf:NumSerieFactura>";
        $xml .= "<sf:FechaExpedicionFactura>{$fecha}</sf:FechaExpedicionFactura>";
        $xml .= '</sf:IDFactura>';

        // 3. NombreRazonEmisor
        $xml .= "<sf:NombreRazonEmisor>{$nombreEmisor}</sf:NombreRazonEmisor>";

        // 4. TipoFactura
        $xml .= "<sf:TipoFactura>{$tipoFactura}</sf:TipoFactura>";

        // 5. DescripcionOperacion
        $xml .= '<sf:DescripcionOperacion>Venta y servicios</sf:DescripcionOperacion>';

        // 6. Indicadores opcionales (según XSD: minOccurs=0)
        // FacturaSimplificadaArt7273: solo para F2/F3; para F1 no se incluye o se pone N
        if ($tipoFactura !== 'F1') {
            $xml .= '<sf:FacturaSimplificadaArt7273>N</sf:FacturaSimplificadaArt7273>';
        }
        // FacturaSinIdentifDestinatarioArt61d: solo si aplica
        // EmitidaPorTerceroODestinatario: solo si aplica

        // 7. Destinatarios (opcional; excluir obligatoriamente en F2 y R5)
        if ($model->tercero && !in_array($tipoFactura, ['F2', 'R5'])) {
            $nifDest = trim($model->tercero->nif_cif ?? '');
            if (!empty($nifDest)) {
                $nombreDest = htmlspecialchars($model->tercero->razon_social ?? '', ENT_XML1);
                $xml .= '<sf:Destinatarios>';
                $xml .= '<sf:IDDestinatario>';
                $xml .= "<sf:NombreRazon>{$nombreDest}</sf:NombreRazon>";
                $xml .= "<sf:NIF>{$nifDest}</sf:NIF>";
                $xml .= '</sf:IDDestinatario>';
                $xml .= '</sf:Destinatarios>';
            }
        }

        // 8. Desglose
        $xml .= '<sf:Desglose>';

        foreach ($desglose as $linea) {
            $base  = number_format((float)$linea['base'], 2, '.', '');
            $iva   = number_format((float)$linea['iva'], 1, '.', '');
            $cuota = number_format((float)$linea['cuota_iva'], 2, '.', '');
            
            $xml .= '<sf:DetalleDesglose>';
            $xml .= '<sf:Impuesto>01</sf:Impuesto>';
            $xml .= '<sf:ClaveRegimen>01</sf:ClaveRegimen>';
            $xml .= '<sf:CalificacionOperacion>S1</sf:CalificacionOperacion>';
            $xml .= "<sf:TipoImpositivo>{$iva}</sf:TipoImpositivo>";
            $xml .= "<sf:BaseImponibleOimporteNoSujeto>{$base}</sf:BaseImponibleOimporteNoSujeto>";
            $xml .= "<sf:CuotaRepercutida>{$cuota}</sf:CuotaRepercutida>";
            $xml .= '</sf:DetalleDesglose>';
        }

        $xml .= '</sf:Desglose>';

        // 9. CuotaTotal (obligatorio según XSD)
        $xml .= "<sf:CuotaTotal>{$cuotaTotal}</sf:CuotaTotal>";

        // 10. ImporteTotal
        $xml .= "<sf:ImporteTotal>{$total}</sf:ImporteTotal>";

        // 11. Encadenamiento (obligatorio; PrimerRegistro o RegistroAnterior)
        $anterior = $model->getUltimaAceptada();
        $xml .= '<sf:Encadenamiento>';
        if ($anterior && $anterior->verifactu_huella) {
            $numAnt   = str_replace(' ', '', $anterior->numero);
            $fechaAnt = $anterior->fecha
                ? $anterior->fecha->format('d-m-Y')
                : ($anterior->completed_at ? $anterior->completed_at->format('d-m-Y') : '');
            $xml .= '<sf:RegistroAnterior>';
            $xml .= "<sf:IDEmisorFactura>{$nifEmisor}</sf:IDEmisorFactura>";
            $xml .= "<sf:NumSerieFactura>{$numAnt}</sf:NumSerieFactura>";
            $xml .= "<sf:FechaExpedicionFactura>{$fechaAnt}</sf:FechaExpedicionFactura>";
            $xml .= "<sf:Huella>{$anterior->verifactu_huella}</sf:Huella>";
            $xml .= '</sf:RegistroAnterior>';
        } else {
            // Primer registro de la cadena
            $xml .= '<sf:PrimerRegistro>S</sf:PrimerRegistro>';
        }
        $xml .= '</sf:Encadenamiento>';

        // 12. SistemaInformatico
        $xml .= '<sf:SistemaInformatico>';
        $xml .= '<sf:NombreRazon>SIENTIA ERP</sf:NombreRazon>';
        $nifDesarrollador = env('VERIFACTU_NIF_DESARROLLADOR', \App\Models\Setting::get('verifactu_nif_desarrollador', $nifEmisor));
        $xml .= "<sf:NIF>{$nifDesarrollador}</sf:NIF>";
        $xml .= '<sf:NombreSistemaInformatico>SIENTIA ERP</sf:NombreSistemaInformatico>';
        $xml .= '<sf:IdSistemaInformatico>01</sf:IdSistemaInformatico>';
        $xml .= '<sf:Version>1.0</sf:Version>';
        $xml .= '<sf:NumeroInstalacion>01</sf:NumeroInstalacion>';
        $xml .= '<sf:TipoUsoPosibleSoloVerifactu>S</sf:TipoUsoPosibleSoloVerifactu>';
        $xml .= '<sf:TipoUsoPosibleMultiOT>N</sf:TipoUsoPosibleMultiOT>';
        $xml .= '<sf:IndicadorMultiplesOT>N</sf:IndicadorMultiplesOT>';
        $xml .= '</sf:SistemaInformatico>';

        // 13. FechaHoraHusoGenRegistro (obligatorio; formato ISO 8601 dateTime)
        $xml .= "<sf:FechaHoraHusoGenRegistro>{$fechaHoraHuso}</sf:FechaHoraHusoGenRegistro>";

        // 14. TipoHuella (01 = SHA-256)
        $xml .= '<sf:TipoHuella>01</sf:TipoHuella>';

        // 15. Huella
        $xml .= "<sf:Huella>{$model->verifactu_huella}</sf:Huella>";

        $xml .= '</sf:RegistroAlta>';
        $xml .= '</sfLR:RegistroFactura>';
        $xml .= '</sfLR:RegFactuSistemaFacturacion>';

        Log::debug('VerifactuXmlService: XML generado para ' . $model->numero, ['xml' => $xml]);

        return $xml;
    }

    /**
     * Obtener desglose de impuestos normalizado para el XML.
     */
    protected function getDesgloseParaXml(Model $model): array
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
