<?php
use App\Services\Cfdi\CfdiCalculator;
use App\Services\Cfdi\CfdiXmlGenerator;
use App\Services\Cfdi\CfdiXmlValidator;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;


new class extends Component
{
    public $calculated = [];
    public $canValidateXML = false;
    public $canDownloadXML = false;
    public $xmlErrors = [];

    public $docData = [];

    public $tiposComrobante = [
        'I' => 'Ingreso',
        'E' => 'Egreso',
        'T' => 'Traslado',
        'N' => 'Nómina',
        'P' => 'Pago',
    ];

    public $typesExportation = [
        '01' => 'No Aplica',
        '02' => 'Definitiva con clave A1',
        '03' => 'Temporal',
        '04' => 'Definitiva con clave distinta a A1',
    ];

    public $paymentTypes = [
        '01' => 'Efectivo',
        '04' => 'Tarjeta de crédito',
        '99' => 'Por definir',
    ];

    public $paymentMethods = [
        'PUE' => 'Pago en una sola exhibición',
        'PPD' => 'Pago en parcialidades o diferido',
    ];

    public $currencyTypes = [
        'MXN' => 'Peso Mexicano',
        'USD' => 'Dólar Estadounidense',
    ];

    public $taxRegimes = [
        '601' => 'General de Ley Personas Morales',
        '603' => 'Personas Morales con Fines no Lucrativos',
        '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
    ];

    public $cfdiUses = [
        'G01' => 'Adquisición de mercancías.',
        'G02' => 'Devoluciones, descuentos o bonificaciones.',
        'G03' => 'Gastos en general.',
    ];

    public function mount() {
        $inputPath = base_path('storage/app/cfdi/input.json');

        if(!file_exists($inputPath)){
            return;
        }

        $this->docData = json_decode(file_get_contents($inputPath), true, 512, JSON_THROW_ON_ERROR);
    }

    public function calculate(CfdiCalculator $calculator, CfdiXmlGenerator $generator) {
        $this->canValidateXML = false;
        $this->canDownloadXML = false;

        $this->calculated = $calculator->calculate($this->docData['conceptos']);
         $document = $generator->generate($this->docData, $this->calculated);
         $outputDirectory = storage_path('app/private/cfdi');

        if(!is_dir($outputDirectory)){
            mkdir($outputDirectory, 0755, true);
        }

        $outputPath = $outputDirectory . DIRECTORY_SEPARATOR . 'output.xml';
        $document->save($outputPath);

        $this->canValidateXML = Storage::exists('cfdi/output.xml');
    }

    public function validateXML(CfdiXmlValidator $validator) {
        $xmlContent = Storage::get('cfdi/output.xml');
        $document = new DomDocument();
        $document->loadXML($xmlContent);
        $validation = $validator->validate($document, resource_path('xsd/sat/cfdv40.xsd'));
        $this->canDownloadXML = $validation['valid'];
        if(!$validation['valid']){

            $this->xmlErrors = $validation['errors'];

        }
        Flux::modal('xml-validation-result')->show();
    }

    public function downloadXML() {
        $path = 'cfdi/output.xml';
        return  Storage::disk('local')
                ->download($path,"CFDI-{$this->docData['comprobante']['serie']}-{$this->docData['comprobante']['folio']}.xml");
    }
};
?>

