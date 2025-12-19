<?php

namespace App\Filament\Components;

use App\Models\Store;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class StoreSelector extends Select
{
    public static function make(?string $name = null): static
    {
        $static = parent::make($name ?? 'store_selector');
        
        $static
            ->label('Store')
            ->placeholder('Select a store')
            ->required()
            ->default(fn () => self::getDefaultStore())
            ->options(fn () => self::getStoreOptions())
            ->reactive()
            ->afterStateUpdated(function ($state) {
                if ($state) {
                    Session::put('active_store_id', $state);
                    Notification::make()
                        ->title('Store switched')
                        ->body('Switched to ' . Store::find($state)->name)
                        ->success()
                        ->send();
                }
            });

        return $static;
    }

    protected static function getDefaultStore(): ?int
    {
        // Try to get from session first
        if (Session::has('active_store_id')) {
            return Session::get('active_store_id');
        }
        
        // Then try user's default store
        if (Auth::check() && Auth::user()->store_id) {
            return Auth::user()->store_id;
        }
        
        // Finally, get the first active store
        return Store::active()->first()?->id;
    }

    protected static function getStoreOptions(): array
    {
        if (Auth::user() && Auth::user()->hasRole('super_admin')) {
            // Super admin can see all stores
            return Store::active()->pluck('name', 'id')->toArray();
        }
        
        if (Auth::check() && Auth::user()->store_id) {
            // User assigned to a specific store can only see that store
            $userStore = Store::find(Auth::user()->store_id);
            if ($userStore) {
                return [$userStore->id => $userStore->name];
            }
        }
        
        return [];
    }
}