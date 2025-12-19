<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SalesReturnsRelationManager extends RelationManager
{
    protected static string $relationship = 'salesReturns';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Return ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('return_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->formatStateUsing(fn ($state) => 
                        match($state) {
                            'damaged' => 'Damaged',
                            'defective' => 'Defective',
                            'wrong_item' => 'Wrong Item',
                            'not_needed' => 'Not Needed',
                            'customer_dissatisfaction' => 'Customer Dissatisfaction',
                            'expired' => 'Expired',
                            'other' => 'Other',
                            default => ucfirst($state),
                        }
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_refund_amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'processed' => 'info',
                        'completed' => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => 
                        match($state) {
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'processed' => 'Processed',
                            'completed' => 'Completed',
                            default => ucfirst($state),
                        }
                    ),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'processed' => 'Processed',
                        'completed' => 'Completed',
                    ]),

                Tables\Filters\SelectFilter::make('reason')
                    ->options([
                        'damaged' => 'Damaged',
                        'defective' => 'Defective',
                        'wrong_item' => 'Wrong Item',
                        'not_needed' => 'Not Needed',
                        'customer_dissatisfaction' => 'Customer Dissatisfaction',
                        'expired' => 'Expired',
                        'other' => 'Other',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create_return')
                    ->label('Create Return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->url(fn () => route('filament.admin.resources.sales-returns.create', ['order_id' => $this->getOwnerRecord()->id])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.admin.resources.sales-returns.view', $record)),
            ])
            ->bulkActions([
                //
            ]);
    }
}