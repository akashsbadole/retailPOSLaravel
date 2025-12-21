<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="mt-6 flex justify-end space-x-4">
            <button 
                type="button"
                wire:click="resetToDefaults"
                class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors"
            >
                Reset to Defaults
            </button>
            
            <button 
                type="button"
                wire:click="testScanner"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
            >
                Test Scanner
            </button>
            
            <button 
                type="submit"
                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
            >
                Save Settings
            </button>
        </div>
    </form>
</x-filament-panels::page>

<!-- Scanner Test Modal -->
<div id="barcode-scanner-test-modal" x-data="{ isOpen: false }" x-show="isOpen"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-50"
     style="display: none;">
    
    <div class="flex min-h-screen items-center justify-center p-4">
        <div x-show="isOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-90"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-90"
             class="relative w-full max-w-md transform overflow-hidden rounded-lg bg-white p-6 shadow-xl">
            
            <div class="text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 mb-4">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                    </svg>
                </div>
                
                <h3 class="text-lg font-medium text-gray-900 mb-2">Barcode Scanner Test</h3>
                <p class="text-sm text-gray-500 mb-4">Scan a barcode to test your scanner configuration</p>
                
                <div class="mb-4">
                    <input 
                        type="text" 
                        id="test-scanner-input"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Scan barcode here..."
                        autofocus
                    />
                </div>
                
                <div id="test-result" class="hidden mb-4 p-3 rounded-md">
                    <p class="text-sm font-medium"></p>
                </div>
                
                <div class="flex space-x-3">
                    <button
                        @click="isOpen = false; $el.removeAttribute('open')"
                        class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        @click="document.getElementById('test-scanner-input').value = ''; document.getElementById('test-result').classList.add('hidden')"
                        class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                    >
                        Clear
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('openScannerTest', () => {
            const modal = document.querySelector('[x-data]');
            if (modal) {
                modal.__x.$data.isOpen = true;
                
                // Focus on input when modal opens
                setTimeout(() => {
                    const input = document.getElementById('test-scanner-input');
                    if (input) {
                        input.focus();
                        
                        // Listen for barcode input
                        input.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter') {
                                const barcode = input.value.trim();
                                if (barcode) {
                                    const resultDiv = document.getElementById('test-result');
                                    const resultText = resultDiv.querySelector('p');
                                    
                                    resultDiv.classList.remove('hidden');
                                    resultDiv.classList.remove('bg-red-50', 'bg-green-50', 'text-red-700', 'text-green-700');
                                    
                                    // Simulate validation (you might want to call an API endpoint here)
                                    if (barcode.length >= 8) {
                                        resultDiv.classList.add('bg-green-50', 'text-green-700');
                                        resultText.textContent = `✓ Valid barcode: ${barcode}`;
                                        
                                        // Play success sound if enabled
                                        @if(config('settings.scanner_sound_enabled', true))
                                            const audio = new Audio('{{ asset('sounds/success.mp3') }}');
                                            audio.play().catch(e => console.log('Audio play failed:', e));
                                        @endif
                                    } else {
                                        resultDiv.classList.add('bg-red-50', 'text-red-700');
                                        resultText.textContent = `✗ Invalid barcode: ${barcode}`;
                                        
                                        // Play error sound if enabled
                                        @if(config('settings.scanner_sound_enabled', true))
                                            const audio = new Audio('{{ asset('sounds/error.mp3') }}');
                                            audio.play().catch(e => console.log('Audio play failed:', e));
                                        @endif
                                    }
                                    
                                    // Clear input after processing
                                    setTimeout(() => {
                                        input.value = '';
                                    }, 1000);
                                }
                            }
                        });
                    }
                }, 100);
            }
        });
    });
</script>
@endscript