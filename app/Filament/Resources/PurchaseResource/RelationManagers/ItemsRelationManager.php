<?php

namespace App\Filament\Resources\PurchaseResource\RelationManagers;

use App\Models\PurchaseItem;
use App\Models\Product;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DetachAction;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $modelLabel = 'Purchase Item';

    protected static ?string $pluralModelLabel = 'Purchase Items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->label('Product'),
                
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->label('Quantity'),
                
                Forms\Components\TextInput::make('unit_price')
                    ->numeric()
                    ->required()
                    ->step(0.01)
                    ->prefix('₹')
                    ->label('Unit Price'),
                
                Forms\Components\TextInput::make('discount_percentage')
                    ->numeric()
                    ->default(0)
                    ->step(0.01)
                    ->suffix('%')
                    ->label('Discount %'),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->searchable()
                    ->sortable()
                    ->label('Product'),
                
                TextColumn::make('quantity')
                    ->sortable()
                    ->label('Quantity'),
                
                TextColumn::make('formatted_unit_price')
                    ->label('Unit Price')
                    ->sortable(),
                
                TextColumn::make('discount_percentage')
                    ->label('Discount %')
                    ->formatStateUsing(fn ($record): string => number_format($record->discount_percentage, 2) . '%'),
                
                TextColumn::make('formatted_total_price')
                    ->label('Total Price')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Item')
                    ->url(fn (): string => route('filament.admin.resources.purchases.create')),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('purchase.edit')),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('purchase.delete')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('purchase.delete')),
                ]),
            ]);
    }

    public function canViewAny(): bool
    {
        return auth()->user()?->can('purchase.view') ?? false;
    }

    public function canCreate(): bool
    {
        return auth()->user()?->can('purchase.create') ?? false;
    }

    public function canEdit($record): bool
    {
        return auth()->user()?->can('purchase.edit') ?? false;
    }

    public function canDelete($record): bool
    {
        return auth()->user()?->can('purchase.delete') ?? false;
    }

    public function canDeleteAny(): bool
    {
        return auth()->user()?->can('purchase.delete') ?? false;
    }

    public function isReadOnly(): bool
    {
        $owner = $this->getOwnerRecord();
        
        // If purchase is received, items cannot be modified
        return $owner && $owner->status === 'received';
    }
}