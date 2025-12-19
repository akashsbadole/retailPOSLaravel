<?php

namespace App\Filament\Widgets;

use App\Models\SalesReturn;
use App\Services\SalesReturnService;
use Carbon\Carbon;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ReturnOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $currency_symbol = config('settings.currency_symbol', '$');
        $salesReturnService = app(SalesReturnService::class);

        $totalReturnsLast30Days = SalesReturn::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $pendingReturns = SalesReturn::pending()->count();
        $totalRefundAmountLast30Days = SalesReturn::where('created_at', '>=', Carbon::now()->subDays(30))->sum('total_refund_amount');
        $processedReturnsLast30Days = SalesReturn::where('created_at', '>=', Carbon::now()->subDays(30))->processed()->count();

        return [
            Stat::make('Total Returns', $totalReturnsLast30Days)
                    ->description("Returns in last 30 days")
                    ->descriptionIcon('heroicon-o-arrow-uturn-left', IconPosition::Before)
                    ->chart([7, 2, 10, 3, 15, 4, 17])
                    ->color('warning'),

            Stat::make('Pending Returns', $pendingReturns)
                    ->description("Returns awaiting approval")
                    ->descriptionIcon('heroicon-o-clock', IconPosition::Before)
                    ->chart([2, 3, 1, 4, 2, 3, 1])
                    ->color('danger'),

            Stat::make('Refund Amount', $currency_symbol . number_format($totalRefundAmountLast30Days, 2))
                    ->description("Total refunds in last 30 days")
                    ->descriptionIcon('heroicon-o-banknotes', IconPosition::Before)
                    ->chart([5, 10, 15, 20, 25, 30, 35])
                    ->color('info'),
            
            Stat::make('Processed Returns', $processedReturnsLast30Days)
                    ->description("Returns processed in last 30 days")
                    ->descriptionIcon('heroicon-o-check-circle', IconPosition::Before)
                    ->chart([3, 5, 7, 9, 11, 13, 15])
                    ->color('success'),       
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    public static function canView(): bool
    {
        return Auth::user()->can('view_any_sales_return');
    }
}