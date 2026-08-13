<?php

namespace App\Services\Cfdi;

use DOMDocument;
use DOMElement;

class CfdiXmlGenerator
{
    private const CFDI_NAMESPACE = 'http://www.sat.gob.mx/cfd/4';
    private const XSI_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

    private DOMDocument $document;

    public function generate(array $data, array $calculated): DOMDocument{
        $this->document = new DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;

        $comprobante = $this->createComprobante($data, $calculated);

        $this->document->appendChild($comprobante);

        $this->addEmisor(
            $comprobante,
            $data['emisor']
        );

        $this->addReceptor(
            $comprobante,
            $data['receptor']
        );

        $this->addConceptos(
            $comprobante,
            $calculated['conceptos']
        );

        $this->addImpuestos(
            $comprobante,
            $calculated
        );

        return $this->document;
    }

    private function createComprobante(array $data, array $calculated): DOMElement {
        $comprobanteData = $data['comprobante'];

        $comprobante = $this->document->createElementNS(
            self::CFDI_NAMESPACE,
            'cfdi:Comprobante'
        );

        /*
         * Namespace para xsi:schemaLocation.
         */
        $comprobante->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            self::XSI_NAMESPACE
        );

        $comprobante->setAttributeNS(
            self::XSI_NAMESPACE,
            'xsi:schemaLocation',
            self::CFDI_NAMESPACE
                . ' '
                . 'http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd'
        );

        /*
         * Datos provenientes del JSON.
         */
        $comprobante->setAttribute(
            'Version',
            $comprobanteData['version']
        );

        $comprobante->setAttribute(
            'Serie',
            $comprobanteData['serie']
        );

        $comprobante->setAttribute(
            'Folio',
            $comprobanteData['folio']
        );

        /*
         * El JSON no proporciona Fecha.
         *
         * Para la prueba la generamos al momento de crear
         * el CFDI.
         */
        $comprobante->setAttribute(
            'Fecha',
            now()->format('Y-m-d\TH:i:s')
        );

        /*
         * Placeholders.
         *
         * No representan un certificado ni sello real.
         * Únicamente permiten construir la estructura
         * esperada por CFDI 4.0.
         */
        $comprobante->setAttribute(
            'Sello',
            'AA=='
        );

        $comprobante->setAttribute(
            'NoCertificado',
            '00001000000500000000'
        );

        $comprobante->setAttribute(
            'Certificado',
            'AA=='
        );

        $comprobante->setAttribute(
            'FormaPago',
            $comprobanteData['formaPago']
        );

        $comprobante->setAttribute(
            'SubTotal',
            $calculated['subtotal']
        );

        $comprobante->setAttribute(
            'Moneda',
            $comprobanteData['moneda']
        );

        $comprobante->setAttribute(
            'TipoCambio',
            $comprobanteData['tipoCambio']
        );

        $comprobante->setAttribute(
            'Total',
            $calculated['total']
        );

        $comprobante->setAttribute(
            'TipoDeComprobante',
            $comprobanteData['tipoDeComprobante']
        );

        $comprobante->setAttribute(
            'Exportacion',
            $comprobanteData['exportacion']
        );

        $comprobante->setAttribute(
            'MetodoPago',
            $comprobanteData['metodoPago']
        );

        $comprobante->setAttribute(
            'LugarExpedicion',
            $comprobanteData['lugarExpedicion']
        );

