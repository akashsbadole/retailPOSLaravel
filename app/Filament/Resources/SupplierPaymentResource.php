<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierPaymentResource\Pages;
use App\Models\SupplierPayment;
use App\Models\Supplier;
use App\Models\Purchase;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Support\Facades\Request;

class SupplierPaymentResource extends Resource
{
    protected static ?string $model = SupplierPayment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Purchasing';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment Information')
                    ->schema([
                        Select::make('supplier_id')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => Request::get('supplier_id'))
                            ->label('Supplier'),
                        
                        Select::make('purchase_id')
                            ->relationship('purchase', function (Builder $query) {
                                $supplierId = Request::get('supplier_id');
                                if ($supplierId) {
                                    $query->where('supplier_id', $supplierId);
                                }
                                return $query;
                            }, 'id')
                            ->searchable()
                            ->preload()
                            ->label('Purchase')
                            ->placeholder('Select a purchase'),
                        
                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->prefix('₹')
                            ->label('Amount'),
                        
                        DatePicker::make('payment_date')
                            ->required()
                            ->label('Payment Date')
                            ->default(now()),
                        
                        Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'bank_transfer' => 'Bank Transfer',
                                'cheque' => 'Cheque',
                                'credit_card' => 'Credit Card',
                                'other' => 'Other',
                            ])
                            ->required()
                            ->label('Payment Method'),
                        
                        TextInput::make('reference_number')
                            ->label('Reference Number')
                            ->maxLength(50),
                        
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Payment #')
                    ->sortable(),
                
                TextColumn::make('supplier.name')
                    ->searchable()
                    ->sortable()
                    ->label('Supplier'),
                
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
                Tables\Filters\SelectFilter::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->label('Supplier'),
                
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'bank_transfer' => 'Bank Transfer',
                        'cheque' => 'Cheque',
                        'credit_card' => 'Credit Card',
                        'other' => 'Other',
                    ])
                    ->label('Payment Method'),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('supplier-payment.edit')),
                
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->can('supplier-payment.delete')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteAction::make()
                        ->visible(fn (): bool => auth()->user()->can('supplier-payment.delete')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierPayments::route('/'),
            'create' => Pages\CreateSupplierPayment::route('/create'),
            'edit' => Pages\EditSupplierPayment::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('supplier-payment.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('supplier-payment.create') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('supplier-payment.edit') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('supplier-payment.delete') ?? false;
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->can('supplier-payment.delete') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }
}