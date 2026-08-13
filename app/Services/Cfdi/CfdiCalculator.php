<?php
namespace App\Services\Cfdi;

use InvalidArgumentException;

class CfdiCalculator {


    private const CALCULATION_SCALE = 6;
    private const MONEY_SCALE = 2;

    function calculate(array $concepts) : array {

        if(empty($concepts)){
            throw new InvalidArgumentException('El CFDI debe contener al menos un concepto');
        }

        $subtotal = '0.000000';
        $totalTaxTraslated = '0.000000';

        $calculatedConcepts = [];

        foreach ($concepts as $index => $concept) {
            $this->validateConcept($concept, $index);

            $quantity = (string) $concept['cantidad'];
            $unitaryVale = (string) $concept['valorUnitario'];
            $iva = (string) $concept['iva'];

            $amount = bcmul($quantity, $unitaryVale, self::CALCULATION_SCALE);

            $base = $amount;

            $amountWithIva = bcmul($base, $iva, self::CALCULATION_SCALE);

             $subtotal = bcadd(
                $subtotal,
                $amount,
                self::CALCULATION_SCALE
            );

            $totalTaxTraslated = bcadd($totalTaxTraslated, $amountWithIva, self::CALCULATION_SCALE);

            $calculatedConcepts[] = array_merge($concept, [
                'importe' => $amount,
                'base' => $base,
                'importeIva' => $amountWithIva
            ]);
        }
        $total = bcadd($subtotal, $totalTaxTraslated, self::CALCULATION_SCALE);

        return [
            'conceptos' => $calculatedConcepts,
            'subtotal' => $this->roundMoney($subtotal),
            'totalImpuestosTrasladados' => $this->roundMoney($totalTaxTraslated),
            'total' => $this->roundMoney($total),
            'impuestos' => [
                [
                    'base' => $this->roundMoney($subtotal),
                    'impuesto' => '002',
                    'tipoFactor' => 'Tasa',
                    'tasaOCuota' => '0.160000',
                    'importe' => $this->roundMoney( $totalTaxTraslated),
                ],
            ]
        ];
    }

    private function validateConcept(array $concept, int $index) {
        $required = [
            'cantidad',
            'valorUnitario',
            'iva',
            'objetoImp'
        ];

        foreach ($required as $field) {
            if(!isset($concept[$field])){
                throw new InvalidArgumentException(sprintf('El concepto %d no contiene el campo %s.', $index + 1, $field));
            }
        }

        foreach (['cantidad', 'valorUnitario', 'iva'] as $field) {
            if(!is_numeric($concept[$field])){
                throw new InvalidArgumentException(sprintf('El campo "%s" del concepto %d debe ser númerico.', $index + 1, $field));
            }
        }
    }

    private function roundMoney(string $value) : string {
        $value = bcadd($value, '0.005', self::MONEY_SCALE + 1);

        return bcdiv($value, '1', self::MONEY_SCALE);
    }

}
