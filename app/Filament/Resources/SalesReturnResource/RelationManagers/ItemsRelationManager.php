<?php

namespace App\Filament\Resources\SalesReturnResource\RelationManagers;

use App\Services\SalesReturnService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('order_item_id')
                    ->label('Product')
                    ->options(function () {
                        $orderId = $this->getOwnerRecord()->order_id;
                        return \App\Models\OrderItem::where('order_id', $orderId)
                            ->with('product')
                            ->get()
                            ->mapWithKeys(function ($item) {
                                $alreadyReturned = \App\Models\SalesReturnItem::where('order_item_id', $item->id)
                                    ->where('sales_return_id', '!=', $this->getOwnerRecord()->id)
                                    ->sum('quantity');
                                $availableQuantity = $item->quantity - $alreadyReturned;
                                
                                if ($availableQuantity <= 0) return [];
                                
                                return [
                                    $item->id => "{$item->name} (Available: {$availableQuantity})"
                                ];
                            });
                    })
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('quantity', 1)),

                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->minValue(1)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        $orderItemId = $get('order_item_id');
                        if ($orderItemId) {
                            $orderItem = \App\Models\OrderItem::find($orderItemId);
                            if ($orderItem) {
                                $salesReturnService = app(SalesReturnService::class);
                                $refundAmount = $salesReturnService->calculateRefundAmount($orderItem, $state);
                                $set('refund_amount', $refundAmount);
                            }
                        }
                    })
                    ->label('Quantity'),

                Forms\Components\TextInput::make('refund_amount')
                    ->numeric()
                    ->prefix('$')
                    ->step(0.01)
                    ->required()
                    ->label('Refund Amount'),

                Forms\Components\Textarea::make('reason')
                    ->rows(2)
                    ->label('Item Reason'),

                Forms\Components\Checkbox::make('restocked')
                    ->default(false)
                    ->label('Restocked'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Product')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('refund_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_refund_amount')
                    ->label('Unit Price')
                    ->money('USD')
                    ->getStateUsing(fn ($record) => $record->unit_refund_amount)
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_price')
                    ->label('Original Price')
                    ->money('USD')
                    ->getStateUsing(fn ($record) => $record->original_price)
                    ->sortable(),

                Tables\Columns\CheckboxColumn::make('restocked')
                    ->label('Restocked'),

                Tables\Columns\TextColumn::make('reason')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('restock')
                    ->label('Restock')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => !$record->restocked && $this->getOwnerRecord()->isApproved())
                    ->action(function ($record) {
                        $salesReturnService = app(SalesReturnService::class);
                        $salesReturnService->processStockAdjustments($this->getOwnerRecord());
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}