<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StoreResource\Pages;
use App\Filament\Resources\StoreResource\RelationManagers;
use App\Models\Store;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\KeyValue;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = 'Store Management';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Store Name'),
                
                TextInput::make('code')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true)
                    ->label('Store Code'),
                
                Textarea::make('address')
                    ->rows(3)
                    ->label('Address'),
                
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(20)
                    ->label('Phone'),
                
                TextInput::make('email')
                    ->email()
                    ->maxLength(255)
                    ->label('Email'),
                
                TextInput::make('tax_number')
                    ->maxLength(20)
                    ->label('Tax Number'),
                
                Toggle::make('status')
                    ->label('Active')
                    ->default(true),
                
                KeyValue::make('settings')
                    ->label('Store Settings')
                    ->keyLabel('Setting')
                    ->valueLabel('Value')
                    ->addable()
                    ->editable()
                    ->deletable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Store Name'),
                
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->label('Store Code'),
                
                TextColumn::make('phone')
                    ->searchable()
                    ->sortable()
                    ->label('Phone'),
                
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->label('Email'),
                
                TextColumn::make('total_orders')
                    ->label('Total Orders')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make(),
                    ]),
                
                TextColumn::make('formatted_total_sales')
                    ->label('Total Sales')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('INR'),
                    ]),
                
                TextColumn::make('total_products')
                    ->label('Products in Stock')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make(),
                    ]),
                
                TextColumn::make('formatted_total_stock_value')
                    ->label('Stock Value')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('INR'),
                    ]),
                
                IconColumn::make('status')
                    ->boolean()
                    ->trueLabel('Active')
                    ->falseLabel('Inactive')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->color(fn ($record): string => $record->status ? 'success' : 'danger'),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('active')
                    ->query(fn (Builder $query): Builder => $query->where('status', true))
                    ->label('Active'),
                
                Tables\Filters\Filter::make('inactive')
                    ->query(fn (Builder $query): Builder => $query->where('status', false))
                    ->label('Inactive'),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store.edit')),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('store.delete')),
                
                Action::make('view_inventory')
                    ->label('View Inventory')
                    ->icon('heroicon-o-cube')
                    ->color('info')
                    ->url(fn ($record): string => route('filament.admin.resources.store-stocks.index', ['store_id' => $record->id]))
                    ->visible(fn (): bool => auth()->user()->can('store-stock.view')),
                
                Action::make('view_orders')
                    ->label('View Orders')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('success')
                    ->url(fn ($record): string => route('filament.admin.resources.store-orders.index', ['store_id' => $record->id]))
                    ->visible(fn (): bool => auth()->user()->can('store-order.view')),
                
                Action::make('view_shifts')
                    ->label('View Shifts')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->url(fn ($record): string => route('filament.admin.resources.store-cashier-shifts.index', ['store_id' => $record->id]))
                    ->visible(fn (): bool => auth()->user()->can('store-cashier-shift.view')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('store.delete')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStores::route('/'),
            'create' => Pages\CreateStore::route('/create'),
            'edit' => Pages\EditStore::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('store.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('store.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('store.edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('store.delete') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('store.delete') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
            RelationManagers\StoreStocksRelationManager::class,
            RelationManagers\StoreOrdersRelationManager::class,
            RelationManagers\StorePaymentsRelationManager::class,
            RelationManagers\StoreCashierShiftsRelationManager::class,
        ];
    }
}