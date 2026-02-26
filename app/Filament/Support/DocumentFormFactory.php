<?php

namespace App\Filament\Support;

use Filament\Forms;
use App\Models\Tercero;
use App\Models\TipoTercero;
use App\Models\BillingSerie;
use App\Models\FormaPago;
use App\Models\Impuesto;
use App\Models\Product;
use App\Services\DocumentCalculator;
use App\Filament\RelationManagers\LineasRelationManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class DocumentFormFactory
{
    public static function terceroSection(string $label = 'Cliente', string $typeCode = 'CLI'): Forms\Components\Section
    {
        return Forms\Components\Section::make($label)
            ->schema([
                Forms\Components\Select::make('tercero_id')
                    ->label($label)
                    ->options(fn() => $typeCode === 'CLI' ? Tercero::clientes()->pluck('nombre_comercial', 'id') : Tercero::proveedores()->pluck('nombre_comercial', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('nombre_comercial')
                            ->label('Nombre Comercial')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('nif_cif')
                            ->label('NIF/CIF')
                            ->required()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(20),
                    ])
                    ->createOptionUsing(function (array $data) use ($typeCode) {
                        $tercero = Tercero::create($data);
                        $tercero->tipos()->attach(TipoTercero::where('codigo', $typeCode)->first());
                        return $tercero->id;
                    }),
            ])->columns(1);
    }

    public static function detailsSection(string $title = 'Datos del Documento', array $extraFields = [], array $options = []): Forms\Components\Section
    {
        $schema = [];

        if (!($options['exclude_numero'] ?? false)) {
            $schema[] = Forms\Components\TextInput::make('numero')
                ->label('Número')
                ->disabled($options['disable_numero'] ?? true)
                ->dehydrated(!($options['disable_numero'] ?? true))
                ->required($options['numero_required'] ?? false)
                ->placeholder($options['numero_placeholder'] ?? 'Se generará automáticamente');
        }

        $schema[] = Forms\Components\Select::make('serie')
            ->label('Serie')
            ->options(BillingSerie::where('activo', true)->pluck('nombre', 'codigo'))
            ->default(fn() => BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A')
            ->searchable()
            ->preload()
            ->required()
            ->createOptionForm([
                Forms\Components\TextInput::make('codigo')->label('Código de Serie')->required()->maxLength(10),
                Forms\Components\TextInput::make('nombre')->label('Nombre')->required(),
                Forms\Components\Toggle::make('devenga_iva')->label('Devenga IVA')->default(true),
            ])
            ->createOptionUsing(fn (array $data) => BillingSerie::create($data)->codigo);

        $schema[] = Forms\Components\DatePicker::make('fecha')
            ->label('Fecha')
            ->default(now())
            ->required();

        if (!($options['exclude_estado'] ?? false)) {
            $schema[] = Forms\Components\Select::make('estado')
                ->label('Estado')
                ->options([
                    'borrador' => 'Borrador',
                    'confirmado' => 'Confirmado',
                    'anulado' => 'Anulado',
                ])
                ->default('borrador')
                ->required();
        }

        return Forms\Components\Section::make($title)
            ->schema(array_merge($schema, $extraFields))
            ->columns(3)
            ->compact();
    }

    public static function linesSection(): array
    {
        return [
            Forms\Components\View::make('filament.components.document-lines-header')
                ->columnSpanFull(),

            Forms\Components\Repeater::make('lineas')
                ->relationship()
                ->schema(LineasRelationManager::getLineFormSchema())
                ->compact()
                ->columns(1)
                ->defaultItems(0)
                ->live()
                ->hiddenLabel()
                ->extraAttributes(['class' => 'document-lines-repeater'])
                ->extraItemActions([
                    Forms\Components\Actions\Action::make('editLine')
                        ->label('Editar Línea')
                        ->tooltip('Editar datos del producto y de la línea')
                        ->icon('heroicon-m-pencil-square')
                        ->color('warning')
                        ->modalHeading('Editar Línea y Producto')
                        ->modalSubmitActionLabel('Guardar cambios')
                        ->form([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    Forms\Components\TextInput::make('sku')
                                        ->label('Referencia/SKU (Producto)')
                                        ->required(),
                                    Forms\Components\TextInput::make('name')
                                        ->label('Nombre (Producto)')
                                        ->columnSpan(2)
                                        ->required(),
                                    Forms\Components\Select::make('iva')
                                        ->label('IVA Aplicable')
                                        ->options(Impuesto::where('tipo', 'iva')
                                            ->where('activo', true)
                                            ->get()
                                            ->mapWithKeys(fn ($i) => [(string)$i->valor => $i->valor . '%']))
                                        ->required(),
                                ])
                        ])
                        ->fillForm(function (array $arguments, Forms\Components\Repeater $component) {
                            $itemData = $component->getItemState($arguments['item']);
                            $productId = $itemData['product_id'] ?? null;
                            
                            $sku = $itemData['codigo'] ?? null;
                            $name = $itemData['descripcion'] ?? null;
                            $iva = $itemData['iva'] ?? null;

                            if ($productId) {
                                $product = Product::find($productId);
                                if ($product) {
                                    $sku = $product->sku;
                                    $name = $product->name;
                                    $iva = (string)$product->tax_rate;
                                }
                            }

                            return [
                                'sku' => $sku,
                                'name' => $name,
                                'iva' => $iva,
                            ];
                        })
                        ->action(function (array $data, array $arguments, Forms\Components\Repeater $component) {
                            $itemData = $component->getItemState($arguments['item']);
                            $productId = $itemData['product_id'] ?? null;

                            if ($productId) {
                                $product = Product::find($productId);
                                if ($product) {
                                    $product->update([
                                        'sku' => $data['sku'],
                                        'name' => $data['name'],
                                        'tax_rate' => $data['iva'],
                                    ]);
                                }
                            }

                            $itemData['codigo'] = $data['sku'];
                            $itemData['descripcion'] = $data['name'];
                            $itemData['iva'] = $data['iva'];

                            $state = $component->getState();
                            $state[$arguments['item']] = $itemData;
                            $component->state($state);
                        })
                ])
                ->columnSpanFull(),
        ];
    }

    public static function totalsSection(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Totales')
            ->schema([
                Forms\Components\Placeholder::make('totales_calculados')
                    ->hiddenLabel()
                    ->content(function (Forms\Get $get) {
                        $lineas = $get('lineas') ?? [];
                        $terceroId = $get('tercero_id');
                        $tieneRecargo = false;
                        if ($terceroId) {
                            $tercero = Tercero::find($terceroId);
                            $tieneRecargo = $tercero?->recargo_equivalencia ?? false;
                        }
                        
                        $breakdown = DocumentCalculator::calculate($lineas, $tieneRecargo);
                        
                        return view('filament.components.tax-breakdown-live', [
                            'breakdown' => $breakdown, 
                            'tieneRecargo' => $tieneRecargo
                        ]);
                    }),
            ])->columnSpanFull();
    }
}
