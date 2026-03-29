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

        // Usar DOMDocument para un control total sobre namespaces y canonización
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $nsFacturae = 'http://www.facturae.gob.es/formato/Versiones/Facturaev3_2_2.xml';
        // El elemento raíz DEBE tener el prefijo facturae para evitar problemas con la firma
        $root = $doc->createElementNS($nsFacturae, 'facturae:Facturae');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', 'http://www.w3.org/2000/09/xmldsig#');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:facturae', $nsFacturae);
        $doc->appendChild($root);

        $this->addFileHeader($doc, $root, $documento);
        $this->addParties($doc, $root, $documento);
        $this->addInvoices($doc, $root, $documento);

        $unsignedXml = $doc->saveXML();
        
        // Firma digital si hay certificado configurado
        $certPath = Setting::get('facturae_cert_path') ?: Setting::get('verifactu_cert_path');
        if ($certPath) {
            return $this->signXml($unsignedXml);
        }

        return $unsignedXml;
    }

    /**
     * Helper para añadir nodos con el namespace de Facturae sin prefijo (unqualified elements).
     */
    protected function addNode(DOMDocument $doc, \DOMNode $parent, string $name, ?string $value = null): \DOMElement
    {
        // Al crear elementos sin namespace explícito y sin prefijo, 
        // DOMDocument los deja como "unqualified" respecto al namespace de la raíz (Facturae).
        $node = $doc->createElement($name);
        if ($value !== null) {
            $node->nodeValue = htmlspecialchars($value);
        }
        $parent->appendChild($node);
        return $node;
    }

    /**
     * Añadir cabecera del fichero.
     */
    protected function addFileHeader(DOMDocument $doc, \DOMNode $root, Documento $documento): void
    {
        $header = $this->addNode($doc, $root, 'FileHeader');
        $this->addNode($doc, $header, 'SchemaVersion', self::SCHEMA_VERSION);
        $this->addNode($doc, $header, 'Modality', 'I');
        $this->addNode($doc, $header, 'InvoiceIssuerType', 'EM');
        
        $batch = $this->addNode($doc, $header, 'Batch');
        $nifEmisor = Setting::get('verifactu_nif_emisor') ?: '00000000X';
        $this->addNode($doc, $batch, 'BatchIdentifier', $nifEmisor . $documento->numero);
        $this->addNode($doc, $batch, 'InvoicesCount', '1');
        
        $totalAmt = $this->addNode($doc, $batch, 'TotalInvoicesAmount');
        $this->addNode($doc, $totalAmt, 'TotalAmount', number_format($documento->total, 2, '.', ''));
        
        $outstandingAmt = $this->addNode($doc, $batch, 'TotalOutstandingAmount');
        $this->addNode($doc, $outstandingAmt, 'TotalAmount', number_format($documento->total, 2, '.', ''));
        
        $executableAmt = $this->addNode($doc, $batch, 'TotalExecutableAmount');
        $this->addNode($doc, $executableAmt, 'TotalAmount', number_format($documento->total, 2, '.', ''));
        
        $this->addNode($doc, $batch, 'InvoiceCurrencyCode', 'EUR');
    }

    /**
     * Añadir datos del emisor y receptor.
     */
    protected function addParties(DOMDocument $doc, \DOMNode $root, Documento $documento): void
    {
        $parties = $this->addNode($doc, $root, 'Parties');
        
        // 1. Seller (Nosotros)
        $seller = $this->addNode($doc, $parties, 'SellerParty');
        $this->addTaxIdentification($doc, $seller, Setting::get('verifactu_nif_emisor'), 'J');
        $this->addPartyData($doc, $seller, Setting::get('verifactu_nombre_emisor'), [
            'address' => Setting::get('verifactu_direccion_emisor', 'Calle Ejemplo, 1'),
            'post_code' => Setting::get('verifactu_cp_emisor', '28001'),
            'town' => Setting::get('verifactu_poblacion_emisor', 'Madrid'),
            'province' => Setting::get('verifactu_provincia_emisor', 'Madrid'),
            'country' => 'ESP'
        ]);

        // 2. Buyer (El cliente)
        $buyer = $this->addNode($doc, $parties, 'BuyerParty');
        $tercero = $documento->tercero;
        
        $this->addTaxIdentification($doc, $buyer, $tercero->nif_cif, $tercero->es_persona_fisica ? 'F' : 'J');

        // Si tiene DIR3, añadirlo (Obligatorio para FACe)
        // DEBE ir antes de LegalEntity/Individual en el esquema de Facturae
        if ($tercero->dir3_oficina_contable || $tercero->dir3_organo_gestor || $tercero->dir3_unidad_tramitadora) {
            $adminCenters = $this->addNode($doc, $buyer, 'AdministrativeCentres');
            
            $addressData = [
                'address' => $tercero->direccion_fiscal,
                'post_code' => $tercero->codigo_postal_fiscal,
                'town' => $tercero->poblacion_fiscal,
                'province' => $tercero->provincia_fiscal,
            ];
            
            if ($tercero->dir3_oficina_contable) {
                $this->addAdminCenter($doc, $adminCenters, '01', $tercero->dir3_oficina_contable, 'Oficina Contable', $addressData);
            }
            if ($tercero->dir3_organo_gestor) {
                $this->addAdminCenter($doc, $adminCenters, '02', $tercero->dir3_organo_gestor, 'Órgano Gestor', $addressData);
            }
            if ($tercero->dir3_unidad_tramitadora) {
                $this->addAdminCenter($doc, $adminCenters, '03', $tercero->dir3_unidad_tramitadora, 'Unidad Tramitadora', $addressData);
            }
        }

        $this->addPartyData($doc, $buyer, $tercero->razon_social ?: $tercero->nombre_comercial, [
            'address' => $tercero->direccion_fiscal,
            'post_code' => $tercero->codigo_postal_fiscal,
            'town' => $tercero->poblacion_fiscal,
            'province' => $tercero->provincia_fiscal,
            'country' => 'ESP'
        ]);
    }

    /**
     * Añadir el bloque de Invoices.
     */
    protected function addInvoices(DOMDocument $doc, \DOMNode $root, Documento $documento): void
    {
        $invoices = $this->addNode($doc, $root, 'Invoices');
        $invoice = $this->addNode($doc, $invoices, 'Invoice');
        
        $header = $this->addNode($doc, $invoice, 'InvoiceHeader');
        $this->addNode($doc, $header, 'InvoiceNumber', $documento->numero);
        $this->addNode($doc, $header, 'InvoiceSeriesCode', $documento->serie ?: 'A');
        $this->addNode($doc, $header, 'InvoiceDocumentType', 'FC');
        $this->addNode($doc, $header, 'InvoiceClass', 'OO');
        
        $issueData = $this->addNode($doc, $invoice, 'InvoiceIssueData');
        $this->addNode($doc, $issueData, 'IssueDate', $documento->fecha->format('Y-m-d'));
        $this->addNode($doc, $issueData, 'InvoiceCurrencyCode', 'EUR');
        $this->addNode($doc, $issueData, 'TaxCurrencyCode', 'EUR');
        $this->addNode($doc, $issueData, 'LanguageName', 'es');
        
        // Impuestos
        $taxesOutputs = $this->addNode($doc, $invoice, 'TaxesOutputs');
        $desgloseResumen = $documento->getDesgloseImpuestos();
        
        foreach ($desgloseResumen as $lineaIva) {
            $tax = $this->addNode($doc, $taxesOutputs, 'Tax');
            $this->addNode($doc, $tax, 'TaxTypeCode', '01');
            $this->addNode($doc, $tax, 'TaxRate', number_format($lineaIva['iva'], 2, '.', ''));
            $base = $this->addNode($doc, $tax, 'TaxableBase');
            $this->addNode($doc, $base, 'TotalAmount', number_format($lineaIva['base'], 2, '.', ''));
            $amt = $this->addNode($doc, $tax, 'TaxAmount');
            $this->addNode($doc, $amt, 'TotalAmount', number_format($lineaIva['cuota_iva'], 2, '.', ''));
        }

        // Totales
        $totals = $this->addNode($doc, $invoice, 'InvoiceTotals');
        $this->addNode($doc, $totals, 'TotalGrossAmount', number_format($documento->subtotal, 2, '.', ''));
        $this->addNode($doc, $totals, 'TotalGrossAmountBeforeTaxes', number_format($documento->subtotal, 2, '.', ''));
        $this->addNode($doc, $totals, 'TotalTaxOutputs', number_format($documento->iva, 2, '.', ''));
        $this->addNode($doc, $totals, 'TotalTaxesWithheld', number_format($documento->irpf ?: 0, 2, '.', ''));
        $this->addNode($doc, $totals, 'InvoiceTotal', number_format($documento->total, 2, '.', ''));
        $this->addNode($doc, $totals, 'TotalOutstandingAmount', number_format($documento->total, 2, '.', ''));
        $this->addNode($doc, $totals, 'TotalExecutableAmount', number_format($documento->total, 2, '.', ''));
        
        // Líneas (items)
        $items = $this->addNode($doc, $invoice, 'Items');
        foreach ($documento->lineas as $linea) {
            $item = $this->addNode($doc, $items, 'InvoiceLine');
            $this->addNode($doc, $item, 'ItemDescription', substr($linea->descripcion, 0, 2500));
            $this->addNode($doc, $item, 'Quantity', number_format($linea->cantidad, 2, '.', ''));
            $this->addNode($doc, $item, 'UnitOfMeasure', '01');
            $this->addNode($doc, $item, 'UnitPriceWithoutTax', number_format($linea->precio_unitario, 6, '.', ''));
            $this->addNode($doc, $item, 'TotalCost', number_format($linea->subtotal, 2, '.', ''));
            $this->addNode($doc, $item, 'GrossAmount', number_format($linea->subtotal, 2, '.', ''));
            
            $itemTaxes = $this->addNode($doc, $item, 'TaxesOutputs');
            $tax = $this->addNode($doc, $itemTaxes, 'Tax');
            $this->addNode($doc, $tax, 'TaxTypeCode', '01');
            $this->addNode($doc, $tax, 'TaxRate', number_format($linea->iva, 2, '.', ''));
            $base = $this->addNode($doc, $tax, 'TaxableBase');
            $this->addNode($doc, $base, 'TotalAmount', number_format($linea->subtotal, 2, '.', ''));
            $amt = $this->addNode($doc, $tax, 'TaxAmount');
            $this->addNode($doc, $amt, 'TotalAmount', number_format($linea->importe_iva, 2, '.', ''));
        }

        // Formas de pago
        if ($documento->formaPago && $documento->formaPago->tipo === 'transferencia' && $documento->tercero->iban) {
            $paymentDetails = $this->addNode($doc, $invoice, 'PaymentDetails');
            $installment = $this->addNode($doc, $paymentDetails, 'Installment');
            $this->addNode($doc, $installment, 'InstallmentDueDate', $documento->fecha_vencimiento ? $documento->fecha_vencimiento->format('Y-m-d') : $documento->fecha->format('Y-m-d'));
            $this->addNode($doc, $installment, 'InstallmentAmount', number_format($documento->total, 2, '.', ''));
            $this->addNode($doc, $installment, 'PaymentMeans', '04');
            
            $account = $this->addNode($doc, $installment, 'AccountToBeCredited');
            $this->addNode($doc, $account, 'IBAN', str_replace(' ', '', $documento->tercero->iban));
        }
    }

    protected function addTaxIdentification(DOMDocument $doc, \DOMNode $parent, ?string $nif, string $residenceType): void
    {
        $id = $this->addNode($doc, $parent, 'TaxIdentification');
        $this->addNode($doc, $id, 'PersonTypeCode', $residenceType);
        $this->addNode($doc, $id, 'ResidenceTypeCode', 'R');
        $this->addNode($doc, $id, 'TaxIdentificationNumber', $nif);
    }

    protected function addPartyData(DOMDocument $doc, \DOMNode $parent, ?string $name, array $addressData = []): void
    {
        $entity = $this->addNode($doc, $parent, 'LegalEntity');
        $this->addNode($doc, $entity, 'CorporateName', $name);
        
        if (!empty($addressData)) {
            $address = $this->addNode($doc, $entity, 'AddressInSpain');
            $this->addNode($doc, $address, 'Address', substr($addressData['address'] ?? 'Calle Desconocida', 0, 80));
            $this->addNode($doc, $address, 'PostCode', substr($addressData['post_code'] ?? '00000', 0, 5));
            $this->addNode($doc, $address, 'Town', substr($addressData['town'] ?? 'Ciudad', 0, 50));
            $this->addNode($doc, $address, 'Province', substr($addressData['province'] ?? 'Provincia', 0, 20));
            $this->addNode($doc, $address, 'CountryCode', 'ESP');
        }
    }

    protected function addAdminCenter(DOMDocument $doc, \DOMNode $parent, string $role, string $code, string $description, array $addressData = []): void
    {
        $center = $this->addNode($doc, $parent, 'AdministrativeCentre');
        $this->addNode($doc, $center, 'CentreCode', $code);
        $this->addNode($doc, $center, 'RoleTypeCode', $role);
        $this->addNode($doc, $center, 'Name', substr($description, 0, 40));
        
        if (!empty($addressData)) {
            $address = $this->addNode($doc, $center, 'AddressInSpain');
            $this->addNode($doc, $address, 'Address', substr($addressData['address'] ?? 'Calle Desconocida', 0, 80));
            $this->addNode($doc, $address, 'PostCode', substr($addressData['post_code'] ?? '00000', 0, 5));
            $this->addNode($doc, $address, 'Town', substr($addressData['town'] ?? 'Ciudad', 0, 50));
            $this->addNode($doc, $address, 'Province', substr($addressData['province'] ?? 'Provincia', 0, 20));
            $this->addNode($doc, $address, 'CountryCode', 'ESP');
        }
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
            $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
            
            $signatureId = 'Signature-' . uniqid();
            $signedPropsId = 'SignedProperties-' . $signatureId;
            
            // Referencia al propio documento (Enveloped) con URI vacío explícito
            $objDSig->addReference(
                $doc, 
                XMLSecurityDSig::SHA256, 
                ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
                ['force_uri' => true, 'uri' => '']
            );

            // 2. BLOQUE XAdES-BES: Crear y añadir al DOM para que sea "visible"
            $xadesObject = $this->addXadesProperties($doc, $publicCert, $signatureId, $signedPropsId);
            $doc->documentElement->appendChild($xadesObject);

            // Registrar IDs manualmente para el motor DOM (Crucial para getElementById)
            $signedPropsNode = $doc->getElementsByTagNameNS('http://uri.etsi.org/01903/v1.3.2#', 'SignedProperties')->item(0);
            if ($signedPropsNode && $signedPropsNode instanceof \DOMElement) {
                $signedPropsNode->setIdAttribute('Id', true);
            }

            // 3. Añadir Referencia a SignedProperties (XAdES) usando C14N
            $objDSig->addReference(
                $signedPropsNode,
                XMLSecurityDSig::SHA256,
                ['http://www.w3.org/2001/10/xml-exc-c14n#'],
                [
                    'type' => 'http://uri.etsi.org/01903#SignedProperties', 
                    'force_uri' => true,
                ]
            );

            // 4. Crear clave RSA para la firma (SHA-256)
            $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
            $objKey->loadKey($privateKey);

            // 5. FIRMAR (Ahora el DigestValue de XAdES será correcto)
            $objDSig->sign($objKey);

            // 6. Insertar firma en el documento
            $objDSig->insertSignature($doc->documentElement);
            
            // Reubicar el objeto XAdES dentro de la firma y añadir Id
            $sigNodes = $doc->getElementsByTagNameNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
            $sigNode = $sigNodes->item(0);
            if ($sigNode && $sigNode instanceof \DOMElement) {
                $sigNode->setAttribute('Id', $signatureId);
                $sigNode->setIdAttribute('Id', true);
                // Importar para evitar Wrong Document Error aunque sea el mismo documento
                $sigNode->appendChild($doc->importNode($xadesObject, true));
                
                // Limpiar el objeto huérfano de la raíz
                if ($xadesObject->parentNode === $doc->documentElement) {
                    $doc->documentElement->removeChild($xadesObject);
                }
            }

            // 7. Añadir Certificado Público a la firma
            $objDSig->add509Cert($publicCert);

            return $doc->saveXML();

        } catch (\Exception $e) {
            Log::error("Error firmando XML Facturae: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $xmlContent;
        }
    }

    /**
     * Crear el bloque de propiedades XAdES-BES.
     */
    protected function addXadesProperties(DOMDocument $doc, string $publicCert, string $signatureId, string $signedPropsId): \DOMNode
    {
        $nsXades = 'http://uri.etsi.org/01903/v1.3.2#';
        $nsDS = 'http://www.w3.org/2000/09/xmldsig#';
        
        $signingTime = gmdate("Y-m-d\TH:i:s\Z");
        
        $certData = openssl_x509_parse($publicCert);
        $certHash256 = base64_encode(hash('sha256', $this->getCertContent($publicCert), true));

        // Crear contenedor ds:Object
        $object = $doc->createElementNS($nsDS, 'ds:Object');
        
        $qualProperties = $doc->createElementNS($nsXades, 'xades:QualifyingProperties');
        $qualProperties->setAttribute('Target', '#' . $signatureId);
        
        $signedProps = $doc->createElementNS($nsXades, 'xades:SignedProperties');
        $signedProps->setAttribute('Id', $signedPropsId);
        
        $signedSigProps = $doc->createElementNS($nsXades, 'xades:SignedSignatureProperties');
        
        // 1. SigningTime
        $signedSigProps->appendChild($doc->createElementNS($nsXades, 'xades:SigningTime', $signingTime));
        
        // 2. SigningCertificate (XAdES Standard)
        $signingCert = $doc->createElementNS($nsXades, 'xades:SigningCertificate');
        $cert = $doc->createElementNS($nsXades, 'xades:Cert');
        $certDigest = $doc->createElementNS($nsXades, 'xades:CertDigest');
        
        $digestMethod = $doc->createElementNS($nsDS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $certDigest->appendChild($digestMethod);
        $certDigest->appendChild($doc->createElementNS($nsDS, 'ds:DigestValue', $certHash256));
        $cert->appendChild($certDigest);

        $issuerSerial = $doc->createElementNS($nsXades, 'xades:IssuerSerial');
        $issuerSerial->appendChild($doc->createElementNS($nsDS, 'ds:X509IssuerName', $this->getIssuerName($certData)));
        $issuerSerial->appendChild($doc->createElementNS($nsDS, 'ds:X509SerialNumber', (string)$certData['serialNumber']));
        $cert->appendChild($issuerSerial);

        $signingCert->appendChild($cert);
        $signedSigProps->appendChild($signingCert);
        
        // 3. SignaturePolicyIdentifier (Facturae 3.1 Policy)
        $policyIdentifier = $doc->createElementNS($nsXades, 'xades:SignaturePolicyIdentifier');
        $signaturePolicyId = $doc->createElementNS($nsXades, 'xades:SignaturePolicyId');
        
        $sigPolicyId = $doc->createElementNS($nsXades, 'xades:SigPolicyId');
        $sigPolicyId->appendChild($doc->createElementNS($nsXades, 'xades:Identifier', 'http://www.facturae.es/politica_de_firma_formato_facturae/politica_de_firma_formato_facturae_v3_1.pdf'));
        $sigPolicyId->appendChild($doc->createElementNS($nsXades, 'xades:Description', 'Política de Firma de Facturae v3.1'));
        $signaturePolicyId->appendChild($sigPolicyId);
        
        $sigPolicyHash = $doc->createElementNS($nsXades, 'xades:SigPolicyHash');
        $digestMethodPolicy = $doc->createElementNS($nsDS, 'ds:DigestMethod');
        $digestMethodPolicy->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $sigPolicyHash->appendChild($digestMethodPolicy);
        // SHA-1 Hash para la política 3.1 de Facturae: Ohixl6upD6av8N7pEvDABhEL6hM=
        $sigPolicyHash->appendChild($doc->createElementNS($nsDS, 'ds:DigestValue', 'Ohixl6upD6av8N7pEvDABhEL6hM='));
        $signaturePolicyId->appendChild($sigPolicyHash);
        
        $policyIdentifier->appendChild($signaturePolicyId);
        $signedSigProps->appendChild($policyIdentifier);
        
        $signedProps->appendChild($signedSigProps);
        $qualProperties->appendChild($signedProps);
        $object->appendChild($qualProperties);
        
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
        return implode(',', array_reverse($parts)); // Sin espacios para máxima compatibilidad LDAP
    }
}