        return $comprobante;
    }

    private function addEmisor(DOMElement $comprobante, array $data) {
        $emisor = $this->createCfdiElement('Emisor');

        $emisor->setAttribute(
            'Rfc',
            $data['rfc']
        );

        $emisor->setAttribute(
            'Nombre',
            $data['nombre']
        );

        $emisor->setAttribute(
            'RegimenFiscal',
            $data['regimenFiscal']
        );

        $comprobante->appendChild($emisor);
    }

    private function addReceptor(DOMElement $comprobante, array $data) {
        $receptor = $this->createCfdiElement('Receptor');

        $receptor->setAttribute(
            'Rfc',
            $data['rfc']
        );

        $receptor->setAttribute(
            'Nombre',
            $data['nombre']
        );

        $receptor->setAttribute(
            'DomicilioFiscalReceptor',
            $data['domicilioFiscalReceptor']
        );

        $receptor->setAttribute(
            'RegimenFiscalReceptor',
            $data['regimenFiscalReceptor']
        );

        $receptor->setAttribute(
            'UsoCFDI',
            $data['usoCFDI']
        );

        $comprobante->appendChild($receptor);
    }

    private function addConceptos(DOMElement $comprobante, array $conceptos) {
        $conceptosNode = $this->createCfdiElement(
            'Conceptos'
        );

        foreach ($conceptos as $data) {
            $concepto = $this->createCfdiElement(
                'Concepto'
            );

            $concepto->setAttribute(
                'ClaveProdServ',
                $data['claveProdServ']
            );

            $concepto->setAttribute(
                'Cantidad',
                $data['cantidad']
            );

            $concepto->setAttribute(
                'ClaveUnidad',
                $data['claveUnidad']
            );

            $concepto->setAttribute(
                'Unidad',
                $data['unidad']
            );

            $concepto->setAttribute(
                'Descripcion',
                $data['descripcion']
            );

            $concepto->setAttribute(
                'ValorUnitario',
                $this->money($data['valorUnitario'])
            );

            $concepto->setAttribute(
                'Importe',
                $this->money($data['importe'])
            );

            $concepto->setAttribute(
                'ObjetoImp',
                $data['objetoImp']
            );

            $this->addConceptoImpuestos(
                $concepto,
                $data
            );

            $conceptosNode->appendChild($concepto);
        }

        $comprobante->appendChild($conceptosNode);
    }

    private function addConceptoImpuestos(DOMElement $concepto, array $data) {
        $impuestos = $this->createCfdiElement(
            'Impuestos'
        );

        $traslados = $this->createCfdiElement(
            'Traslados'
        );

        $traslado = $this->createCfdiElement(
            'Traslado'
        );

        $traslado->setAttribute(
            'Base',
            $this->money($data['base'])
        );

        /*
         * 002 = IVA
         */
        $traslado->setAttribute(
            'Impuesto',
            '002'
        );

        $traslado->setAttribute(
            'TipoFactor',
            'Tasa'
        );

        $traslado->setAttribute(
            'TasaOCuota',
            $data['iva']
        );

        $traslado->setAttribute(
            'Importe',
            $this->money($data['importeIva'])
        );

        $traslados->appendChild($traslado);
        $impuestos->appendChild($traslados);
        $concepto->appendChild($impuestos);
    }

   private function addImpuestos(DOMElement $comprobante, array $calculated){
    $impuestos = $this->createCfdiElement(
        'Impuestos'
    );

    $impuestos->setAttribute(
        'TotalImpuestosTrasladados',
        $calculated['totalImpuestosTrasladados']
    );

    $traslados = $this->createCfdiElement(
        'Traslados'
    );

    foreach ($calculated['impuestos'] as $data) {
        $traslado = $this->createCfdiElement(
            'Traslado'
        );

        $traslado->setAttribute(
            'Base',
            $data['base']
        );

        $traslado->setAttribute(
            'Impuesto',
            $data['impuesto']
        );

        $traslado->setAttribute(
            'TipoFactor',
            $data['tipoFactor']
        );

        $traslado->setAttribute(
            'TasaOCuota',
            $data['tasaOCuota']
        );

        $traslado->setAttribute(
            'Importe',
            $data['importe']
        );

        $traslados->appendChild($traslado);
    }

    $impuestos->appendChild($traslados);
    $comprobante->appendChild($impuestos);
}

    private function createCfdiElement(string $name): DOMElement {
        return $this->document->createElementNS(
            self::CFDI_NAMESPACE,
            "cfdi:{$name}"
        );
    }

    private function money(string $value): string{
         return bcdiv(
            bcadd($value, '0.005', 3),
            '1',
            2
        );
    }
}
