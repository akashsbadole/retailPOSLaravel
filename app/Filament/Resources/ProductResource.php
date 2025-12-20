<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Excel;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextInputColumn;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use App\Filament\Actions\ProductImportAction;
use App\Filament\Actions\ProductExportAction;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('barcode')
                    ->required()
                    ->unique(Product::class, 'barcode', ignoreRecord: true)
                    ->helperText('Scan or enter barcode. Leave empty to auto-generate.')
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (empty($state)) {
                            $barcodeService = app(\App\Services\BarcodeService::class);
                            $prefix = config('settings.barcode_prefix', 'PROD');
                            $set('barcode', $barcodeService->generateUniqueBarcode($prefix));
                        }
                    }),
                TextInput::make('sku')
                    ->label('SKU')
                    ->unique(Product::class, 'sku', ignoreRecord: true)
                    ->nullable(),
                TextInput::make('price')
                    ->label('Selling Price')
                    ->numeric()
                    ->required(),
                TextInput::make('cost_price')
                    ->label('Cost Price')
                    ->numeric()
                    ->step(0.01)
                    ->nullable()
                    ->visible(fn() => auth()->user()->can('product.view-cost'))
                    ->helperText('Only visible to users with cost price permission'),
                TextInput::make('quantity')
                    ->numeric()
                    ->minValue(0)
                    ->default(1)
                    ->required(),
                TextInput::make('minimum_stock')
                    ->label('Minimum Stock')
                    ->numeric()
                    ->minValue(0)
                    ->default(function () {
                        return config('settings.default_minimum_stock', 10);
                    })
                    ->helperText('Alert when stock reaches this level')
                    ->required(),
                TextInput::make('tax')
                    ->label('Tax (%)')
                    ->suffixIcon('heroicon-o-information-circle')
                    ->helperText('Leave empty to use default tax rate from settings (' . (config('settings.default_tax_rate', '0.00')) . '%). Example: 5 for 5% VAT/GST.')
                    ->numeric()
                    ->default(function () {
                        return config('settings.default_tax_rate', 0.00);
                    }),
                FileUpload::make('image')
                    ->disk('public_uploads')
                    ->panelLayout('grid')
                    ->visibility('public'),
                Toggle::make('status')
                    ->label('Active')
                    ->default(true)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->width(250)
                    ->wrap()
                    ->sortable()
                    ->searchable(),
                ImageColumn::make('image')->disk('public_uploads')
                    ->size(50)
                    ->square(),
                TextColumn::make('barcode')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Barcode copied!')
                    ->copyMessageDuration(1500),
                TextColumn::make('sku')->searchable(),
                TextInputColumn::make('quantity')->type('number')
                    ->sortable()
                    ->width(10)
                    ->rules(['required', 'integer', 'min:1'])
                    ->formatStateUsing(fn($record) => $record->quantity)
                    ->color(fn($record): string => match (true) {
                        $record->quantity <= 0 => 'danger',
                        $record->isLowStock() => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('minimum_stock')
                    ->label('Min Stock')
                    ->sortable()
                    ->width(10)
                    ->alignCenter(),
                TextColumn::make('price')->label('Selling Price')->sortable(),
                TextColumn::make('cost_price')
                    ->label('Cost Price')
                    ->sortable()
                    ->visible(fn() => auth()->user()->can('product.view-cost'))
                    ->formatStateUsing(fn($state) => $state ? '$' . number_format($state, 2) : 'N/A'),
                TextColumn::make('stock_status')
                    ->label('Stock Status')
                    ->badge()
                    ->color(fn($record): string => match (true) {
                        $record->quantity <= 0 => 'danger',
                        $record->isLowStock() => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn($record): string => match (true) {
                        $record->quantity <= 0 => 'Out of Stock',
                        $record->isLowStock() => 'Low Stock',
                        default => 'In Stock',
                    }),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn(): bool => auth()->user()->can('product.edit')),

                DeleteAction::make()
                    ->visible(fn(): bool => auth()->user()->can('product.delete')),

                Action::make('stock_adjustment')
                    ->label('Adjust Stock')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('warning')
                    ->url(fn($record): string => route('filament.admin.resources.stock-adjustments.create', ['product_id' => $record->id]))
                    ->visible(fn(): bool => auth()->user()->can('stock.adjust')),

                Action::make('view_history')
                    ->label('Stock History')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->url(fn($record): string => route('filament.admin.resources.stock-adjustments.index', ['product_id' => $record->id]))
                    ->visible(fn(): bool => auth()->user()->can('stock.view')),

                Action::make('print_barcode')
                    ->label('Print Barcode')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn($record): string => route('filament.admin.pages.barcode-printing', ['product_id' => $record->id]))
                    ->visible(fn(): bool => auth()->user()->can('barcode.print')),

                Action::make('regenerate_barcode')
                    ->label('Regenerate Barcode')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function (Product $record) {
                        $record->regenerateBarcode();
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Barcode regenerated')
                            ->body('New barcode has been generated for ' . $record->name)
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->visible(fn(): bool => auth()->user()->can('product.edit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn(): bool => auth()->user()->can('product.delete')),

                    BulkAction::make('print_barcodes')
                        ->label('Print Barcodes')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->action(function (Collection $records) {
                            return redirect()->route('filament.admin.pages.barcode-printing', ['product_ids' => $records->pluck('id')]);
                        })
                        ->visible(fn(): bool => auth()->user()->can('barcode.print')),

                    BulkAction::make('generate_barcodes')
                        ->label('Generate Missing Barcodes')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (empty($record->barcode)) {
                                    $record->generateBarcode();
                                    $count++;
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title("Generated {$count} barcodes")
                                ->body('Missing barcodes have been generated')
                                ->send();
                        })
                        ->visible(fn(): bool => auth()->user()->can('product.edit')),
                ]),
                ProductImportAction::make(),
                ProductExportAction::make(),
                ExportBulkAction::make()->exports([
                    ExcelExport::make()
                        ->withFilename(fn($resource) => $resource::getModelLabel() . '-' . date('Y-m-d'))
                        ->withWriterType(Excel::CSV)
                        ->withColumns([
                            Column::make('name')->heading('Name'),
                            Column::make('barcode')->heading('Barcode'),
                            Column::make('sku')->heading('SKU'),
                            Column::make('price')->heading('Selling Price'),
                            Column::make('cost_price')
                                ->heading('Cost Price')
                                ->visible(fn() => auth()->user()->can('product.view-cost')),
                            Column::make('tax')->heading('Tax'),
                            Column::make('quantity')->heading('Quantity'),
                        ])
                ])
            ]);
    }


    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('product.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('product.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('product.edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('product.delete') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('product.delete') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}
