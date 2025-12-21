<div class="">

    @if (session()->has('error'))
        <p class="text-red-500 bg-red-50 p-3 rounded-lg border border-red-200">{{ session('error') }}</p>
    @endif
    <div class="overflow-x-auto md:overflow-x-none">
        <table class="min-w-[600px] min-w-full border-2 border-blue-300">
            <thead>
                <tr class="bg-gradient-to-r from-blue-100 to-blue-50 border-b-2 border-blue-300">
                    <th class="px-2 py-2 border border-blue-300 text-left w-3/5 text-blue-900 font-semibold">Item</th>
                    <th class="px-2 py-2 border border-blue-300 text-center w-1/6 text-blue-900 font-semibold">Rate</th>
                    <th class="px-2 py-2 border border-blue-300 text-center w-1/6 text-blue-900 font-semibold">Tax(%)</th>
                    <th class="px-2 py-2 border border-blue-300 text-center w-1/6 text-blue-900 font-semibold">Quantity</th>
                    <th class="px-2 py-2 border border-blue-300 text-center w-1/6 text-blue-900 font-semibold">Total</th>
                </tr>
            </thead>
            <tbody>
            @if ( !is_countable($cartItems) || count($cartItems) < 1)
                <tr class="min-h-32"><td class="p-4 text-gray-600">Add Items.</td></tr>
            @else
                @php 
                    $total_price = 0;
                    $total_tax = [];
                    $grand_total = 0;
                @endphp

                @foreach($cartItems as $item)
                    @php 
                        $tax = $item->tax;
                        $item_total = $item->price * $item->quantity;
                        $tax_amount = ($item_total * $tax) / 100;
                        $item_total_with_tax = $item_total + $tax_amount;
                        $total_price += $item_total;
                        $total_tax[$tax] = ($total_tax[$tax] ?? 0) + $tax_amount;
                        $grand_total += $item_total_with_tax;
                    @endphp
                    <livewire:cart-item :cartItem="$item" :currency_symbol="$currency_symbol" :key="$item->id" />
                @endforeach
                
                <tr class="border-blue-300 border-t-2 bg-gray-50">
                    <td colspan="3" class="px-4 py-2 border-r border-blue-300 text-right font-semibold text-gray-900">Subtotal</td>
                    <td colspan="2" class="px-4 py-2 text-center font-semibold text-gray-900">{{ $currency_symbol }}{{ number_format($total_price, 2) }}</td>
                </tr>

                @foreach ($total_tax as $rate => $amount)
                    <tr class="border-blue-300 border-t">
                        <td colspan="3" class="px-4 py-2 border-r border-blue-300 text-right font-semibold text-gray-900">VAT/GST @ {{ $rate }}%</td>
                        <td colspan="2" class="px-4 py-2 text-center font-semibold text-gray-900">{{ $currency_symbol }}{{ number_format($amount, 2) }}</td>
                    </tr>
                @endforeach

                <tr class="bg-gradient-to-r from-yellow-100 to-yellow-50 border-blue-300 border-t-2 border-b-2">
                    <td colspan="3" class="px-4 py-2 border-r border-blue-300 text-right font-bold text-gray-900">Grand Total</td>
                    <td colspan="2" class="px-4 py-2 text-center font-bold text-blue-600">{{ $currency_symbol }}{{ number_format($grand_total, 2) }}</td>
                </tr>

                @if(auth()->user()->can('discount.full') || auth()->user()->can('discount.limited'))
                <tr class="border-blue-300 border-t">
                    <td colspan="3" class="px-4 py-2 border-r border-blue-300 text-right font-semibold text-gray-900">Discount</td>
                    <td colspan="2" class="px-4 py-2 text-center">
                        <div class="flex items-center gap-2">
                            <input type="number"
                                   wire:model.live="discountPercentage"
                                   min="0"
                                   max="{{ auth()->user()->getMaxDiscountPercentage() }}"
                                   class="p-1 text-center w-20 text-sm text-gray-900 border-2 border-blue-300 rounded-md bg-blue-50 focus:ring-blue-500 focus:border-blue-500" />
                            <span class="text-gray-700">%</span>
                            @if($discountAmount > 0)
                                <span class="text-red-600 font-semibold">-{{ $currency_symbol }}{{ number_format($discountAmount, 2) }}</span>
                            @endif
                        </div>
                    </td>
                </tr>
                @endif

                @if($discountAmount > 0)
                <tr class="bg-gradient-to-r from-red-100 to-red-50 border-blue-300 border-t-2">
                    <td colspan="3" class="px-4 py-2 border-r border-blue-300 text-right font-bold text-gray-900">Total After Discount</td>
                    <td colspan="2" class="px-4 py-2 text-center font-bold text-red-600">{{ $currency_symbol }}{{ number_format($grand_total - $discountAmount, 2) }}</td>
                </tr>
                @endif
  
            @endif
            </tbody>
        </table>
    </div>
    <button wire:click="checkout" wire:loading.attr="disabled"  class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 rounded text-white px-4 py-2 mt-3 font-medium transition-all shadow-md hover:shadow-lg">
        <span wire:loading.remove wire:target='checkout'>Save</span>
        <span wire:loading wire:target='checkout' class="w-4 h-4 border-2 border-t-yellow-300 border-transparent rounded-full animate-spin"></span>
    </button>
</div>
