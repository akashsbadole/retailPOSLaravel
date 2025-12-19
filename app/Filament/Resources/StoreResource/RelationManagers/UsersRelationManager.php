<?php

namespace App\Filament\Resources\StoreResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\EditAction;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Assigned Users';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Name'),
                
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->label('Email'),
                
                TextColumn::make('phone')
                    ->searchable()
                    ->sortable()
                    ->label('Phone'),
                
                IconColumn::make('is_admin')
                    ->boolean()
                    ->trueLabel('Admin')
                    ->falseLabel('User')
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-user')
                    ->color(fn ($record): string => $record->is_admin ? 'warning' : 'primary'),
                
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->visible(fn (): bool => auth()->user()->can('user.edit')),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()->can('user.edit')),
                
                DetachAction::make()
                    ->visible(fn (): bool => auth()->user()->can('user.edit')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DetachAction::make()
                        ->visible(fn (): bool => auth()->user()->can('user.edit')),
                ]),
            ]);
    }
}