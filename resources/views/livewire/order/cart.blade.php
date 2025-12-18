<div class="">

    @if (session()->has('error'))
        <p class="text-red-500">{{ session('error') }}</p>
    @endif
    <div class="overflow-x-auto md:overflow-x-none">
        <table class="min-w-[600px] min-w-full border border-gray-300">
            <thead>
                <tr class="bg-gray-200">
                    <th class="px-2 py-1 border border-gray-400 text-left w-3/5 dark:text-gray-800">Item</th>
                    <th class="px-2 py-1 border border-gray-400 text-center w-1/6 dark:text-gray-800">Rate</th>
                    <th class="px-2 py-1 border border-gray-400 text-center w-1/6 dark:text-gray-800">Tax(%)</th>
                    <th class="px-2 py-1 border border-gray-400 text-center w-1/6 dark:text-gray-800">Discount(%)</th>
                    <th class="px-2 py-1 border border-gray-400 text-center w-1/6 dark:text-gray-800">Quantity</th>
                    <th class="px-2 py-1 border border-gray-400 text-center w-1/6 dark:text-gray-800">Total</th>
                </tr>
            </thead>
            <tbody>
            @if ( !is_countable($cartItems) || count($cartItems) < 1)
                <tr class="min-h-32"><td class="p-4">Add Items.</td></tr>
            @else
                @php 
                    $total_price = 0;
                    $total_tax = [];
                    $grand_total = 0;
                    $total_item_discount = 0;
                @endphp
                
                @foreach ($cartItems as $item)
                @php
                    $tax = $item->tax;
                    $item_total = $item->price * $item->quantity;
                    $discount_amount = ($item_total * $item->discount_percentage) / 100;
                    $item_total_after_discount = $item_total - $discount_amount;
                    $gst_amount = ($item_total_after_discount * $tax) / 100;
                    $item_total_with_gst = $item_total_after_discount + $gst_amount;
                    $total_price += $item_total_after_discount;
                    $total_tax[$tax] = ($total_tax[$tax] ?? 0) + $gst_amount;
                    $grand_total += $item_total_with_gst;
                    $item->item_total_with_gst = $item_total_with_gst;
                    $total_item_discount += $discount_amount;
                @endphp
                    <livewire:order.cart-item :cartItem="$item" :currency_symbol="$currency_symbol" :order-id="$orderId" :key="$item->id" />
                @endforeach
                
                @if(isset($total_item_discount) && $total_item_discount > 0)
                    <tr class="border-gray-400 border">
                        <td colspan="5" class="px-4 py-2 border-r text-right font-semibold">Item Discounts</td>
                        <td class="px-4 py-2 text-center font-semibold text-green-600">-{{ $currency_symbol }}{{ number_format($total_item_discount, 2) }}</td>
                    </tr>
                @endif

                <tr class="border-gray-400 border">
                    <td colspan="5" class="px-4 py-2 border-r text-right font-semibold">Subtotal</td>
                    <td class="px-4 py-2 text-center font-semibold">{{ $currency_symbol }}{{ number_format($total_price, 2) }}</td>
                </tr>

                <tr class="border-gray-400 border">
                    <td colspan="5" class="px-4 py-2 border-r text-right font-semibold">Bill Discount</td>
                    <td class="px-4 py-2 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <input type="number" min="0" step="0.01" wire:model.live.debounce.250ms="billDiscount" class="p-1 bg-white text-center w-24 text-sm text-gray-900 border border-gray-300 rounded-md bg-gray-50 focus:ring-blue-500 focus:border-blue-500" />
                            <span class="text-xs">{{ $currency_symbol }}</span>
                        </div>
                    </td>
                </tr>

                @foreach ($total_tax as $rate => $amount)
                    <tr class="border-gray-400 border">
                        <td colspan="5" class="px-4 py-2 border-r text-right font-semibold">VAT/GST @ {{ $rate }}%</td>
                        <td class="px-4 py-2 text-center font-semibold">{{ $currency_symbol }}{{ number_format($amount, 2) }}</td>
                    </tr>
                @endforeach

                <?php
                    $final_total = $grand_total - $billDiscount;
                    if($final_total < 0) $final_total = 0;
                ?>

                <tr class="bg-gray-100 border-gray-400 border">
                    <td colspan="5" class="px-4 py-2 border-r text-right font-bold">Grand Total</td>
                    <td class="px-4 py-2 text-center font-bold">{{ $currency_symbol }}{{ number_format($final_total, 2) }}</td>
                </tr>

                <tr class="border-gray-400 border">
                    <td colspan="5" class="px-4 py-2 border-r text-right font-semibold">Payment Method</td>
                    <td class="px-4 py-2 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <select wire:model.live="paymentMethod" class="p-1 bg-white text-center w-24 text-sm text-gray-900 border border-gray-300 rounded-md bg-gray-50 focus:ring-blue-500 focus:border-blue-500">
                                @if(config('settings.payment_cash_enabled', '1') === '1')
                                    <option value="cash">Cash</option>
                                @endif
                                @if(config('settings.payment_card_enabled', '1') === '1')
                                    <option value="card">Card</option>
                                @endif
                                @if(config('settings.payment_upi_enabled', '1') === '1')
                                    <option value="upi">UPI</option>
                                @endif
                            </select>
                        </div>
                    </td>
                </tr>

            @endif
            </tbody>
        </table>
    </div>

    <button wire:click="checkout" class="bg-green-500 rounded  text-white px-4 py-2 mt-3">
        <svg class="size-6 h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>              
    </button>

</div>
