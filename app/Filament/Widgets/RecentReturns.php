<?php

namespace App\Filament\Widgets;

use App\Models\SalesReturn;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RecentReturns extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesReturn::query()
                    ->with(['order.customer', 'user'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Return ID')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Order ID')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('order.customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(fn ($record) => 
                        $record->order->customer ? 
                        $record->order->customer->first_name . ' ' . $record->order->customer->last_name : 
                        'Walk-in Customer'
                    )
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('return_date')
                    ->date()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('reason')
                    ->formatStateUsing(fn ($state) => 
                        match($state) {
                            'damaged' => 'Damaged',
                            'defective' => 'Defective',
                            'wrong_item' => 'Wrong Item',
                            'not_needed' => 'Not Needed',
                            'customer_dissatisfaction' => 'Customer Dissatisfaction',
                            'expired' => 'Expired',
                            'other' => 'Other',
                            default => ucfirst($state),
                        }
                    )
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_refund_amount')
                    ->money('USD')
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'processed' => 'info',
                        'completed' => 'primary',
                    })
                    ->formatStateUsing(fn (string $state): string => 
                        match($state) {
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'processed' => 'Processed',
                            'completed' => 'Completed',
                            default => ucfirst($state),
                        }
                    ),
            ])
            ->actions([
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('filament.admin.resources.sales-returns.view', $record)),
                    
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->canBeApproved() && Auth::user()->can('approve_sales_return'))
                    ->action(function ($record) {
                        $record->approve();
                    }),
                    
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->canBeRejected() && Auth::user()->can('reject_sales_return'))
                    ->action(function ($record) {
                        $record->reject();
                    }),
            ])
            ->paginated([5, 10, 25])
            ->striped();
    }

    public static function canView(): bool
    {
        return Auth::user()->can('view_any_sales_return');
    }
}