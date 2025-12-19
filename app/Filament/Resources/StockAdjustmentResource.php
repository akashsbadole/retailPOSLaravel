<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Filament\Resources\StockAdjustmentResource\RelationManagers;
use App\Models\StockAdjustment;
use App\Models\Product;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Stock Adjustments';

    protected static ?string $modelLabel = 'Stock Adjustment';

    protected static ?string $pluralModelLabel = 'Stock Adjustments';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stock Adjustment Details')
                    ->description('Adjust product stock quantity for various reasons')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->relationship('product', 'name')
                                ->searchable()
                                ->getSearchResultsUsing(function (string $search) {
                                    return Product::where('name', 'like', "%{$search}%")
                                        ->orWhere('barcode', 'like', "%{$search}%")
                                        ->orWhere('sku', 'like', "%{$search}%")
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(function ($product) {
                                            return [$product->id => "{$product->name} (SKU: {$product->sku}, Stock: {$product->quantity})"];
                                        });
                                })
                                ->getOptionLabelUsing(function ($value) {
                                    $product = Product::find($value);
                                    return $product ? "{$product->name} (SKU: {$product->sku}, Stock: {$product->quantity})" : '';
                                })
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('current_stock', $product->quantity);
                                        }
                                    }
                                }),

                            TextInput::make('current_stock')
                                ->label('Current Stock')
                                ->disabled()
                                ->default(0),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('adjustment_type')
                                ->label('Adjustment Type')
                                ->options([
                                    'increase' => 'Increase Stock',
                                    'decrease' => 'Decrease Stock',
                                ])
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $set('quantity', 1);
                                }),

                            TextInput::make('quantity')
                                ->label('Quantity')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(1)
                                ->helperText(function (callable $get) {
                                    $type = $get('adjustment_type');
                                    return $type === 'decrease' 
                                        ? 'This quantity will be deducted from current stock' 
                                        : 'This quantity will be added to current stock';
                                }),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('reason')
                                ->label('Reason')
                                ->options([
                                    'damage' => 'Damage',
                                    'theft' => 'Theft',
                                    'manual_correction' => 'Manual Correction',
                                    'other' => 'Other',
                                ])
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state === 'other') {
                                        $set('notes', '');
                                    }
                                }),

                            TextInput::make('user_id')
                                ->label('Created By')
                                ->default(Auth::id())
                                ->disabled()
                                ->required(),
                        ]),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Enter any additional notes about this adjustment...')
                            ->required(function (callable $get) {
                                return $get('reason') === 'other';
                            }),

                        Section::make('Preview')
                            ->schema([
                                Placeholder::make('preview')
                                    ->label('Adjustment Preview')
                                    ->content(function (callable $get) {
                                        $productId = $get('product_id');
                                        $quantity = $get('quantity');
                                        $type = $get('adjustment_type');
                                        
                                        if (!$productId || !$quantity || !$type) {
                                            return 'Select product and adjustment details to see preview';
                                        }
                                        
                                        $product = Product::find($productId);
                                        if (!$product) {
                                            return 'Product not found';
                                        }
                                        
                                        $currentStock = $product->quantity;
                                        $newStock = $type === 'increase' 
                                            ? $currentStock + $quantity 
                                            : $currentStock - $quantity;
                                        
                                        return "Current: {$currentStock} → New: {$newStock} (" . ($type === 'increase' ? '+' : '-') . "{$quantity})";
                                    }),
                            ])
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->description(fn ($record): string => $record->created_at->diffForHumans()),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): string => "SKU: " . ($record->product->sku ?? 'N/A')),

                TextColumn::make('quantity_with_sign')
                    ->label('Quantity')
                    ->badge()
                    ->color(fn ($record): string => $record->adjustment_type === 'increase' ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('adjustment_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($record): string => $record->adjustment_type === 'increase' ? 'success' : 'danger'),

                TextColumn::make('formatted_reason')
                    ->label('Reason')
                    ->badge()
                    ->color(fn ($record): string => match($record->reason) {
                        'damage' => 'warning',
                        'theft' => 'danger',
                        'manual_correction' => 'info',
                        'other' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('previous_quantity')
                    ->label('Previous Stock')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('new_quantity')
                    ->label('New Stock')
                    ->sortable()
                    ->alignCenter(),

                IconColumn::make('approved')
                    ->label('Approved')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('user.name')
                    ->label('Created By')
                    ->sortable()
                    ->description(fn ($record): string => $record->created_at->format('M j, Y')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('adjustment_type')
                    ->options([
                        'increase' => 'Increase',
                        'decrease' => 'Decrease',
                    ]),

                Tables\Filters\SelectFilter::make('reason')
                    ->options([
                        'damage' => 'Damage',
                        'theft' => 'Theft',
                        'manual_correction' => 'Manual Correction',
                        'other' => 'Other',
                    ]),

                Tables\Filters\SelectFilter::make('approved')
                    ->options([
                        '1' => 'Approved',
                        '0' => 'Pending',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record): bool => !$record->approved && auth()->user()->can('stock-adjustment.edit')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record): bool => !$record->approved && auth()->user()->can('stock-adjustment.delete')),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => !$record->approved && auth()->user()->can('stock-adjustment.approve'))
                    ->action(function (StockAdjustment $record) {
                        $record->approve();
                        Notification::make()
                            ->title('Stock adjustment approved')
                            ->body('The stock adjustment has been approved and applied.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()->can('stock-adjustment.delete')),
                    Tables\Actions\BulkAction::make('approve')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (): bool => auth()->user()->can('stock-adjustment.approve'))
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if (!$record->approved) {
                                    $record->approve();
                                }
                            }
                            Notification::make()
                                ->title('Stock adjustments approved')
                                ->body('The selected stock adjustments have been approved and applied.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => auth()->user()->can('stock-adjustment.create')),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'view' => Pages\ViewStockAdjustment::route('/{record}'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->can('stock-adjustment.view');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('stock-adjustment.create');
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->can('stock-adjustment.edit') && !$record->approved;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->can('stock-adjustment.delete') && !$record->approved;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->can('stock-adjustment.delete');
    }
}