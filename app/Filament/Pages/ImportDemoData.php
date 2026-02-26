<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;
use App\Models\Product;
use App\Models\Tercero;
use App\Models\Documento;

class ImportDemoData extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Importar datos de prueba';
    protected static ?string $title = 'Generador de Datos Demo';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.import-demo-data';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'products_count' => 50,
            'clients_count' => 10,
            'suppliers_count' => 5,
            'documents_count' => 20,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Parámetros de Generación')
                    ->description('Selecciona la cantidad de registros que deseas generar para cada módulo.')
                    ->schema([
                        TextInput::make('products_count')
                            ->label('Productos')
                            ->numeric()
                            ->default(50)
                            ->minValue(0)
                            ->maxValue(1000)
                            ->required(),
                        
                        TextInput::make('clients_count')
                            ->label('Clientes')
                            ->numeric()
                            ->default(10)
                            ->minValue(0)
                            ->maxValue(200)
                            ->required(),

                        TextInput::make('suppliers_count')
                            ->label('Proveedores')
                            ->numeric()
                            ->default(5)
                            ->minValue(0)
                            ->maxValue(50)
                            ->required(),

                        TextInput::make('documents_count')
                            ->label('Documentos (Varios tipos)')
                            ->numeric()
                            ->default(20)
                            ->minValue(0)
                            ->maxValue(500)
                            ->required()
                            ->helperText('Se generarán tipos aleatorios: presupuestos, pedidos, facturas, etc.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        $formData = $this->form->getState();

        try {
            // 1. Productos
            if ($formData['products_count'] > 0) {
                Product::factory()->count($formData['products_count'])->create();
            }

            // 2. Clientes
            if ($formData['clients_count'] > 0) {
                Tercero::factory()->count($formData['clients_count'])->cliente()->create();
            }

            // 3. Proveedores
            if ($formData['suppliers_count'] > 0) {
                Tercero::factory()->count($formData['suppliers_count'])->proveedor()->create();
            }

            // 4. Documentos
            if ($formData['documents_count'] > 0) {
                // Requiere que existan productos y terceros
                if (Product::count() === 0 || Tercero::count() === 0) {
                    Notification::make()
                        ->title('Error')
                        ->body('Debes generar productos y terceros antes que los documentos.')
                        ->danger()
                        ->send();
                    return;
                }

                // Generar los documentos con líneas
                Documento::factory()
                    ->count($formData['documents_count'])
                    ->withLines()
                    ->confirmado() // Generar números de documento
                    ->create();
            }

            Notification::make()
                ->title('Éxito')
                ->body('Se han generado los datos correctamente.')
                ->success()
                ->send();

            return redirect()->to(request()->header('Referer'));

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error en la generación')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
