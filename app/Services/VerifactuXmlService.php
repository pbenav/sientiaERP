<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class VerifactuXmlService
{
    /**
     * Generar el XML de Alta de Facturación según esquemas de la AEAT.
     */
    public function generateAltaXml(Model $model): string
    {
        // Forzar carga de relación para integridad en el XML
        // Forzar carga de relación para integridad en el XML
        if (($model instanceof Documento || $model instanceof Ticket) && !$model->relationLoaded('tercero')) {
            $model->load('tercero');
        }
        $nifEmisor = \App\Models\Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor', 'B00000000'));
        $nombreEmisor = \App\Models\Setting::get('verifactu_nombre_emisor', config('verifactu.nombre_emisor', 'BENAVIDES ORTIGOSA PABLO ANTONIO'));
        $tipoFactura = ($model instanceof Documento && $model->tipo === 'factura') ? 'F1' : 'F2';
        $fecha = $model->fecha ? $model->fecha->format('d-m-Y') : ($model->completed_at ? $model->completed_at->format('d-m-Y') : now()->format('d-m-Y'));
        $total = number_format($model->total, 2, '.', '');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<sfLR:RegFactuSistemaFacturacion xmlns:sfLR="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd" xmlns:sf="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd">';
        
        // CABECERA
        $xml .= "<sfLR:Cabecera>";
        $xml .= "<sf:ObligadoEmision>";
        $xml .= "<sf:NombreRazon>$nombreEmisor</sf:NombreRazon>";
        $xml .= "<sf:NIF>$nifEmisor</sf:NIF>";
        $xml .= "</sf:ObligadoEmision>";
        $xml .= "</sfLR:Cabecera>";
        
        // REGISTRO ALTA
        $xml .= "<sfLR:RegistroAlta>";
        $xml .= "<sfLR:RegistroFactura>";
        
        $xml .= "<sfLR:IDFactura>";
        $xml .= "<sfLR:IDEmisorFactura><sf:NIF>$nifEmisor</sf:NIF></sfLR:IDEmisorFactura>";
        $numeroLimpio = str_replace(' ', '', $model->numero);
        $xml .= "<sfLR:NumSerieFactura>{$numeroLimpio}</sfLR:NumSerieFactura>";
        $xml .= "<sfLR:FechaExpedicionFactura>$fecha</sfLR:FechaExpedicionFactura>";
        $xml .= "</sfLR:IDFactura>";
        
        $xml .= "<sf:NombreRazonEmisor>$nombreEmisor</sf:NombreRazonEmisor>";
        $xml .= "<sf:TipoFactura>$tipoFactura</sf:TipoFactura>";
        $xml .= "<sf:DescripcionOperacion>Venta y servicios</sf:DescripcionOperacion>";
        
        // Indicadores obligatorios (S/N)
        $xml .= "<sf:FacturaSimplificadaArticulos72_73>N</sf:FacturaSimplificadaArticulos72_73>";
        $xml .= "<sf:FacturaSinIdentifDestinatarioArticulo6_1_d>N</sf:FacturaSinIdentifDestinatarioArticulo6_1_d>";
        $xml .= "<sf:Macrodato>N</sf:Macrodato>";
        $xml .= "<sf:EmitidaPorTercerosODestinatario>N</sf:EmitidaPorTercerosODestinatario>";
        
        // DESTINATARIO (Mandatorio para F1)
        if ($model->tercero) {
            $nifDest = $model->tercero->cif ?: ($model->tercero->nif ?: '');
            $xml .= "<sfLR:Destinatario>";
            $xml .= "<sf:NombreRazon>{$model->tercero->razon_social}</sf:NombreRazon>";
            $xml .= "<sf:NIF>$nifDest</sf:NIF>";
            $xml .= "</sfLR:Destinatario>";
        }
        
        $xml .= "<sfLR:Desglose>";
        $xml .= "<sfLR:DetalleDesglose>";
        $xml .= "<sf:Impuesto>01</sf:Impuesto>";
        $xml .= "<sf:ClaveRegimen>01</sf:ClaveRegimen>";
        foreach ($this->getDesgloseParaXml($model) as $linea) {
            $base = number_format($linea['base'], 2, '.', '');
            $iva = number_format($linea['iva'], 1, '.', '');
            $cuota = number_format($linea['cuota_iva'], 2, '.', '');
            $xml .= "<sf:BaseImponible>$base</sf:BaseImponible>";
            $xml .= "<sf:TipoImpositivo>$iva</sf:TipoImpositivo>";
            $xml .= "<sf:CuotaRepercutida>$cuota</sf:CuotaRepercutida>";
        }
        $xml .= "</sfLR:DetalleDesglose>";
        $xml .= "</sfLR:Desglose>";
        
        $xml .= "<sf:ImporteTotal>$total</sf:ImporteTotal>";
        
        // Encadenamiento
        $anterior = $model->getUltimaAceptada();
        if ($anterior) {
            $numAnt = str_replace(' ', '', $anterior->numero);
            $fechaAnt = $anterior->fecha->format('d-m-Y');
            $xml .= "<sfLR:Encadenamiento>";
            $xml .= "<sfLR:RegistroAnterior>";
            $xml .= "<sfLR:IDFactura>";
            $xml .= "<sfLR:IDEmisorFactura><sf:NIF>$nifEmisor</sf:NIF></sfLR:IDEmisorFactura>";
            $xml .= "<sfLR:NumSerieFactura>$numAnt</sfLR:NumSerieFactura>";
            $xml .= "<sfLR:FechaExpedicionFactura>$fechaAnt</sfLR:FechaExpedicionFactura>";
            $xml .= "</sfLR:IDFactura>";
            $xml .= "<sfLR:Huella>{$anterior->verifactu_huella}</sfLR:Huella>";
            $xml .= "</sfLR:RegistroAnterior>";
            $xml .= "</sfLR:Encadenamiento>";
        }
        
        $xml .= "<sfLR:Huella>{$model->verifactu_huella}</sfLR:Huella>";
        
        $xml .= "<sfLR:SistemaInformatico>";
        $xml .= "<sf:NombreRazon>SIENTIA ERP</sf:NombreRazon>";
        $xml .= "<sf:NIF>24265003A</sf:NIF>";
        $xml .= "<sf:NombreSistemaInformatico>SIENTIA ERP</sf:NombreSistemaInformatico>";
        $xml .= "<sf:IdSistemaInformatico>01</sf:IdSistemaInformatico>";
        $xml .= "<sf:Version>1.0</sf:Version>";
        $xml .= "<sf:NumeroInstalacion>01</sf:NumeroInstalacion>";
        $xml .= "<sf:TipoUsoSistemaInformatico>01</sf:TipoUsoSistemaInformatico>";
        $xml .= "</sfLR:SistemaInformatico>";
        
        $xml .= "</sfLR:RegistroFactura>";
        $xml .= "</sfLR:RegistroAlta>";
        $xml .= "</sfLR:RegFactuSistemaFacturacion>";
        
        return $xml;
    }

    protected function getDesgloseParaXml(Model $model): array
    {
        if ($model instanceof Documento) {
            return $model->getDesgloseImpuestos();
        }
        
        // Para Tickets, agrupar items por IVA si no tienen método getDesgloseImpuestos
        $desglose = [];
        foreach ($model->items as $item) {
            $ivaKey = (string)$item->tax_rate;
            if (!isset($desglose[$ivaKey])) {
                $desglose[$ivaKey] = ['iva' => $item->tax_rate, 'base' => 0, 'cuota_iva' => 0];
            }
            $desglose[$ivaKey]['base'] += $item->subtotal;
            $desglose[$ivaKey]['cuota_iva'] += $item->tax_amount;
        }
        return array_values($desglose);
    }

    protected function getNumeroAnterior(Model $model): string
    {
        $class = get_class($model);
        $anterior = $class::where('verifactu_huella', $model->verifactu_huella_anterior)->first();
        return $anterior ? $anterior->numero : '';
    }

    protected function getFechaAnterior(Model $model): string
    {
        $class = get_class($model);
        $anterior = $class::where('verifactu_huella', $model->verifactu_huella_anterior)->first();
        if ($anterior) {
           return $anterior->fecha ? $anterior->fecha->format('d-m-Y') : ($anterior->completed_at ? $anterior->completed_at->format('d-m-Y') : '');
        }
        return '';
    }
}
