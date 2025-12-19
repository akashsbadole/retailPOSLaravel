<?php

namespace App\Filament\Widgets;

use App\Models\StockAdjustment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Filament\Actions\Action;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

class StockAdjustmentWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        // Get recent adjustments
        $recentAdjustments = StockAdjustment::with(['product', 'user'])
            ->latest()
            ->take(5)
            ->get();

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

            Stat::make('Net Stock Change', $netChange >= 0 ? '+' . $netChange : $netChange)
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

    public function getTableData(): array
    {
        return StockAdjustment::with(['product', 'user'])
            ->latest()
            ->take(5)
            ->map(function ($adjustment) {
                return [
                    'product' => $adjustment->product->name ?? 'N/A',
                    'quantity' => $adjustment->quantity_with_sign,
                    'reason' => $adjustment->formatted_reason,
                    'user' => $adjustment->user->name ?? 'N/A',
                    'date' => $adjustment->created_at->format('M j, Y g:i A'),
                    'status' => $adjustment->approved ? 'Approved' : 'Pending',
                ];
            })
            ->toArray();
    }

    protected function getTableHeaders(): array
    {
        return [
            'Product',
            'Quantity',
            'Reason',
            'User',
            'Date',
            'Status',
        ];
    }

    protected function getActions(): array
    {
        return [
            Action::make('create_adjustment')
                ->label('Create Adjustment')
                ->icon('heroicon-o-plus')
                ->url(route('filament.admin.resources.stock-adjustments.create'))
                ->visible(fn (): bool => auth()->user()->can('stock-adjustment.create')),

            Action::make('view_all')
                ->label('View All')
                ->icon('heroicon-o-eye')
                ->url(route('filament.admin.resources.stock-adjustments.index'))
                ->visible(fn (): bool => auth()->user()->can('stock-adjustment.view')),

            Action::make('pending_approvals')
                ->label('Pending Approvals')
                ->icon('heroicon-o-clock')
                ->url(route('filament.admin.resources.stock-adjustments.index', ['approved' => '0']))
                ->badge(StockAdjustment::pending()->count())
                ->color('warning')
                ->visible(fn (): bool => auth()->user()->can('stock-adjustment.approve') && StockAdjustment::pending()->exists()),
        ];
    }
}