<div>
    <form wire:submit="calculate">
        <div class="flex flex-col w-full font-body-md text-on-surface">
            <div class="flex items-center justify-between mb-xl pb-md bg-surface border-b-2 border-surface-container shadow-sm p-lg rounded-xl relative overflow-hidden">

            <div class="flex items-center gap-md z-10">
            <div class="w-12 h-12 bg-primary-container rounded-xl flex items-center justify-center shadow-md">
            <span class="material-symbols-outlined text-[28px] text-on-primary-container">post_add</span>
            </div>
            <div>
            <h1 class="font-headline-lg text-headline-lg text-on-surface">Nuevo CFDI 4.0</h1>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">Facturación Electrónica SAT</p>
            </div>
            </div>
            </div>
                    <div class="grid grid-cols-12 gap-lg relative">
                    <div class="col-span-12 lg:col-span-8 flex flex-col gap-lg">
                    <div class="bg-surface shadow-md rounded-xl p-xl relative overflow-hidden">
                            <div class="flex items-center justify-between mb-lg">
                                <h2 class="font-headline-md text-headline-md text-on-surface flex items-center gap-sm">
                                    <span class="material-symbols-outlined text-primary text-[20px]">description</span>
                                    Datos del Comprobante
                                </h2>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-md mb-2">
                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">Serie</label>
                                    <input class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-primary shadow-sm border border-transparent focus:border-transparent transition-all w-full uppercase"
                                    type="text"
                                    wire:model="docData.comprobante.serie"
                                    />
                                </div>
                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">Folio</label>
                                    <input class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-primary shadow-sm border border-transparent focus:border-transparent transition-all w-full"
                                    type="text"
                                    wire:model="docData.comprobante.folio"/>
                                </div>
                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">Moneda</label>
                                    <select
                                      wire:model="docData.comprobante.moneda"
                                    class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-primary shadow-sm border border-transparent focus:border-transparent transition-all w-full appearance-none">
                                        @forelse ($currencyTypes as $key => $value)
                                            <option value="{{$key}}">{{$value}}</option>
                                        @empty
                                            <option value="">Sin datos</option>
                                        @endforelse
                                    </select>
                                </div>
                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">C.P Expedición</label>
                                    <input class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-primary shadow-sm border border-transparent focus:border-transparent transition-all w-full"
                                    type="text"
                                    wire:model="docData.comprobante.lugarExpedicion"/>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">Tipo de Comprobante<span class="text-error">*</span></label>
                                    <select
                                    wire:model="docData.comprobante.tipoDeComprobante"
                                    class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-primary shadow-sm border border-transparent focus:border-transparent transition-all w-full appearance-none">
                                        @forelse ($tiposComrobante as $key => $value)
                                            <option value="{{$key}}">{{$value}}</option>
                                        @empty
                                        <option value="">Sin datos</option>
                                        @endforelse
                                    </select>
                                </div>

                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">Exportación<span class="text-error">*</span></label>
                                    <select
                                    wire:model="docData.comprobante.exportacion"
                                    class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-primary shadow-sm border border-transparent focus:border-transparent transition-all w-full appearance-none">
                                        @forelse ($typesExportation as $key => $value)
                                            <option value="{{$key}}">{{$value}}</option>
                                        @empty
                                            <option value="">Sin datos</option>
                                        @endforelse
                                    </select>
                                </div>

                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">Forma de pago<span class="text-error">*</span></label>
                                    <select
                                    wire:model="docData.comprobante.formaPago"
                                    class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-primary shadow-sm border border-transparent focus:border-transparent transition-all w-full appearance-none">
                                        @forelse ($paymentTypes as $key => $value)
                                            <option value="{{$key}}">{{$value}}</option>
                                        @empty
                                            <option value="">Sin datos</option>
                                        @endforelse
                                    </select>
                                </div>

                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">Método de pago<span class="text-error">*</span></label>
                                    <select
                                    wire:model="docData.comprobante.metodoPago"
                                    class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-primary shadow-sm border border-transparent focus:border-transparent transition-all w-full appearance-none">
                                        @forelse ($paymentMethods as $key => $value)
                                            <option value="{{$key}}">{{$value}}</option>
                                        @empty
                                            <option value="">Sin datos</option>
                                        @endforelse
                                    </select>
                                </div>

                            </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                        <div class="bg-surface shadow-md rounded-xl p-lg relative overflow-hidden group">
                                <h3 class="font-headline-md text-headline-md text-on-surface mb-md flex items-center gap-sm">
                                <span class="material-symbols-outlined text-secondary text-[20px]">storefront</span>
                                    Emisor
                                </h3>
                            <div class="flex flex-col gap-md z-10 relative">
                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">RFC</label>
                                    <input
                                    class="bg-surface-container-low text-on-surface-variant px-md py-sm rounded-lg cursor-not-allowed font-mono-md"
                                    type="text"
                                    wire:model="docData.emisor.rfc"/>
                                </div>
                                <div class="flex flex-col gap-xs">
                                        <label class="font-label-md text-label-md text-on-surface-variant">Nombre</label>
                                        <input class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-primary shadow-sm border border-transparent focus:border-transparent transition-all w-full"
                                        type="text"
                                        wire:model="docData.emisor.nombre"/>
                                </div>
                                <div class="flex flex-col gap-xs">
                                <label class="font-label-md text-label-md text-on-surface-variant">Régimen Fiscal</label>
                                    <select
                                    wire:model="docData.emisor.regimenFiscal"
                                    class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg shadow-sm border-transparent focus:ring-2 focus:ring-primary w-full appearance-none truncate">
                                        @forelse ($taxRegimes as $key => $value)
                                            <option value="{{$key}}">{{$value}}</option>
                                        @empty
                                            <option value="">Sin datos</option>
                                        @endforelse
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="bg-surface shadow-md rounded-xl p-lg relative">
                            <h3 class="font-headline-md text-headline-md text-on-surface mb-md flex items-center gap-sm">
                                <span class="material-symbols-outlined text-tertiary text-[20px]">person</span>
                                    Receptor
                            </h3>
                            <div class="flex flex-col gap-md">
                                <div class="grid grid-cols-2 gap-md">
                                    <div class="flex flex-col gap-xs">
                                        <label class="font-label-md text-label-md text-on-surface-variant flex justify-between">RFC</label>
                                        <div class="relative">
                                            <input
                                            class="bg-surface-container-low text-on-surface-variant px-md py-sm rounded-lg cursor-not-allowed font-mono-md"
                                            type="text"
                                            wire:model="docData.receptor.rfc"
                                            />
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-xs">
                                        <label class="font-label-md text-label-md text-on-surface-variant">CP</label>
                                        <input class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg shadow-sm focus:ring-2 focus:ring-primary w-full font-mono-md"
                                        wire:model="docData.receptor.domicilioFiscalReceptor"
                                        type="text"
                                        />
                                    </div>
                                </div>
                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">Nombre</label>
                                    <input
                                    class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg focus:outline-none focus:ring-2 focus:ring-primary shadow-sm border border-transparent focus:border-transparent transition-all w-full"
                                    type="text"
                                    wire:model="docData.receptor.nombre"/>
                                </div>
                                <div class="flex flex-col gap-xs">
                                    <label class="font-label-md text-label-md text-on-surface-variant">Régimen Fiscal</label>
                                    <select
                                    class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg shadow-sm border-transparent focus:ring-2 focus:ring-primary w-full appearance-none truncate"
                                    wire:model="docData.receptor.regimenFiscalReceptor"
                                    >
                                        @forelse ($taxRegimes as $key => $value)
                                            <option value="{{$key}}">{{$value}}</option>
                                        @empty
                                            <option value="">Sin datos</option>
                                        @endforelse
                                    </select>
                                </div>
                                <div class="flex flex-col gap-xs">
                                <label class="font-label-md text-label-md text-on-surface-variant">Uso CFDI</label>
                                <select class="bg-surface-container-lowest text-on-surface px-md py-sm rounded-lg shadow-sm focus:ring-2 focus:ring-primary w-full appearance-none"
                                wire:model="docData.receptor.usoCFDI"
                                >
                                    @forelse ($cfdiUses as $key => $value)
                                        <option value="{{$key}}">{{$value}}</option>
                                    @empty
                                        <option value="">Sin datos</option>
                                    @endforelse
                                </select>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="bg-surface shadow-md rounded-xl p-xl">
                        <div class="flex items-center justify-between mb-lg">
                            <h2 class="font-headline-md text-headline-md text-on-surface flex items-center gap-sm">
                                <span class="material-symbols-outlined text-primary text-[20px]">list_alt</span>
                                Conceptos
                            </h2>
                            <button class="bg-secondary-container text-on-secondary-container px-md py-sm rounded-lg font-label-md text-label-md hover:bg-secondary transition-colors hover:text-on-secondary shadow-sm flex items-center gap-xs">
                                <span class="material-symbols-outlined text-[18px]">add</span>
                                Agregar Concepto
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b-2 border-surface-container-high bg-surface-container-lowest">
                                        <th class="p-sm font-label-md text-label-md text-on-surface-variant uppercase w-16">Cant</th>
                                        <th class="p-sm font-label-md text-label-md text-on-surface-variant uppercase w-24">Unidad</th>
                                        <th class="p-sm font-label-md text-label-md text-on-surface-variant uppercase">Descripción</th>
                                        <th class="p-sm font-label-md text-label-md text-on-surface-variant uppercase text-right w-32">Valor U.</th>
                                        <th class="p-sm font-label-md text-label-md text-on-surface-variant uppercase text-right w-32">IVA</th>
                                        <th class="p-sm w-10"></th>
                                    </tr>
                                </thead>
                                <tbody class="font-body-sm text-body-sm text-on-surface">
                                    @forelse ($docData['conceptos'] as $key => $concepto)
                                        <tr class="border-b border-surface-container hover:bg-surface-container-lowest transition-colors group">
                                            <td class="p-sm"><span class="w-full bg-transparent focus:ring-1 focus:ring-primary rounded p-xs">{{$concepto['cantidad']}}</span></td>
                                            <td class="p-sm"><span class="bg-surface-container px-xs py-base rounded font-mono-md text-[10px]">{{$concepto['claveUnidad']}} - {{$concepto['unidad']}}</span></td>
                                            <td class="p-sm">
                                                <span class="w-full bg-transparent text-[10px] focus:ring-1 focus:ring-primary rounded p-xs font-light line-clamp-2" >{{$concepto['descripcion']}}</span>
                                                <div class="text-[10px] text-on-surface-variant mt-xs font-mono-md">ClaveProdServ: {{$concepto['claveProdServ']}}</div>
                                            </td>
                                            <td class="p-sm text-right"><span class="w-full bg-transparent focus:ring-1 focus:ring-primary rounded p-xs text-right font-mono-md">{{$concepto['valorUnitario']}}</span> </td>
                                            <td class="p-sm text-right font-mono-md font-semibold text-primary">{{$concepto['iva']}}</td>
                                            <td class="p-sm text-center">
                                                <button class="text-outline hover:text-error transition-colors opacity-0 group-hover:opacity-100">
                                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                    <tr class="flex justify-center items-center">
                                        Sin datos
                                    </tr>

                                    @endforelse

                                    <tr class="bg-surface-container-lowest/50">
                                        <td class="p-sm pl-xl" colspan="6">
                                            <div class="flex gap-lg text-[11px] text-on-surface-variant border-l-2 border-surface-container-high pl-md">
                                                <span>Base: ${{array_sum(array_column($docData['conceptos'], 'valorUnitario'))}}</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                        <div class="col-span-12 lg:col-span-4 flex flex-col gap-lg">
                            <div class="bg-primary text-on-primary rounded-xl shadow-lg p-xl sticky top-24 relative overflow-hidden">
                                <h2 class="font-headline-md text-headline-md mb-lg border-b border-on-primary/20 pb-sm">Resumen Total</h2>
                                <div class="flex flex-col gap-sm font-body-md">
                                    <div class="flex justify-between items-center">
                                        <span class="text-on-primary/80">Subtotal</span>
                                        <span class="font-mono-md font-semibold">{{isset($calculated['subtotal'])?$calculated['subtotal']:'--.--'}}</span>
                                    </div>
                                    <div class="my-sm border-t border-on-primary/20 border-dashed pt-sm flex flex-col gap-xs text-[13px]">
                                        <div class="flex justify-between items-center text-on-primary/90">
                                        <span>IVA Trasladado (16%)</span>
                                        <span class="font-mono-md">{{isset($calculated['totalImpuestosTrasladados'])?$calculated['totalImpuestosTrasladados']:'--.--'}}</span>
                                        </div>
                                    </div>
                                    <div class="mt-md pt-md border-t border-on-primary/30 flex justify-between items-end">
                                        <span class="font-headline-md text-headline-md">Total</span>
                                        <span class="font-display text-display tracking-tight">{{isset($calculated['total'])?$calculated['total']:'---.--'}}</span>
                                    </div>
                                    <div class="text-right text-[11px] text-on-primary/60 uppercase mt-xs tracking-wider">MXN</div>
                                </div>

                                <div class="mt-xl flex flex-col gap-md">
                                    <button type="submit" class="w-full bg-tertiary text-on-tertiary py-md rounded-lg font-label-md text-label-md shadow-md hover:bg-tertiary-container transition-colors flex justify-center items-center gap-sm">
                                        <span class="material-symbols-outlined text-[20px]">calculate</span>
                                            Calcular
                                    </button>
                                    <div class="grid grid-cols-2 gap-sm">
                                        <button class="w-full  py-sm rounded-lg font-label-md text-[11px] border  flex justify-center items-center gap-xs
                                            {{  $canValidateXML ? 'bg-surface/10 hover:bg-surface/20 transition-colors border-on-primary/20 text-on-primary':'border-transparent cursor-not-allowed bg-surface/5 text-on-primary/50'  }}"
                                        wire:click="validateXML" type="button">
                                            <span class="material-symbols-outlined text-[16px]">fact_check</span>
                                            Validar XML
                                        </button>
                                        <button class="w-full  py-sm rounded-lg font-label-md text-[11px] border  flex justify-center items-center gap-xs
                                            {{  $canDownloadXML ? 'bg-surface/10 hover:bg-surface/20 transition-colors border-on-primary/20 text-on-primary':'border-transparent cursor-not-allowed bg-surface/5 text-on-primary/50'  }}"
                                        wire:click="downloadXML" type="button">
                                            <span class="material-symbols-outlined text-[16px]">download</span>
                                            Descargar XML
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
            </div>
        </div>
    </form>
    <flux:modal
    name="xml-validation-result"
    class="md:w-[32rem]"
    >
    <div class="space-y-6">

        @if ($canDownloadXML)

            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <flux:icon.check-circle
                        class="size-8 text-green-500"
                    />

                    <flux:heading size="lg">
                        XML válido
                    </flux:heading>
                </div>

                <flux:text>
                    El XML generado cumple correctamente
                    con el esquema XSD de CFDI 4.0.
                </flux:text>
            </div>

        @else

            <div class="space-y-4">

                <div class="flex items-center gap-3">
                    <flux:icon.x-circle
                        class="size-8 text-red-500"
                    />

                    <flux:heading size="lg">
                        XML inválido
                    </flux:heading>
                </div>

                <flux:text>
                    Se encontraron errores durante
                    la validación del CFDI.
                </flux:text>

                <div
                    class="
                        max-h-72
                        overflow-y-auto
                        rounded-lg
                        border
                        border-red-200
                        bg-red-50
                        p-4
                        dark:border-red-900
                        dark:bg-red-950/30
                    "
                >
                    <div class="space-y-4">

                        @foreach ($xmlErrors as $error)

                            <div class="text-sm">

                                <div class="font-medium text-red-700 dark:text-red-400">
                                    {{ strtoupper($error['level']) }}
                                    · Línea {{ $error['line'] }}
                                </div>

                                <div class="mt-1 text-zinc-700 dark:text-zinc-300">
                                    {{ $error['message'] }}
                                </div>

                            </div>

                        @endforeach

                    </div>
                </div>

            </div>

        @endif

        <div class="flex justify-end">

            <flux:modal.close>
                <flux:button>
                    Cerrar
                </flux:button>
            </flux:modal.close>

        </div>

    </div>
</flux:modal>
</div>
