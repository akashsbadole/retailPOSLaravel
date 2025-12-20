<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseResource\Pages;
use App\Filament\Resources\PurchaseResource\RelationManagers;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Product;
use Filament\Forms;
use App\Filament\Actions\PurchaseImportAction;
use App\Filament\Actions\PurchaseExportAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|\UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Purchase Information')
                    ->schema([
                        Select::make('supplier_id')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->label('Supplier'),
                        
                        DatePicker::make('purchase_date')
                            ->required()
                            ->label('Purchase Date')
                            ->default(now()),
                        
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->columns(2),
                
                Section::make('Items')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->label('Product'),
                                
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->label('Quantity'),
                                
                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->required()
                                    ->step(0.01)
                                    ->prefix('₹')
                                    ->label('Unit Price'),
                                
                                TextInput::make('discount_percentage')
                                    ->numeric()
                                    ->default(0)
                                    ->step(0.01)
                                    ->suffix('%')
                                    ->label('Discount %'),
                            ])
                            ->columns(4)
                            ->collapsed()
                            ->itemLabel(fn ($state): ?string => 'Item ' . (array_search($state, $this->getRecord()->items->toArray()) + 1))
                            ->addActionLabel('Add Item')
                            ->collapsible()
                            ->cloneable()
                            ->orderColumn('sort'),
                    ]),
                
                Section::make('Summary')
                    ->schema([
                        Placeholder::make('total_amount')
                            ->label('Total Amount')
                            ->content(fn ($get): string => '₹' . number_format($get('total_amount', 2), 2)),
                        
                        Placeholder::make('discount_amount')
                            ->label('Discount Amount')
                            ->content(fn ($get): string => '₹' . number_format($get('discount_amount', 2), 2)),
                        
                        Placeholder::make('tax_amount')
                            ->label('Tax Amount')
                            ->content(fn ($get): string => '₹' . number_format($get('tax_amount', 2), 2)),
                        
                        Placeholder::make('final_amount')
                            ->label('Final Amount')
                            ->content(fn ($get): string => '₹' . number_format($get('final_amount', 2), 2)),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('PO #')
                    ->sortable(),
                
                TextColumn::make('supplier.name')
                    ->searchable()
                    ->sortable()
                    ->label('Supplier'),
                
                TextColumn::make('purchase_date')
                    ->date()
                    ->sortable()
                    ->label('Purchase Date'),
                
                TextColumn::make('formatted_final_amount')
                    ->label('Total Amount')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('INR'),
                    ]),
                
                BadgeColumn::make('status')
                    ->colors([
                        'pending' => 'warning',
                        'received' => 'success',
                        'cancelled' => 'danger',
                    ])
                    ->label('Status'),
                
                BadgeColumn::make('payment_status')
                    ->colors([
                        'unpaid' => 'danger',
                        'partial' => 'warning',
                        'paid' => 'success',
                    ])
                    ->label('Payment Status'),
                
                TextColumn::make('formatted_remaining_amount')
                    ->label('Remaining')
                    ->sortable()
                    ->color('danger')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('INR'),
                    ]),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label('Supplier'),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'received' => 'Received',
                        'cancelled' => 'Cancelled',
                    ])
                    ->label('Status'),
                
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'partial' => 'Partially Paid',
                        'paid' => 'Paid',
                    ])
                    ->label('Payment Status'),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('purchase.edit')),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('purchase.delete')),
                
                Action::make('receive_purchase')
                    ->label('Receive Purchase')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Purchase $record) {
                        $record->update(['status' => 'received']);
                        
                        // Update stock for each item
                        foreach ($record->items as $item) {
                            $product = $item->product;
                            if ($product) {
                                $product->addStock($item->quantity);
                            }
                        }
                        
                        Notification::make()
                            ->title('Purchase Received')
                            ->body('Purchase has been marked as received and stock has been updated.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Purchase $record): bool => 
                        auth()->user()->can('purchase.edit') && $record->status === 'pending'
                    ),
                
                Action::make('add_payment')
                    ->label('Add Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->url(fn (Purchase $record): string => 
                        route('filament.admin.resources.supplier-payments.create', [
                            'supplier_id' => $record->supplier_id,
                            'purchase_id' => $record->id
                        ])
                    )
                    ->visible(fn (Purchase $record): bool => 
                        auth()->user()->can('supplier-payment.create') && $record->remaining_amount > 0
                    ),
            ])
            ->toolbarActions([
                PurchaseImportAction::make(),
                PurchaseExportAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('purchase.delete')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchases::route('/'),
            'create' => Pages\CreatePurchase::route('/create'),
            'edit' => Pages\EditPurchase::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('purchase.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('purchase.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('purchase.edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('purchase.delete') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('purchase.delete') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }
}