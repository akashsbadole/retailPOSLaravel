<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\Cart;
use App\Services\BarcodeService;
use Livewire\Component;

class BarcodeScan extends Component
{
    public $query = '';

    public $error = '';

    public $success = '';

    public $barcodeFormat = 'CODE128';

    public $showManualEntry = false;

    public $manualBarcode = '';

    protected $barcodeService;

    public function boot(BarcodeService $barcodeService)
    {
        $this->barcodeService = $barcodeService;
    }

    public function mount()
    {
        $this->barcodeFormat = config('settings.default_barcode_format', 'CODE128');
    }

    public function render()
    {
        return view('livewire.barcode-scan');
    }

    public function addToCart()
    {
        if (empty($this->query)) {
            return;
        }
        
        $this->processBarcode($this->query);
    }

    public function addManualBarcode()
    {
        if (empty($this->manualBarcode)) {
            $this->error = 'Please enter a barcode';
            return;
        }
        
        $this->processBarcode($this->manualBarcode);
        $this->manualBarcode = '';
        $this->showManualEntry = false;
    }

    protected function processBarcode($barcode)
    {
        $this->error = '';
        $this->success = '';
        
        // Clean and validate barcode
        $barcode = trim($barcode);
        
        if (!$this->barcodeService->validateBarcode($barcode, $this->barcodeFormat)) {
            $this->error = "Invalid barcode format for {$this->barcodeFormat}";
            $this->query = '';
            $this->dispatch('playErrorSound');
            return;
        }

        // Try to find product by barcode
        $product = Product::where('barcode', $barcode)->first();
        
        if (!$product) {
            // Try to find by SKU if barcode not found
            $product = Product::where('sku', $barcode)->first();
            
            if (!$product) {
                $this->error = "Product with barcode '{$barcode}' not found!";
                $this->query = '';
                $this->dispatch('playErrorSound');
                return;
            }
        }

        $user_id = auth()->user()->id;
        $quantity = 1;
        
        $cartItem = Cart::firstOrCreate(
            [
                'user_id' => $user_id,
                'product_id' => $product->id
            ],
            [
                'name' => $product->name,
                'quantity' => 0,
                'price' => $product->price,
                'tax' => $product->tax
            ]
        );

        if ($product->quantity < ($cartItem->quantity + $quantity)) {
            $this->error = 'Product Is Out of Stock!';
            if ($cartItem->quantity < 1) {
                $cartItem->delete();
            }
            $this->query = '';
            $this->dispatch('playErrorSound');
            return;
        }
        
        $cartItem->update([
            'quantity' => $cartItem->quantity + $quantity
        ]);

        $this->query = '';
        $this->success = "{$product->name} added to cart!";

        $this->dispatch('cartUpdated');
        $this->dispatch('playSuccessSound');
        $this->dispatch('showNotification', $this->success, 'success');
        $this->dispatch('barcodeScanned', [
            'product_name' => $product->name,
            'barcode' => $barcode,
            'quantity' => $quantity
        ]);
    }

    public function toggleManualEntry()
    {
        $this->showManualEntry = !$this->showManualEntry;
        $this->manualBarcode = '';
    }

    public function closeErrorModal()
    {
        $this->error = '';
    }

    public function closeSuccessMessage()
    {
        $this->success = '';
    }

    public function setBarcodeFormat($format)
    {
        if ($this->barcodeService->isFormatSupported($format)) {
            $this->barcodeFormat = $format;
        }
    }

    public function getSupportedFormatsProperty()
    {
        return $this->barcodeService->getSupportedFormats();
    }
}
