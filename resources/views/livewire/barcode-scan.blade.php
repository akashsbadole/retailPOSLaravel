<div>
    <!-- Barcode Scanner Container -->
    <div class="space-y-3">
        <!-- Main Scanner Input -->
        <div class="relative">
            <input
                wire:model="query"
                wire:keydown.escape="closeErrorModal"
                wire:keydown.enter="addToCart"
                class="p-3 pr-10 bg-white block w-full text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                placeholder="Scan barcode or type manually..."
                autofocus
            />
            <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                </svg>
            </div>
        </div>

        <!-- Scanner Controls -->
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <label class="text-xs text-gray-600 dark:text-gray-400">Format:</label>
                <select
                    wire:model.live="barcodeFormat"
                    class="text-xs border border-gray-300 rounded px-2 py-1 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                    @foreach($supportedFormats as $format)
                        <option value="{{ $format }}">{{ $format }}</option>
                    @endforeach
                </select>
            </div>
            
            <button
                wire:click="toggleManualEntry"
                class="text-xs px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md transition-colors dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500"
            >
                {{ $showManualEntry ? 'Hide Manual Entry' : 'Manual Entry' }}
            </button>
        </div>

        <!-- Manual Entry Form -->
        @if($showManualEntry)
            <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 dark:bg-gray-700 dark:border-gray-600">
                <div class="flex items-center space-x-2">
                    <input
                        wire:model="manualBarcode"
                        wire:keydown.enter="addManualBarcode"
                        class="flex-1 p-2 text-sm border border-gray-300 rounded focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                        placeholder="Enter barcode manually..."
                    />
                    <button
                        wire:click="addManualBarcode"
                        class="px-3 py-2 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 transition-colors"
                    >
                        Add
                    </button>
                </div>
            </div>
        @endif

        <!-- Success Message -->
        @if($success)
            <div class="p-3 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="text-green-700 dark:text-green-300">{{ $success }}</span>
                    <button wire:click="closeSuccessMessage" class="ml-auto text-green-500 hover:text-green-700">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
        @endif
    </div>

    <!-- Error Modal -->
    @if($error)
        <div class="fixed inset-0 flex items-center justify-center z-50 bg-gray-600 bg-opacity-50 transition-opacity duration-500 ease-in-out"
            style="display: {{ $error ? 'flex' : 'none' }}; opacity: {{ $error ? '1' : '0' }};">
            <!-- Modal Container -->
            <div wire:click.outside="closeErrorModal"
                class="bg-white p-8 rounded-lg shadow-lg max-w-sm w-full opacity-0 transform transition-all duration-300 ease-in-out
                        {{ $error ? 'opacity-100 scale-100' : 'opacity-0 scale-95' }}">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-semibold mb-4 text-gray-900">Scanning Error</h2>
                    <p class="text-gray-700 mb-6">{{ $error }}</p>
                    <div class="flex space-x-3">
                        <button wire:click="closeErrorModal" class="flex-1 px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors">
                            Close
                        </button>
                        @if($showManualEntry)
                            <button wire:click="toggleManualEntry" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                                Try Manual Entry
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Audio elements for feedback -->
    <audio wire:key="success-sound" src="{{ asset('sounds/success.mp3') }}" id="success-sound"></audio>
    <audio wire:key="error-sound" src="{{ asset('sounds/error.mp3') }}" id="error-sound"></audio>

    @script
    <script>
        // Listen for barcode scanned events
        document.addEventListener('livewire:init', () => {
            Livewire.on('playSuccessSound', () => {
                const audio = document.getElementById('success-sound');
                if (audio) {
                    audio.play().catch(e => console.log('Audio play failed:', e));
                }
            });

            Livewire.on('playErrorSound', () => {
                const audio = document.getElementById('error-sound');
                if (audio) {
                    audio.play().catch(e => console.log('Audio play failed:', e));
                }
            });

            Livewire.on('barcodeScanned', (data) => {
                // Visual feedback for successful scan
                const input = document.querySelector('input[wire\\:model="query"]');
                if (input) {
                    input.classList.add('ring-2', 'ring-green-500');
                    setTimeout(() => {
                        input.classList.remove('ring-2', 'ring-green-500');
                    }, 1000);
                }
            });
        });
    </script>
    @endscript
</div>
