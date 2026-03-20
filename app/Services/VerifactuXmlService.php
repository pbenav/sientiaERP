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
        $nifEmisor = \App\Models\Setting::get('verifactu_nif_emisor', config('verifactu.nif_emisor', 'B00000000'));
        $nombreEmisor = \App\Models\Setting::get('verifactu_nombre_emisor', config('verifactu.nombre_emisor', 'SienteERP Demo'));
        
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><sum:RegAltaRegistroFacturacion xmlns:sum="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"></sum:RegAltaRegistroFacturacion>');
        
        // Cabecera del mensaje
        $cabecera = $xml->addChild('sum:Cabecera');
        $cabecera->addChild('sum:IDEmisorFactura')->addChild('sum:NIF', $nifEmisor);
        
        // El contenido del registro
        $registro = $xml->addChild('sum:RegistroAlta');
        $idReg = $registro->addChild('sum:IDRegistroFacturacion');
        $idReg->addChild('sum:IDEmisorFactura')->addChild('sum:NIF', $nifEmisor);
        $idReg->addChild('sum:NumSerieFactura', $model->numero);
        $fecha = $model->fecha ? $model->fecha->format('d-m-Y') : ($model->completed_at ? $model->completed_at->format('d-m-Y') : now()->format('d-m-Y'));
        $idReg->addChild('sum:FechaExpedicionFactura', $fecha);

        $registro->addChild('sum:NombreRazonEmisor', $nombreEmisor);
        $registro->addChild('sum:TipoFactura', ($model instanceof Ticket ? 'F2' : 'F1')); // F2 = Simplificada, F1 = Completa
        $registro->addChild('sum:ConceptoFactura', 'Venta y servicios');
        
        // Desglose de IVA
        $desgloseResumen = $this->getDesgloseParaXml($model);
        $cuotas = $registro->addChild('sum:DesgloseIVA');
        foreach ($desgloseResumen as $lineaIva) {
            $det = $cuotas->addChild('sum:DetalleIVA');
            $det->addChild('sum:BaseImponible', number_format($lineaIva['base'], 2, '.', ''));
            $det->addChild('sum:TipoImpositivo', number_format($lineaIva['iva'], 2, '.', ''));
            $det->addChild('sum:CuotaRepercutida', number_format($lineaIva['cuota_iva'], 2, '.', ''));
        }

        $registro->addChild('sum:ImporteTotal', number_format($model->total, 2, '.', ''));

        // Encadenamiento (Crucial para Verifactu)
        if ($model->verifactu_huella_anterior) {
            $encadenamiento = $registro->addChild('sum:Encadenamiento');
            $regAnt = $encadenamiento->addChild('sum:RegistroAnterior');
            $idAnt = $regAnt->addChild('sum:IDRegistroFacturacion');
            $idAnt->addChild('sum:IDEmisorFactura')->addChild('sum:NIF', $nifEmisor);
            // Si el anterior es otro modelo, buscarlo
            $idAnt->addChild('sum:NumSerieFactura', $this->getNumeroAnterior($model));
            $idAnt->addChild('sum:FechaExpedicionFactura', $this->getFechaAnterior($model));
            $regAnt->addChild('sum:Huella', $model->verifactu_huella_anterior);
        }

        // Huella del mensaje actual
        $registro->addChild('sum:Huella', $model->verifactu_huella);
        
        // Bloque de Sistema Informático (Semaforización)
        $sif = $registro->addChild('sum:SistemaInformatico');
        $sif->addChild('sum:NombreSistema', 'SIENTEERP');
        $sif->addChild('sum:Versión', config('app.version', '0.3.0'));
        $sif->addChild('sum:NIFEntidadDesarrolladora', 'B12345678'); // NIF de la empresa propietaria de SienteERP
        $sif->addChild('sum:TipoUso', '01'); // 01 = Uso general

        return $xml->asXML();
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
