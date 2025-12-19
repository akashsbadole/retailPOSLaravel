<?php

namespace App\Observers;

use App\Models\StoreCashierShift;
use Illuminate\Support\Facades\Log;

class StoreCashierShiftObserver
{
    /**
     * Handle the StoreCashierShift "created" event.
     */
    public function created(StoreCashierShift $shift): void
    {
        Log::info('Store cashier shift created', [
            'shift_id' => $shift->id,
            'store_id' => $shift->store_id,
            'user_id' => $shift->user_id,
            'start_time' => $shift->start_time,
        ]);
    }

    /**
     * Handle the StoreCashierShift "updated" event.
     */
    public function updated(StoreCashierShift $shift): void
    {
        Log::info('Store cashier shift updated', [
            'shift_id' => $shift->id,
            'store_id' => $shift->store_id,
            'user_id' => $shift->user_id,
            'changes' => $shift->getDirty(),
        ]);
    }

    /**
     * Handle the StoreCashierShift "deleted" event.
     */
    public function deleted(StoreCashierShift $shift): void
    {
        Log::warning('Store cashier shift deleted', [
            'shift_id' => $shift->id,
            'store_id' => $shift->store_id,
            'user_id' => $shift->user_id,
        ]);
    }
}