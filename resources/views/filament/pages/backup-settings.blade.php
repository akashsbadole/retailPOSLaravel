<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="flex justify-end gap-2 mt-6">
            <x-filament::button type="submit">
                {{ __('Save Settings') }}
            </x-filament::button>
            
            <x-filament::button 
                type="button" 
                wire:click="$wire.set('showTestModal', true)"
                color="success"
            >
                {{ __('Test Backup') }}
            </x-filament::button>
        </div>
    </form>

    <!-- Test Backup Modal -->
    <x-filament::modal 
        id="test-backup-modal" 
        wire:model.live="showTestModal"
        heading="Test Backup Configuration"
        width="2xl"
    >
        <div class="space-y-4">
            <p class="text-sm text-gray-600">
                This will create a test backup with your current settings to verify everything is working correctly.
            </p>
            
            <div class="bg-amber-50 border border-amber-200 rounded-md p-4">
                <h4 class="text-sm font-medium text-amber-800 mb-2">Test Backup Details:</h4>
                <ul class="text-sm text-amber-700 space-y-1">
                    <li>• Creates a small selective backup</li>
                    <li>• Tests compression and encryption settings</li>
                    <li>• Verifies storage permissions</li>
                    <li>• Validates notification system</li>
                </ul>
            </div>
            
            <div class="flex justify-end gap-2 mt-6">
                <x-filament::button 
                    type="button" 
                    wire:click="$wire.set('showTestModal', false)"
                >
                    {{ __('Cancel') }}
                </x-filament::button>
                
                <x-filament::button 
                    type="button" 
                    wire:click="testBackup"
                    color="success"
                >
                    {{ __('Run Test Backup') }}
                </x-filament::button>
            </div>
        </div>
    </x-filament::modal>
</x-filament-panels::page>