<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Order;
use App\Models\CashierShift;
use App\Models\SalesReturn;
use Carbon\Carbon;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $currency_symbol = config('settings.currency_symbol');

        $totalOrdersLast30Days = Order::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $totalIncomeLast30Days = Order::where('created_at', '>=', Carbon::now()->subDays(30))->sum('total_price');
        $totalcustomersLast30Days = Customer::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        
        // Phase 3 Integration: Add shift data
        $activeShifts = CashierShift::open()->count();
        $closedShiftsToday = CashierShift::closed()->whereDate('end_time', today())->count();
        
        // Phase 3 Integration: Add return data
        $totalReturnsLast30Days = SalesReturn::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $totalRefundAmountLast30Days = SalesReturn::where('created_at', '>=', Carbon::now()->subDays(30))->sum('total_refund_amount');
        $pendingReturns = SalesReturn::pending()->count();

        return [
            Stat::make('Orders Count', $totalOrdersLast30Days)
                    ->description("Total orders in the last 30 days")
                    ->descriptionIcon('heroicon-o-inbox-stack', IconPosition::Before)
                    ->chart([1,5,10,50])
                    ->color('success'),

            Stat::make('Income', $currency_symbol.$totalIncomeLast30Days)
                    ->description("Total income in the last 30 days")
                    ->descriptionIcon('heroicon-o-banknotes', IconPosition::Before)
                    ->chart([1,5,30, 50])
                    ->color('success'),
            
            Stat::make('Customers Count', $totalcustomersLast30Days)
                    ->description("Last 30 days customers count")
                    ->descriptionIcon('heroicon-o-user-group', IconPosition::Before)
                    ->chart([1,5,15, 25])
                    ->color('success'),
                    
            // Phase 3 Integration: Shift Statistics
            Stat::make('Active Shifts', $activeShifts)
                    ->description("Currently active shifts")
                    ->descriptionIcon('heroicon-o-clock', IconPosition::Before)
                    ->chart([1,2,1])
                    ->color('info'),
                    
            Stat::make('Shifts Closed Today', $closedShiftsToday)
                    ->description("Shifts closed today")
                    ->descriptionIcon('heroicon-o-check-circle', IconPosition::Before)
                    ->chart([1,1,2])
                    ->color('success'),
                    
            // Phase 3 Integration: Return Statistics
            Stat::make('Total Returns', $totalReturnsLast30Days)
                    ->description("Returns in the last 30 days")
                    ->descriptionIcon('heroicon-o-arrow-uturn-left', IconPosition::Before)
                    ->chart([7, 2, 10, 3, 15, 4])
                    ->color('warning'),
                    
            Stat::make('Pending Returns', $pendingReturns)
                    ->description("Returns awaiting approval")
                    ->descriptionIcon('heroicon-o-exclamation-triangle', IconPosition::Before)
                    ->chart([2, 3, 1, 4, 2, 3])
                    ->color('danger'),
                    
            Stat::make('Refund Amount', $currency_symbol . number_format($totalRefundAmountLast30Days, 2))
                    ->description("Total refunds in the last 30 days")
                    ->descriptionIcon('heroicon-o-receipt-refund', IconPosition::Before)
                    ->chart([5, 10, 15, 20, 25, 30])
                    ->color('info'),
        ];
    }
}
