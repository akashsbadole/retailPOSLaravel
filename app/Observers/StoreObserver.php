<?php

namespace App\Observers;

use App\Models\Store;
use Illuminate\Support\Facades\Log;

class StoreObserver
{
    /**
     * Handle the Store "created" event.
     */
    public function created(Store $store): void
    {
        Log::info('Store created', [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'store_code' => $store->code,
        ]);
    }

    /**
     * Handle the Store "updated" event.
     */
    public function updated(Store $store): void
    {
        Log::info('Store updated', [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'changes' => $store->getDirty(),
        ]);
    }

    /**
     * Handle the Store "deleted" event.
     */
    public function deleted(Store $store): void
    {
        Log::warning('Store deleted', [
            'store_id' => $store->id,
            'store_name' => $store->name,
        ]);
    }
}