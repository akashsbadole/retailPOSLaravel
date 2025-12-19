<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use App\Models\StockAdjustment;
use App\Observers\StockAdjustmentObserver;
use App\Models\Order;
use App\Models\Payment;
use App\Observers\CashierShiftObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Schema::defaultStringLength(191);
        
        FilamentAsset::register([
            Css::make('filament-print', asset('css/print.min.css')),
            Js::make('filament-print-js', asset('js/print.min.js'))
        ]);

        config()->set('excel.exports.csv.delimiter', ';');

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        StockAdjustment::observe(StockAdjustmentObserver::class);
        
        // Register CashierShiftObserver for Order and Payment models
        Order::observe(CashierShiftObserver::class);
        Payment::observe(CashierShiftObserver::class);
    }
}
