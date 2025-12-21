<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Order;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions\Action;

class Pos extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-plus-circle';

    protected string $view = 'filament.pages.pos';

    protected static ?string $navigationLabel = 'Point Of Sale';

    public $orderId;

    public function getTitle(): string
    {
        return '';  
    }

    public function mount(): void
    {
        // Create a new order with default values
        $this->orderId = Order::create([
            'customer_id' => null,
            'total_price' => 0.00,
            'discount_amount' => 0.00,
            'status' => 'pending'
        ])->id;
    }
}
