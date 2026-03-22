<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use DOMDocument;

class FacturaeXmlService
{
    private const SCHEMA_VERSION = '3.2.2';
    private CertificateService $certService;

    public function __construct(CertificateService $certService)
    {
        $this->certService = $certService;
    }
    
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

        // Crear elemento raíz con el namespace por defecto para Facturae
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Facturae xmlns="http://www.facturae.gob.es/formato/versiones/facturaev3_2_2.xml" xmlns:ds="http://www.w3.org/2000/09/xmldsig#"></Facturae>');

        $this->addFileHeader($xml, $documento);
        $this->addParties($xml, $documento);
        $this->addInvoices($xml, $documento);

        $unsignedXml = $xml->asXML();
        
        // Firma digital si hay certificado configurado
        $certPath = Setting::get('facturae_cert_path') ?: Setting::get('verifactu_cert_path');
        if ($certPath) {
            return $this->signXml($unsignedXml);
        }

        return $unsignedXml;
    }

    /**
     * Añadir cabecera del fichero.
     */
    protected function addFileHeader(SimpleXMLElement $xml, Documento $documento): void
    {
        $header = $xml->addChild('FileHeader');
        $header->addChild('SchemaVersion', self::SCHEMA_VERSION);
        $header->addChild('Modality', 'I'); // I = Individual
        $header->addChild('InvoiceIssuerType', 'EM'); // EM = Emisor (Proveedor)
        
        $batch = $header->addChild('Batch');
        $batch->addChild('BatchIdentifier', 'FACT' . $documento->numero . '-' . now()->format('YmdHis'));
        $batch->addChild('InvoicesCount', '1');
        
        $batch->addChild('TotalInvoicesAmount')->addChild('TotalAmount', number_format($documento->total, 2, '.', ''));
        $batch->addChild('TotalOutstandingAmount')->addChild('TotalAmount', number_format($documento->total, 2, '.', ''));
        $batch->addChild('TotalExecutableAmount')->addChild('TotalAmount', number_format($documento->total, 2, '.', ''));
        $batch->addChild('BatchCurrency', 'EUR');
    }

    /**
     * Añadir datos del emisor y receptor.
     */
    protected function addParties(SimpleXMLElement $xml, Documento $documento): void
    {
        $parties = $xml->addChild('Parties');
        
        // 1. Seller (Nosotros)
        $seller = $parties->addChild('SellerParty');
        $this->addTaxIdentification($seller, Setting::get('verifactu_nif_emisor'), 'J');
        $this->addPartyData($seller, Setting::get('verifactu_nombre_emisor'), [
            'address' => Setting::get('verifactu_direccion_emisor', 'Calle Ejemplo, 1'),
            'post_code' => Setting::get('verifactu_cp_emisor', '28001'),
            'town' => Setting::get('verifactu_poblacion_emisor', 'Madrid'),
            'province' => Setting::get('verifactu_provincia_emisor', 'Madrid'),
            'country' => 'ESP'
        ]);

        // 2. Buyer (El cliente)
        $buyer = $parties->addChild('BuyerParty');
        $tercero = $documento->tercero;
        
        $this->addTaxIdentification($buyer, $tercero->nif_cif, $tercero->es_persona_fisica ? 'F' : 'J');
        $this->addPartyData($buyer, $tercero->razon_social ?: $tercero->nombre_comercial, [
            'address' => $tercero->direccion_fiscal,
            'post_code' => $tercero->codigo_postal_fiscal,
            'town' => $tercero->poblacion_fiscal,
            'province' => $tercero->provincia_fiscal,
            'country' => 'ESP'
        ]);
        
        // Si tiene DIR3, añadirlo (Obligatorio para FACe)
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
        
        // Líneas (items) - DEBEN ir antes de PaymentDetails
        $items = $invoice->addChild('Items');
        foreach ($documento->lineas as $linea) {
            $item = $items->addChild('InvoiceLine');
            $item->addChild('ItemDescription', substr($linea->descripcion, 0, 2500));
            $item->addChild('Quantity', number_format($linea->cantidad, 2, '.', ''));
            $item->addChild('UnitOfMeasure', '01'); // 01 = Unidades
            $item->addChild('UnitPriceWithoutTax', number_format($linea->precio_unitario, 6, '.', ''));
            $item->addChild('TotalCost', number_format($linea->subtotal, 2, '.', ''));
            $item->addChild('GrossAmount', number_format($linea->subtotal, 2, '.', ''));
            
            $itemTaxes = $item->addChild('TaxesOutputs');
            $tax = $itemTaxes->addChild('Tax');
            $tax->addChild('TaxCode', '01');
            $tax->addChild('TaxRate', number_format($linea->iva, 2, '.', ''));
            $tax->addChild('TaxableBase')->addChild('TotalAmount', number_format($linea->subtotal, 2, '.', ''));
            $tax->addChild('TaxAmount')->addChild('TotalAmount', number_format($linea->importe_iva, 2, '.', ''));
        }

        // Formas de pago (PaymentDetails) - DEBEN ir después de Items
        if ($documento->formaPago && $documento->formaPago->tipo === 'transferencia' && $documento->tercero->iban) {
            $paymentDetails = $invoice->addChild('PaymentDetails');
            $installment = $paymentDetails->addChild('Installment');
            $installment->addChild('InstallmentDueDate', $documento->fecha_vencimiento ? $documento->fecha_vencimiento->format('Y-m-d') : $documento->fecha->format('Y-m-d'));
            $installment->addChild('InstallmentAmount', number_format($documento->total, 2, '.', ''));
            $installment->addChild('PaymentMeans', '04'); // 04 = Transferencia
            
            $account = $installment->addChild('AccountToBeCredited');
            $account->addChild('IBAN', str_replace(' ', '', $documento->tercero->iban));
        }
    }

    protected function addTaxIdentification(SimpleXMLElement $parent, ?string $nif, string $residenceType): void
    {
        $id = $parent->addChild('TaxIdentification');
        $id->addChild('PersonTypeCode', $residenceType); // J = Jurídica, F = Física
        $id->addChild('ResidenceTypeCode', 'R'); // R = Residente en España
        $id->addChild('TaxIdentificationNumber', $nif);
    }

    protected function addPartyData(SimpleXMLElement $parent, ?string $name, array $addressData = []): void
    {
        $entity = $parent->addChild('LegalEntity');
        $entity->addChild('CorporateName', $name);
        
        if (!empty($addressData)) {
            $address = $entity->addChild('AddressInSpain');
            $address->addChild('Address', substr($addressData['address'] ?? 'Calle Desconocida', 0, 80));
            $address->addChild('PostCode', substr($addressData['post_code'] ?? '00000', 0, 5));
            $address->addChild('Town', substr($addressData['town'] ?? 'Ciudad', 0, 50));
            $address->addChild('Province', substr($addressData['province'] ?? 'Provincia', 0, 20));
            $address->addChild('CountryCode', 'ESP');
        }
    }

    protected function addAdminCenter(SimpleXMLElement $parent, string $role, string $code, string $description): void
    {
        $center = $parent->addChild('AdministrativeCentre');
        $center->addChild('CentreRoleCode', $role); // 01 Contable, 02 Gestor, 03 Tramitadora
        $center->addChild('CentreCode', $code);
        $center->addChild('CentreDescription', $description);
    }

    /**
     * Firmar el XML con XAdES-EPES.
     */
    protected function signXml(string $xmlContent): string
    {
        $certPath = Setting::get('facturae_cert_path') ?: Setting::get('verifactu_cert_path');
        $certPass = Setting::get('facturae_cert_password') ?: Setting::get('verifactu_cert_password');
        
        if (!$certPath) {
            Log::warning('No se pudo firmar el Facturae: ruta de certificado no configurada.');
            return $xmlContent;
        }

        // Obtener trayectoria real según el disco (igual que en FaceService)
            $realPath = '';
            if (Storage::disk('local')->exists($certPath)) {
                $realPath = Storage::disk('local')->path($certPath);
            } elseif (Storage::disk('public')->exists($certPath)) {
                $realPath = Storage::disk('public')->path($certPath);
            } else {
                $realPath = storage_path('app/private/' . $certPath);
            }

            if (!file_exists($realPath)) {
                Log::warning('No se pudo firmar el Facturae: el archivo de certificado no existe en ' . $realPath);
                return $xmlContent;
            }

        try {
            $certs = $this->certService->loadP12($realPath, $certPass);
            $privateKey = $certs['pkey'];
            $publicCert = $certs['cert'];

            // Cargar XML en DOM
            $doc = new DOMDocument();
            $doc->loadXML($xmlContent);

            // 1. Inicializar objeto DSig
            $objDSig = new XMLSecurityDSig();
            $objDSig->setCanonicalMethod(XMLSecurityDSig::C14N);
            
            $signatureId = 'Signature-' . uniqid();
            
            $objDSig->addReference(
                $doc, 
                XMLSecurityDSig::SHA256, 
                ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
                ['force_uri' => true, 'id_name' => 'SignatureID']
            );

            // 2. Crear clave RSA para la firma (SHA-256)
            $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
            $objKey->loadKey($privateKey);

            // 3. BLOQUE XAdES-BES: QualifyingProperties (Lo creamos pero no lo insertamos aún)
            $xadesObject = $this->addXadesProperties($doc, $objDSig, $publicCert, $signatureId);
            
            // Firmar
            $objDSig->sign($objKey);

            // 4. Añadir Certificado Público a la firma
            $objDSig->add509Cert($publicCert);

            // 5. Insertar firma en el documento
            $objDSig->insertSignature($doc->documentElement);
            
            // 6. ASIGNAR ID A LA FIRMA Y REUBICAR EL OBJETO XAdES
            // Buscamos la firma dentro del documento principal (no usamos sigNode directamente si es de otro doc)
            $sigNodes = $doc->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
            if ($sigNodes->length > 0) {
                $actualSigNode = $sigNodes->item(0);
                $actualSigNode->setAttribute('Id', $signatureId);
                
                if (isset($xadesObject)) {
                    $importedObject = $doc->importNode($xadesObject, true);
                    $actualSigNode->appendChild($importedObject);
                }
            }

            return $doc->saveXML();

        } catch (\Exception $e) {
            Log::error("Error firmando XML Facturae: " . $e->getMessage());
            return $xmlContent;
        }
    }

    protected function addXadesProperties(DOMDocument $doc, XMLSecurityDSig $objDSig, string $publicCert, string $signatureId): \DOMElement
    {
        $signingTime = gmdate("Y-m-d\TH:i:s\Z");
        
        // Extraer hash del certificado (SHA-256 es preferible hoy en día)
        $certData = openssl_x509_parse($publicCert);
        $certBase64 = base64_encode(sha1($this->getCertContent($publicCert), true)); // v1 usaba sha1, v2 usa sha256
        $certHash256 = base64_encode(hash('sha256', $this->getCertContent($publicCert), true));

        // Crear contenedor ds:Object
        $object = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Object');
        
        $qualProperties = $doc->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:QualifyingProperties');
        $qualProperties->setAttribute('Target', '#' . $signatureId);
        
        $signedProps = $doc->createElement('xades:SignedProperties');
        $signedPropsId = 'SignedProperties-' . $signatureId;
        $signedProps->setAttribute('Id', $signedPropsId);
        
        $signedSigProps = $doc->createElement('xades:SignedSignatureProperties');
        
        // 1. SigningTime
        $signedSigProps->appendChild($doc->createElement('xades:SigningTime', $signingTime));
        
        // 2. SigningCertificateV2 (Obligatorio para SHA-256)
        $signingCert = $doc->createElement('xades:SigningCertificateV2');
        $cert = $doc->createElement('xades:Cert');
        $certDigest = $doc->createElement('xades:CertDigest');
        $digestMethod = $doc->createElement('ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $certDigest->appendChild($digestMethod);
        $certDigest->appendChild($doc->createElement('ds:DigestValue', $certHash256));
        
        $cert->appendChild($certDigest);

        $issuerSerial = $doc->createElement('xades:IssuerSerial');
        $issuerSerial->appendChild($doc->createElement('ds:X509IssuerName', $this->getIssuerName($certData)));
        $issuerSerial->appendChild($doc->createElement('ds:X509SerialNumber', $certData['serialNumber']));
        $cert->appendChild($issuerSerial);

        $signingCert->appendChild($cert);
        $signedSigProps->appendChild($signingCert);
        
        // 3. SignaturePolicyIdentifier (Política de firma Facturae 3.1)
        $policy = $doc->createElement('xades:SignaturePolicyIdentifier');
        $id = $doc->createElement('xades:SignaturePolicyId');
        $id->appendChild($doc->createElement('xades:SigPolicyId'))->appendChild($doc->createElement('xades:Identifier', 'http://www.facturae.gob.es/politica_de_firma_formato_facturae/politica_de_firma_formato_facturae_v3_1.pdf'));
        $id->appendChild($doc->createElement('xades:SigPolicyHash'))->appendChild($doc->createElement('ds:DigestMethod'))->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $id->getElementsByTagName('xades:SigPolicyHash')->item(0)->appendChild($doc->createElement('ds:DigestValue', '3S6fAtZsh9mSIdPZrntG87o8AlUCD9ApmMeUwbv7p6c=')); // Hash SHA-256 de la política v3.1
        $policy->appendChild($id);
        $signedSigProps->appendChild($policy);
        
        $signedProps->appendChild($signedSigProps);
        $qualProperties->appendChild($signedProps);
        $object->appendChild($qualProperties);
        
        // Referenciar SignedProperties para que sean firmadas también
        $objDSig->addReference(
            $signedProps,
            XMLSecurityDSig::SHA256,
            null,
            ['type' => 'http://uri.etsi.org/01903#SignedProperties', 'force_uri' => true]
        );

        return $object;
    }

    private function getCertContent(string $cert): string
    {
        $cert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"], '', $cert);
        return base64_decode($cert);
    }

    private function getIssuerName(array $certData): string
    {
        $parts = [];
        foreach ($certData['issuer'] as $key => $value) {
            if (is_array($value)) $value = implode(', ', $value);
            $parts[] = "$key=$value";
        }
        return implode(', ', array_reverse($parts));
    }
}
