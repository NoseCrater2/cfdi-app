<?php

namespace Tests\Unit\Services\Cfdi;

use App\Services\Cfdi\CfdiCalculator;
use PHPUnit\Framework\TestCase;

class CfdiCalculatorTest extends TestCase
{
    public function test_it_calculates_cfdi_totals()
    {
        $conceptos = [
            [
                'cantidad' => '1',
                'valorUnitario' => '2296.26',
                'objetoImp' => '02',
                'iva' => '0.160000',
            ],
            [
                'cantidad' => '1',
                'valorUnitario' => '5567.40',
                'objetoImp' => '02',
                'iva' => '0.160000',
            ],
            [
                'cantidad' => '1',
                'valorUnitario' => '162.80',
                'objetoImp' => '02',
                'iva' => '0.160000',
            ],
        ];

        $calculator = new CfdiCalculator();

        $result = $calculator->calculate($conceptos);

        $this->assertSame(
            '8026.46',
            $result['subtotal']
        );

        $this->assertSame(
            '1284.23',
            $result['totalImpuestosTrasladados']
        );

        $this->assertSame(
            '9310.69',
            $result['total']
        );

        $this->assertSame(
            '367.401600',
            $result['conceptos'][0]['importeIva']
        );

        $this->assertCount(
            3,
            $result['conceptos']
        );
    }
}
