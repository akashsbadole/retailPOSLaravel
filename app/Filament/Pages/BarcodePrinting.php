<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Services\BarcodeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class BarcodePrinting extends Page implements HasTable
{
    use InteractsWithTable;
    
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';
    
    protected string $view = 'filament.pages.barcode-printing';
    
    protected static ?int $navigationSort = 4;
    
    public ?Collection $selectedProducts = null;
    
    public $barcodeFormat = 'CODE128';
    
    public $labelSize = 'standard';
    
    public $quantityPerProduct = 1;
    
    public function mount(): void
    {
        $this->selectedProducts = collect();
        $this->barcodeFormat = config('settings.default_barcode_format', 'CODE128');
    }
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Barcode Settings')
                    ->schema([
                        Forms\Components\Select::make('barcodeFormat')
                            ->label('Barcode Format')
                            ->options([
                                'CODE128' => 'Code 128',
                                'CODE39' => 'Code 39',
                                'EAN-13' => 'EAN-13',
                                'UPC-A' => 'UPC-A',
                            ])
                            ->default('CODE128')
                            ->required(),
                        
                        Forms\Components\Select::make('labelSize')
                            ->label('Label Size')
                            ->options([
                                'small' => 'Small (1" x 2")',
                                'standard' => 'Standard (2" x 1")',
                                'large' => 'Large (3" x 2")',
                            ])
                            ->default('standard')
                            ->required(),
                        
                        Forms\Components\TextInput::make('quantityPerProduct')
                            ->label('Labels per Product')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(100)
                            ->required(),
                    ])
                    ->columns(3),
            ]);
    }
    
    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query()->where('status', true))
            ->columns([
                Tables\Columns\CheckboxColumn::make('selected')
                    ->label('')
                    ->alignCenter(),
                
                Tables\Columns\ImageColumn::make('image')
                    ->disk('public_uploads')
                    ->size(40)
                    ->square(),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                
                Tables\Columns\TextColumn::make('barcode')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Barcode copied!')
                    ->copyMessageDuration(1500),
                
                Tables\Columns\TextColumn::make('sku')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Stock')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->filters([
                Tables\Filters\Filter::make('low_stock')
                    ->query(fn($query) => $query->lowStock())
                    ->label('Low Stock'),
                
                Tables\Filters\Filter::make('has_barcode')
                    ->query(fn($query) => $query->whereNotNull('barcode')->where('barcode', '!=', ''))
                    ->label('Has Barcode'),
            ])
            ->actions([
                Tables\Actions\Action::make('print_single')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->action(fn (Product $record) => $this->printSingleBarcode($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('print_selected')
                    ->label('Print Selected')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->action(fn (Collection $records) => $this->printSelectedBarcodes($records))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->paginated([10, 25, 50, 100]);
    }
    
    public function printSingleBarcode(Product $product): void
    {
        $this->generateAndDownloadPdf([$product], 1);
    }
    
    public function printSelectedBarcodes(Collection $products): void
    {
        if ($products->isEmpty()) {
            $this->notify('danger', 'Please select products to print barcodes for.');
            return;
        }
        
        $this->generateAndDownloadPdf($products->all(), $this->quantityPerProduct);
    }
    
    protected function generateAndDownloadPdf(array $products, int $quantityPerProduct): void
    {
        $barcodeService = app(BarcodeService::class);
        $labels = [];
        
        foreach ($products as $product) {
            for ($i = 0; $i < $quantityPerProduct; $i++) {
                $labels[] = [
                    'product' => $product,
                    'barcode_html' => $barcodeService->generateHtml(
                        $product->barcode,
                        $this->barcodeFormat,
                        2,
                        60
                    ),
                    'product_name' => $product->name,
                    'price' => number_format($product->price, 2),
                    'barcode' => $product->barcode,
                    'sku' => $product->sku,
                ];
            }
        }
        
        $html = view('filament.pages.barcode-labels', [
            'labels' => $labels,
            'labelSize' => $this->labelSize,
        ])->render();
        
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [100, 50], // Custom label size
            'margin_left' => 2,
            'margin_right' => 2,
            'margin_top' => 2,
            'margin_bottom' => 2,
        ]);
        
        $mpdf->WriteHTML($html);
        
        $filename = 'barcodes_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        
        $this->response = response()->streamDownload(
            function () use ($mpdf) {
                echo $mpdf->Output('', Destination::STRING_RETURN);
            },
            $filename,
            ['Content-Type' => 'application/pdf']
        );
        
        $this->notify('success', 'Barcode labels generated successfully!');
    }
    
    public function generateMissingBarcodes(): void
    {
        $barcodeService = app(BarcodeService::class);
        $productsWithoutBarcode = Product::whereNull('barcode')->orWhere('barcode', '')->get();
        
        foreach ($productsWithoutBarcode as $product) {
            $product->update([
                'barcode' => $barcodeService->generateUniqueBarcode('PROD')
            ]);
        }
        
        $this->notify('success', "Generated barcodes for {$productsWithoutBarcode->count()} products.");
    }
    
    public function getSelectedProductsCountProperty(): int
    {
        return $this->selectedProducts?->count() ?? 0;
    }
    
    public static function canAccess(): bool
    {
        return auth()->user()->can('barcode.print');
    }
}