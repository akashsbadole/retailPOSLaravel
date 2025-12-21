<?php

namespace App\Filament\Widgets;

use App\Models\StockAdjustment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockAdjustmentWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $today = now()->startOfDay();

        // Get adjustment statistics
        $totalAdjustments = StockAdjustment::count();
        $pendingAdjustments = StockAdjustment::pending()->count();
        $todayAdjustments = StockAdjustment::whereDate('created_at', '>=', $today)->count();

        // Get adjustments by reason
        $adjustmentsByReason = StockAdjustment::approved()
            ->selectRaw('reason, COUNT(*) as count, SUM(ABS(quantity)) as total_quantity')
            ->groupBy('reason')
            ->get()
            ->keyBy('reason');

        // Get net stock change
        $netIncrease = StockAdjustment::approved()->increase()->sum('quantity');
        $netDecrease = abs(StockAdjustment::approved()->decrease()->sum('quantity'));
        $netChange = $netIncrease - $netDecrease;

        return [
            Stat::make('Total Adjustments', $totalAdjustments)
                ->description($pendingAdjustments . ' pending approval')
                ->descriptionIcon($pendingAdjustments > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($pendingAdjustments > 0 ? 'warning' : 'success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Today\'s Adjustments', $todayAdjustments)
                ->description('Adjustments made today')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('Net Stock Change', $netChange >= 0 ? '+' . $netChange : (string) $netChange)
                ->description('Increase: ' . $netIncrease . ', Decrease: ' . $netDecrease)
                ->descriptionIcon($netChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netChange >= 0 ? 'success' : 'danger'),

            Stat::make('Damage Adjustments', $adjustmentsByReason['damage']->total_quantity ?? 0)
                ->description('Total damaged items')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),

            Stat::make('Theft Adjustments', $adjustmentsByReason['theft']->total_quantity ?? 0)
                ->description('Total stolen items')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color('danger'),

            Stat::make('Manual Corrections', $adjustmentsByReason['manual_correction']->total_quantity ?? 0)
                ->description('Total manual corrections')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}