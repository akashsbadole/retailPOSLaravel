<?php

namespace App\Livewire\Order;

use App\Models\Product;
use App\Models\Cart;
use App\Models\OrderItem;
use App\Services\BarcodeService;
use Livewire\Component;

class BarcodeScan extends Component
{
    public $query = '';

    public $error = '';

    public $success = '';

    public $orderId;

    public $barcodeFormat = 'CODE128';

    public $showManualEntry = false;

    public $manualBarcode = '';

    protected $barcodeService;

    public function boot(BarcodeService $barcodeService)
    {
        $this->barcodeService = $barcodeService;
    }

    public function mount($orderId)
    {
        $this->orderId = $orderId;
        $this->barcodeFormat = config('settings.default_barcode_format', 'CODE128');
    }

    public function render()
    {
        return view('livewire.order.barcode-scan');
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

        // Check if product is in stock
        if (!$product->isInStock()) {
            $this->error = 'Product is out of stock!';
            $this->query = '';
            $this->dispatch('playErrorSound');
            return;
        }

        // Use product tax or default tax rate from settings
        $taxRate = $product->tax ?? config('settings.default_tax_rate', 0);
        
        $cartItem = OrderItem::firstOrCreate(
            [
                'order_id' => $this->orderId,
                'product_id' => $product->id
            ],
            [
                'name' => $product->name,
                'quantity' => 0,
                'price' => $product->price,
                'tax' => $taxRate,
                'discount_percentage' => 0
            ]
        );

        // Check if adding this quantity would exceed available stock
        $quantity = 1;
        $totalQuantity = $cartItem->quantity + $quantity;
        if (!$product->hasStockFor($totalQuantity)) {
            $this->error = "Cannot add {$quantity} more items. Only {$product->quantity} available in stock!";
            if ($cartItem->quantity < 1) {
                $cartItem->delete();
            }
            $this->query = '';
            $this->dispatch('playErrorSound');
            return;
        }
        
        // Update cart item quantity
        $cartItem->update([
            'quantity' => $totalQuantity
        ]);

        // Deduct stock using the model method
        if (!$product->deductStock($quantity)) {
            $this->error = 'Failed to update stock!';
            $this->query = '';
            $this->dispatch('playErrorSound');
            return;
        }

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
