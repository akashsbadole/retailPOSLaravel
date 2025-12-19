<?php

namespace App\Filament\Widgets;

use App\Models\CashierShift;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CashierShiftWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = Auth::user();
        if (!$user) {
            return [];
        }

        $currentShift = $user->currentShift();
        
        if (!$currentShift) {
            return [
                Stat::make('Current Shift', 'No Active Shift')
                    ->description('Start a new shift to begin selling')
                    ->descriptionIcon('heroicon-m-clock')
                    ->color('warning')
                    ->chart([0, 0, 0, 0, 0, 0, 0]),
            ];
        }

        return [
            Stat::make('Current Shift', 'Active')
                ->description('Started ' . $currentShift->start_time->diffForHumans())
                ->descriptionIcon('heroicon-m-clock')
                ->color('success')
                ->chart([0, 0, 0, 0, 0, 0, 0]),

            Stat::make('Total Sales', '₹' . number_format($currentShift->total_sales, 2))
                ->description('From ' . $currentShift->orders()->count() . ' orders')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->chart($this->getSalesChart($currentShift)),

            Stat::make('Cash Collected', '₹' . number_format($currentShift->cash_collected, 2))
                ->description('Expected: ₹' . number_format($currentShift->expected_cash, 2))
                ->descriptionIcon('heroicon-m-cash')
                ->color($currentShift->cash_discrepancy === 0 ? 'success' : 'warning')
                ->chart([0, 0, 0, 0, 0, 0, 0]),

            Stat::make('Shift Duration', $currentShift->duration)
                ->description('Started at ' . $currentShift->start_time->format('g:i A'))
                ->descriptionIcon('heroicon-m-timer')
                ->color('info')
                ->chart([0, 0, 0, 0, 0, 0, 0]),
        ];
    }

    /**
     * Get sales chart data for the current shift
     */
    private function getSalesChart(CashierShift $shift): array
    {
        // Get hourly sales data for the current shift
        $orders = $shift->orders()
            ->selectRaw('HOUR(created_at) as hour, SUM(total_price) as total')
            ->where('created_at', '>=', $shift->start_time)
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Create a simple chart representation (last 7 hours)
        $chart = [];
        $currentHour = now()->hour;
        
        for ($i = 6; $i >= 0; $i--) {
            $hour = ($currentHour - $i + 24) % 24;
            $hourlyTotal = $orders->where('hour', $hour)->first()?->total ?? 0;
            $chart[] = (float) $hourlyTotal;
        }

        return $chart;
    }

    /**
     * Determine if the widget should be displayed.
     */
    public static function canView(): bool
    {
        return Auth::check() && Auth::user()->hasRole(['cashier', 'manager', 'admin']);
    }
}