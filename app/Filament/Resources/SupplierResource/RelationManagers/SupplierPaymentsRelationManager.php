<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Models\SupplierPayment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;

class SupplierPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $modelLabel = 'Supplier Payment';

    protected static ?string $pluralModelLabel = 'Supplier Payments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('amount')
                    ->numeric()
                    ->required()
                    ->step(0.01)
                    ->prefix('₹')
                    ->label('Amount'),
                
                Forms\Components\DatePicker::make('payment_date')
                    ->required()
                    ->label('Payment Date'),
                
                Forms\Components\Select::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'cheque' => 'Cheque',
                        'credit_card' => 'Credit Card',
                        'other' => 'Other',
                    ])
                    ->required()
                    ->label('Payment Method'),
                
                Forms\Components\TextInput::make('reference_number')
                    ->label('Reference Number')
                    ->maxLength(50),
                
                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Payment #')
                    ->sortable(),
                
                TextColumn::make('purchase.id')
                    ->label('Purchase #')
                    ->sortable()
                    ->formatStateUsing(fn ($record): ?string => $record->purchase_id ? '#' . $record->purchase_id : 'N/A'),
                
                TextColumn::make('formatted_amount')
                    ->label('Amount')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()->money('INR'),
                    ]),
                
                BadgeColumn::make('payment_method')
                    ->colors([
                        'cash' => 'success',
                        'bank_transfer' => 'info',
                        'cheque' => 'warning',
                        'credit_card' => 'primary',
                        'other' => 'secondary',
                    ])
                    ->formatStateUsing(fn ($record): string => $record->payment_method_label),
                
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable()
                    ->label('Payment Date'),
                
                TextColumn::make('reference_number')
                    ->searchable()
                    ->sortable()
                    ->label('Reference #'),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Payment')
                    ->url(fn (): string => route('filament.admin.resources.supplier-payments.create', [
                        'supplier_id' => $this->getOwnerRecord()->id
                    ])),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('supplier-payment.edit')),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('supplier-payment.delete')),
                
                ViewAction::make('view_purchase')
                    ->label('View Purchase')
                    ->icon('heroicon-o-shopping-bag')
                    ->color('info')
                    ->url(fn ($record): ?string => $record->purchase_id 
                        ? route('filament.admin.resources.purchases.edit', ['record' => $record->purchase_id]) 
                        : '#')
                    ->visible(fn ($record): bool => (bool) $record->purchase_id),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('supplier-payment.delete')),
                ]),
            ]);
    }

    public function canViewAny(): bool
    {
        return auth()->user()?->can('supplier-payment.view') ?? false;
    }

    public function canCreate(): bool
    {
        return auth()->user()?->can('supplier-payment.create') ?? false;
    }

    public function canEdit($record): bool
    {
        return auth()->user()?->can('supplier-payment.edit') ?? false;
    }

    public function canDelete($record): bool
    {
        return auth()->user()?->can('supplier-payment.delete') ?? false;
    }

    public function canDeleteAny(): bool
    {
        return auth()->user()?->can('supplier-payment.delete') ?? false;
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}