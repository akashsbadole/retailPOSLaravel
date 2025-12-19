<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\SupplierResource\RelationManagers;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Supplier Name'),
                
                TextInput::make('contact_person')
                    ->maxLength(255)
                    ->label('Contact Person'),
                
                TextInput::make('email')
                    ->email()
                    ->maxLength(255)
                    ->label('Email'),
                
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(20)
                    ->label('Phone'),
                
                Textarea::make('address')
                    ->rows(3)
                    ->label('Address'),
                
                TextInput::make('gst_number')
                    ->maxLength(20)
                    ->label('GST Number'),
                
                Toggle::make('status')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Supplier Name'),
                
                TextColumn::make('contact_person')
                    ->searchable()
                    ->sortable()
                    ->label('Contact Person'),
                
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->label('Email'),
                
                TextColumn::make('phone')
                    ->searchable()
                    ->sortable()
                    ->label('Phone'),
                
                TextColumn::make('gst_number')
                    ->searchable()
                    ->sortable()
                    ->label('GST Number'),
                
                TextColumn::make('formatted_total_purchases')
                    ->label('Total Purchases')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('INR'),
                    ]),
                
                TextColumn::make('formatted_total_paid')
                    ->label('Total Paid')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('INR'),
                    ]),
                
                TextColumn::make('formatted_total_due')
                    ->label('Total Due')
                    ->sortable()
                    ->color('danger')
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
                    ->visible(fn (): bool => auth()->user()->can('supplier.edit')),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('supplier.delete')),
                
                Action::make('view_purchases')
                    ->label('View Purchases')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info')
                    ->url(fn ($record): string => route('filament.admin.resources.purchases.index', ['supplier_id' => $record->id]))
                    ->visible(fn (): bool => auth()->user()->can('purchase.view')),
                
                Action::make('add_payment')
                    ->label('Add Payment')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->url(fn ($record): string => route('filament.admin.resources.supplier-payments.create', ['supplier_id' => $record->id]))
                    ->visible(fn (): bool => auth()->user()->can('supplier-payment.create') && $record->total_due > 0),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('supplier.delete')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('supplier.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('supplier.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('supplier.edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('supplier.delete') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('supplier.delete') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SupplierPaymentsRelationManager::class,
        ];
    }
}