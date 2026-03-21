<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

class FacturaeXmlService
{
    private const SCHEMA_VERSION = '3.2.2';
    
    /**
     * Generar XML Facturae 3.2.2 para un documento.
     */
    public function generateXml(Documento $documento): string
    {
        if (!in_array($documento->tipo, ['factura', 'factura_compra'])) {
            throw new \Exception('Solo se pueden generar Facturae desde facturas confirmadas.');
        }

        if (empty($documento->numero)) {
            throw new \Exception('La factura debe estar confirmada y tener número asignado.');
        }

        // Crear elemento raíz con los namespaces necesarios
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><fe:Facturae xmlns:fe="http://www.facturae.gob.es/formato/versiones/facturaev3_2_2.xml" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"></fe:Facturae>');

        $this->addFileHeader($xml);
        $this->addParties($xml, $documento);
        $this->addInvoices($xml, $documento);

        return $xml->asXML();
    }

    /**
     * Añadir cabecera del fichero.
     */
    protected function addFileHeader(SimpleXMLElement $xml): void
    {
        $header = $xml->addChild('FileHeader');
        $header->addChild('SchemaVersion', self::SCHEMA_VERSION);
        $header->addChild('Modality', 'I'); // I = Individual
        $header->addChild('InvoiceIssuerType', 'EM'); // EM = Emisor (Proveedor)
        
        $batch = $header->addChild('Batch');
        $batch->addChild('BatchIdentifier', 'FACT' . now()->format('YmdHis'));
        $batch->addChild('InvoicesCount', '1');
        
        // Totales del lote (en este caso solo una factura)
        // Estos se calculan más adelante normalmente, o se ponen aquí
        // Nota: Facturae requiere importes con precisión específica
    }

    /**
     * Añadir datos del emisor y receptor.
     */
    protected function addParties(SimpleXMLElement $xml, Documento $documento): void
    {
        $parties = $xml->addChild('Parties');
        
        // 1. Seller (Nosotros)
        $seller = $parties->addChild('SellerParty');
        $this->addTaxIdentification($seller, Setting::get('verifactu_nif_emisor'), 'J'); // TODO: Detectar si es persona física
        $this->addPartyData($seller, Setting::get('verifactu_nombre_emisor'), 'empresa@ejemplo.com');

        // 2. Buyer (El cliente)
        $buyer = $parties->addChild('BuyerParty');
        $tercero = $documento->tercero;
        
        $this->addTaxIdentification($buyer, $tercero->nif_cif, $tercero->es_persona_fisica ? 'F' : 'J');
        $this->addPartyData($buyer, $tercero->razon_social ?: $tercero->nombre_comercial, $tercero->email);
        
        // Si tiene DIR3, añadirlo
        if ($tercero->dir3_oficina_contable || $tercero->dir3_organo_gestor) {
            $adminCenters = $buyer->addChild('AdministrativeCentres');
            
            if ($tercero->dir3_oficina_contable) {
                $this->addAdminCenter($adminCenters, '01', $tercero->dir3_oficina_contable, 'Oficina Contable');
            }
            if ($tercero->dir3_organo_gestor) {
                $this->addAdminCenter($adminCenters, '02', $tercero->dir3_organo_gestor, 'Órgano Gestor');
            }
            if ($tercero->dir3_unidad_tramitadora) {
                $this->addAdminCenter($adminCenters, '03', $tercero->dir3_unidad_tramitadora, 'Unidad Tramitadora');
            }
        }
    }

