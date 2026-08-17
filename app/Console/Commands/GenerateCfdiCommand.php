<?php

namespace App\Console\Commands;

use App\Services\Cfdi\CfdiCalculator;
use App\Services\Cfdi\CfdiXmlGenerator;
use App\Services\Cfdi\CfdiXmlValidator;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

class GenerateCfdiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cfdi:generate {input=storage/app/cfdi/input.json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates and validate a CFDI 4.0 from a JSON file';

    /**
     * Execute the console command.
     */
    public function handle(CfdiCalculator $calculator, CfdiXmlGenerator $generator, CfdiXmlValidator $validator): int
    {
        try {
            $inputPath = base_path($this->argument('input'));

            if(!file_exists($inputPath)){
                $this->error("No existe el archivo: {$inputPath}");
                return self::FAILURE;
            }

            $this->info('Leyendo JSON...');

            $data = json_decode(file_get_contents($inputPath), true, 512, JSON_THROW_ON_ERROR);

            $this->info('Calculando CFDI...');

            $calculated = $calculator->calculate($data['conceptos']);

            $this->table(
                    ['Campo', 'Valor'],
                    [
                        ['Subtotal', $calculated['subtotal']],
                        ['IVA', $calculated['totalImpuestosTrasladados']],
                        ['Total', $calculated['total']]
                    ]
                );

            $this->info('Generando XML...');

            $document = $generator->generate($data, $calculated);

            $outputDirectory = storage_path('app/cfdi');

            if(!is_dir($outputDirectory)){
                mkdir($outputDirectory, 0755, true);
            }

            $outputPath = $outputDirectory . DIRECTORY_SEPARATOR . 'output.xml';
            $document->save($outputPath);

            $this->info("XML generado: {$outputPath}");
            $this->info('Validando contra CFDI 4.0');

            $validation = $validator->validate($document, resource_path('xsd/sat/cfdv40.xsd'));

            if(!$validation['valid']){
                $this->newLine();
                $this->error('El CFDI no es válido');

                foreach ($validation['errors'] as $error) {
                    $this->line(sprintf('%s Línea %d: %s', strtoupper($error['level']), $error['line'], $error['message']));
                }

                return self::FAILURE;
            }

            $this->newLine();
            $this->info( '✓ CFDI válido contra XSD 4.0');
            return self::SUCCESS;

        } catch (JsonException $exception) {
            $this->error(
                'El JSON proporcionado no es válido.'
            );

            $this->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }
}
