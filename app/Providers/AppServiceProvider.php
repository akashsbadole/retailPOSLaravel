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
use App\Models\Purchase;
use App\Observers\PurchaseObserver;
use App\Models\Store;
use App\Observers\StoreObserver;
use App\Models\StoreStock;
use App\Observers\StoreStockObserver;
use App\Models\StoreOrder;
use App\Observers\StoreOrderObserver;
use App\Models\StoreCashierShift;
use App\Observers\StoreCashierShiftObserver;

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
        
        // Register PurchaseObserver for Purchase model
        \App\Models\Purchase::observe(PurchaseObserver::class);
        
        // Register Store observers
        Store::observe(StoreObserver::class);
        StoreStock::observe(StoreStockObserver::class);
        StoreOrder::observe(StoreOrderObserver::class);
        StoreCashierShift::observe(StoreCashierShiftObserver::class);
    }
}