    /**
     * Añadir el bloque de Invoices.
     */
    protected function addInvoices(SimpleXMLElement $xml, Documento $documento): void
    {
        $invoices = $xml->addChild('Invoices');
        $invoice = $invoices->addChild('Invoice');
        
        $header = $invoice->addChild('InvoiceHeader');
        $header->addChild('InvoiceNumber', $documento->numero);
        $header->addChild('InvoiceSeriesCode', $documento->serie ?: 'A');
        $header->addChild('InvoiceDocumentType', 'FC'); // FC = Factura completa
        $header->addChild('InvoiceClass', 'OO'); // OO = Original
        
        $invoice->addChild('InvoiceIssueDate', $documento->fecha->format('Y-m-d'));
        
        // Impuestos
        $taxesOutputs = $invoice->addChild('TaxesOutputs');
        $desgloseResumen = $documento->getDesgloseImpuestos();
        
        foreach ($desgloseResumen as $lineaIva) {
            $tax = $taxesOutputs->addChild('Tax');
            $tax->addChild('TaxCode', '01'); // 01 = IVA
            $tax->addChild('TaxRate', number_format($lineaIva['iva'], 2, '.', ''));
            $tax->addChild('TaxableBase')->addChild('TotalAmount', number_format($lineaIva['base'], 2, '.', ''));
            $tax->addChild('TaxAmount')->addChild('TotalAmount', number_format($lineaIva['cuota_iva'], 2, '.', ''));
        }

        // Totales
        $totals = $invoice->addChild('InvoiceTotals');
        $totals->addChild('TotalGrossAmount', number_format($documento->subtotal, 2, '.', ''));
        $totals->addChild('TotalTaxOutputs', number_format($documento->iva, 2, '.', ''));
        $totals->addChild('TotalInvoiceAmount', number_format($documento->total, 2, '.', ''));
        
        // Líneas
        $items = $invoice->addChild('Items');
        foreach ($documento->lineas as $linea) {
            $item = $items->addChild('InvoiceLine');
            $item->addChild('ItemDescription', substr($linea->descripcion, 0, 2500));
            $item->addChild('Quantity', number_format($linea->cantidad, 2, '.', ''));
            $item->addChild('UnitOfMeasure', '01'); // 01 = Unidades
            $item->addChild('UnitPriceWithoutTax', number_format($linea->precio_unitario, 2, '.', ''));
            $item->addChild('TotalCost', number_format($linea->subtotal, 2, '.', ''));
            $item->addChild('GrossAmount', number_format($linea->subtotal, 2, '.', ''));
            
            $itemTaxes = $item->addChild('TaxesOutputs');
            $tax = $itemTaxes->addChild('Tax');
            $tax->addChild('TaxCode', '01');
            $tax->addChild('TaxRate', number_format($linea->iva, 2, '.', ''));
            $tax->addChild('TaxableBase')->addChild('TotalAmount', number_format($linea->subtotal, 2, '.', ''));
            $tax->addChild('TaxAmount')->addChild('TotalAmount', number_format($linea->importe_iva, 2, '.', ''));
        }
    }

    protected function addTaxIdentification(SimpleXMLElement $parent, ?string $nif, string $residenceType): void
    {
        $id = $parent->addChild('TaxIdentification');
        $id->addChild('PersonTypeCode', $residenceType); // J = Jurídica, F = Física
        $id->addChild('ResidenceTypeCode', 'R'); // R = Residente en España
        $id->addChild('TaxIdentificationNumber', $nif);
    }

    protected function addPartyData(SimpleXMLElement $parent, ?string $name, ?string $email): void
    {
        // En Facturae los nombres van en estructuras específicas
        // Para simplificar usamos LegalEntity o Individual
        $entity = $parent->addChild('LegalEntity');
        $entity->addChild('CorporateName', $name);
        
        // Dirección (opcional pero recomendada)
        $address = $entity->addChild('RegistrationData'); // No exactamente la dirección fiscal pero sirve como placeholder
        // TODO: Mapear dirección fiscal correctamente
    }

    protected function addAdminCenter(SimpleXMLElement $parent, string $role, string $code, string $description): void
    {
        $center = $parent->addChild('AdministrativeCentre');
        $center->addChild('CentreRoleCode', $role); // 01 Contable, 02 Gestor, 03 Tramitadora
        $center->addChild('CentreCode', $code);
        $center->addChild('CentreDescription', $description);
    }
}
