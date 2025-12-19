<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class BarcodeSettings extends Page implements HasActions
{
    use InteractsWithActions;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.barcode-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'default_barcode_format' => config('settings.default_barcode_format', 'CODE128'),
            'auto_generate_barcodes' => config('settings.auto_generate_barcodes', true),
            'barcode_prefix' => config('settings.barcode_prefix', 'PROD'),
            'scanner_sound_enabled' => config('settings.scanner_sound_enabled', true),
            'scanner_timeout' => config('settings.scanner_timeout', 5000),
            'require_unique_barcodes' => config('settings.require_unique_barcodes', true),
            'allow_manual_barcode_entry' => config('settings.allow_manual_barcode_entry', true),
            'barcode_validation_enabled' => config('settings.barcode_validation_enabled', true),
            'default_label_size' => config('settings.default_label_size', 'standard'),
            'labels_per_product' => config('settings.labels_per_product', 1),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Barcode Scanner Settings')
                    ->description('Configure barcode scanner behavior and validation')
                    ->schema([
                        Forms\Components\Select::make('default_barcode_format')
                            ->label('Default Barcode Format')
                            ->options([
                                'CODE128' => 'Code 128 (Recommended)',
                                'CODE39' => 'Code 39',
                                'EAN-13' => 'EAN-13',
                                'UPC-A' => 'UPC-A',
                            ])
                            ->helperText('Default format for barcode generation and validation')
                            ->required(),
                        
                        Forms\Components\Toggle::make('auto_generate_barcodes')
                            ->label('Auto-generate Barcodes')
                            ->helperText('Automatically generate barcodes for new products')
                            ->default(true),
                        
                        Forms\Components\TextInput::make('barcode_prefix')
                            ->label('Barcode Prefix')
                            ->helperText('Prefix for auto-generated barcodes (e.g., PROD, SKU)')
                            ->default('PROD')
                            ->required()
                            ->maxLength(10),
                        
                        Forms\Components\Toggle::make('scanner_sound_enabled')
                            ->label('Scanner Sound Feedback')
                            ->helperText('Play sound on successful/failed scans')
                            ->default(true),
                        
                        Forms\Components\TextInput::make('scanner_timeout')
                            ->label('Scanner Timeout (ms)')
                            ->helperText('Timeout for barcode scanner input in milliseconds')
                            ->numeric()
                            ->default(5000)
                            ->minValue(1000)
                            ->maxValue(30000),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Barcode Validation')
                    ->description('Configure barcode validation rules')
                    ->schema([
                        Forms\Components\Toggle::make('require_unique_barcodes')
                            ->label('Require Unique Barcodes')
                            ->helperText('Ensure all product barcodes are unique')
                            ->default(true),
                        
                        Forms\Components\Toggle::make('allow_manual_barcode_entry')
                            ->label('Allow Manual Barcode Entry')
                            ->helperText('Allow users to manually enter barcodes')
                            ->default(true),
                        
                        Forms\Components\Toggle::make('barcode_validation_enabled')
                            ->label('Enable Barcode Validation')
                            ->helperText('Validate barcode format before processing')
                            ->default(true),
                    ])
                    ->columns(3),
                
                Forms\Components\Section::make('Barcode Printing')
                    ->description('Configure barcode label printing settings')
                    ->schema([
                        Forms\Components\Select::make('default_label_size')
                            ->label('Default Label Size')
                            ->options([
                                'small' => 'Small (1" x 2")',
                                'standard' => 'Standard (2" x 1")',
                                'large' => 'Large (3" x 2")',
                            ])
                            ->helperText('Default size for barcode labels')
                            ->default('standard')
                            ->required(),
                        
                        Forms\Components\TextInput::make('labels_per_product')
                            ->label('Default Labels per Product')
                            ->helperText('Default number of labels to print per product')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(100)
                            ->required(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Save to cache (you might want to save to database or config file)
        foreach ($data as $key => $value) {
            Cache::forever("settings.{$key}", $value);
            Config::set("settings.{$key}", $value);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    public function resetToDefaults(): void
    {
        $defaults = [
            'default_barcode_format' => 'CODE128',
            'auto_generate_barcodes' => true,
            'barcode_prefix' => 'PROD',
            'scanner_sound_enabled' => true,
            'scanner_timeout' => 5000,
            'require_unique_barcodes' => true,
            'allow_manual_barcode_entry' => true,
            'barcode_validation_enabled' => true,
            'default_label_size' => 'standard',
            'labels_per_product' => 1,
        ];

        foreach ($defaults as $key => $value) {
            Cache::forever("settings.{$key}", $value);
            Config::set("settings.{$key}", $value);
        }

        $this->form->fill($defaults);

        Notification::make()
            ->title('Settings reset to defaults')
            ->success()
            ->send();
    }

    public function testScanner(): void
    {
        $this->dispatch('openScannerTest');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->action('save')
                ->color('success'),
            
            Action::make('reset')
                ->label('Reset to Defaults')
                ->action('resetToDefaults')
                ->color('warning')
                ->requiresConfirmation(),
            
            Action::make('test')
                ->label('Test Scanner')
                ->action('testScanner')
                ->color('info'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('settings.manage');
    }
}