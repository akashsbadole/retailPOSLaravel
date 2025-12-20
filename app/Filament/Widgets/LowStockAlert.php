<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockAlert extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::lowStock()
                    ->orderBy('quantity', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Current Stock')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($record): string => $record->quantity <= 0 ? 'danger' : 'warning'),
                Tables\Columns\TextColumn::make('minimum_stock')
                    ->label('Minimum Stock')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('stock_difference')
                    ->label('Difference')
                    ->getStateUsing(fn ($record): int => $record->quantity - $record->minimum_stock)
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($record): string => match (true) {
                        $record->quantity <= 0 => 'danger',
                        $record->quantity <= $record->minimum_stock / 2 => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn ($state): string => $state <= 0 ? $state : '+' . $state),
            ])
            ->actions([
                Action::make('update_stock')
                    ->label('Update Stock')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record): string => route('filament.admin.resources.products.edit', $record))
                    ->openUrlInNewTab(),
                Action::make('reorder')
                    ->label('Reorder')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('success')
                    ->action(function ($record) {
                        // This could be extended to create a purchase order or send notification
                        // For now, we'll just show a success message
                        $this->dispatch('show-notification', [
                            'message' => "Reorder request created for {$record->name}",
                            'type' => 'success'
                        ]);
                    }),
            ])
            ->emptyStateHeading('No Low Stock Products')
            ->emptyStateDescription('All products are well stocked!')
            ->emptyStateActions([
                Action::make('view_all_products')
                    ->label('View All Products')
                    ->url(route('filament.admin.resources.products.index'))
                    ->icon('heroicon-o-squares-2x2'),
            ])
            ->paginated([5, 10, 25])
            ->poll('60s'); // Refresh every minute
    }

    public static function canView(): bool
    {
        // Check if low stock alerts are enabled in settings
        $lowStockEnabled = \App\Models\Setting::where('key', 'low_stock_alerts_enabled')->first()?->value ?? '1';
        return $lowStockEnabled === '1' && auth()->user()->can('viewAny', Product::class);
    }
}