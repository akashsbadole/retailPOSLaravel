<x-filament-panels::page>

    <div class="flex flex-col md:flex-row gap-2">
        
        <div class="w-full md:w-1/3 md:order-2">
            <livewire:order.product-search :order-id="$orderId" />
        </div>

        <div class="w-full md:w-2/3 md:order-1">
            <div class="flex flex-col md:flex-row gap-4 pb-4">
                <div class="w-full md:w-1/2">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Barcode Scanner</h3>
                            <button
                                onclick="toggleScannerHelp()"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                title="Scanner Help"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </button>
                        </div>
                        <livewire:order.barcode-scan :order-id="$orderId" />
                        
                        <!-- Scanner Help -->
                        <div id="scanner-help" class="hidden mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-md border border-blue-200 dark:border-blue-800">
                            <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">Scanner Instructions:</h4>
                            <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                                <li>• Scan barcode using scanner device</li>
                                <li>• Or type barcode manually and press Enter</li>
                                <li>• Use "Manual Entry" for keyboard input</li>
                                <li>• Select barcode format from dropdown</li>
                                <li>• Green flash = successful scan</li>
                                <li>• Red flash = error (check product exists)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="w-full md:w-1/2">
                    <livewire:order.customer-search :order-id="$orderId" />
                </div>
            </div>
            <livewire:order.cart :order-id="$orderId" />
        </div>

    </div>

    <livewire:error />

</x-filament-panels::page>

@script
<script>
function toggleScannerHelp() {
    const helpDiv = document.getElementById('scanner-help');
    if (helpDiv) {
        helpDiv.classList.toggle('hidden');
    }
}

// Listen for barcode scanned events to provide visual feedback
document.addEventListener('livewire:init', () => {
    Livewire.on('barcodeScanned', (data) => {
        // Flash the scanner container green on successful scan
        const scannerContainer = document.querySelector('[wire\\:key*="barcode-scan"]');
        if (scannerContainer) {
            scannerContainer.classList.add('ring-2', 'ring-green-500', 'ring-opacity-50');
            setTimeout(() => {
                scannerContainer.classList.remove('ring-2', 'ring-green-500', 'ring-opacity-50');
            }, 1000);
        }
    });
});
</script>
@endscript
