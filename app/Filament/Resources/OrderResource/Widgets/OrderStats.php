<?php

namespace App\Filament\Resources\OrderResource\Widgets;

use App\Models\Order;
use App\Models\Payment;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use App\Filament\Resources\OrderResource\Pages\ListOrders;

class OrderStats extends BaseWidget
{

    use InteractsWithPageTable;

    protected function getTablePage(): string
    {
        return ListOrders::class;
    }

    protected function getStats(): array
    {
        $currency_symbol = config('settings.currency_symbol');
        
        // Get payment method stats
        $query = $this->getPageTableQuery();
        $orderIds = $query->pluck('id');
        
        $cashPayments = Payment::whereIn('order_id', $orderIds)->where('payment_method', 'cash')->sum('amount');
        $cardPayments = Payment::whereIn('order_id', $orderIds)->where('payment_method', 'card')->sum('amount');
        $upiPayments = Payment::whereIn('order_id', $orderIds)->where('payment_method', 'upi')->sum('amount');

        return [
            Stat::make('Total orders', $query->count())
                    ->description("Total orders")
                    ->descriptionIcon('heroicon-o-inbox-stack', IconPosition::Before)
                    ->chart([1,5,10,50])
                    ->color('success'),
            Stat::make('Income', $currency_symbol.$query->sum('total_price'))
                    ->description("Total income")
                    ->descriptionIcon('heroicon-o-banknotes', IconPosition::Before)
                    ->chart([1,5,30, 50])
                    ->color('success'),
            Stat::make('Cash Payments', $currency_symbol.number_format($cashPayments, 2))
                    ->description("Cash transactions")
                    ->descriptionIcon('heroicon-o-banknotes', IconPosition::Before)
                    ->color('info'),
            Stat::make('Card Payments', $currency_symbol.number_format($cardPayments, 2))
                    ->description("Card transactions")
                    ->descriptionIcon('heroicon-o-credit-card', IconPosition::Before)
                    ->color('warning'),
            Stat::make('UPI Payments', $currency_symbol.number_format($upiPayments, 2))
                    ->description("UPI transactions")
                    ->descriptionIcon('heroicon-o-device-phone-mobile', IconPosition::Before)
                    ->color('primary'),
        ];
    }
}
