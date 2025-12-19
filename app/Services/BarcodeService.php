<?php

namespace App\Services;

use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Picqer\Barcode\BarcodeGeneratorJPG;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BarcodeService
{
    protected BarcodeGenerator $generator;
    protected array $supportedFormats = [
        'EAN-13' => BarcodeGenerator::TYPE_EAN_13,
        'UPC-A' => BarcodeGenerator::TYPE_UPC_A,
        'CODE128' => BarcodeGenerator::TYPE_CODE_128,
        'CODE39' => BarcodeGenerator::TYPE_CODE_39,
        'QR' => BarcodeGenerator::TYPE_CODE_128, // Using CODE128 as fallback for QR
    ];

    public function __construct()
    {
        // BarcodeGenerator is abstract, we'll use specific generators as needed
    }

    /**
     * Generate barcode HTML
     */
    public function generateHtml(string $barcode, string $format = 'CODE128', int $widthFactor = 2, int $height = 30): string
    {
        $generator = new BarcodeGeneratorHTML();
        $type = $this->getBarcodeType($format);
        
        return $generator->getBarcode($barcode, $type, $widthFactor, $height);
    }

    /**
     * Generate barcode PNG image
     */
    public function generatePng(string $barcode, string $format = 'CODE128', int $widthFactor = 2, int $height = 30): string
    {
        $generator = new BarcodeGeneratorPNG();
        $type = $this->getBarcodeType($format);
        
        return $generator->getBarcode($barcode, $type, $widthFactor, $height);
    }

    /**
     * Generate barcode SVG
     */
    public function generateSvg(string $barcode, string $format = 'CODE128', int $widthFactor = 2, int $height = 30): string
    {
        $generator = new BarcodeGeneratorSVG();
        $type = $this->getBarcodeType($format);
        
        return $generator->getBarcode($barcode, $type, $widthFactor, $height);
    }

    /**
     * Generate barcode JPG image
     */
    public function generateJpg(string $barcode, string $format = 'CODE128', int $widthFactor = 2, int $height = 30): string
    {
        $generator = new BarcodeGeneratorJPG();
        $type = $this->getBarcodeType($format);
        
        return $generator->getBarcode($barcode, $type, $widthFactor, $height);
    }

    /**
     * Save barcode PNG to storage
     */
    public function saveBarcodePng(string $barcode, string $path, string $format = 'CODE128', int $widthFactor = 2, int $height = 30): string
    {
        $imageData = $this->generatePng($barcode, $format, $widthFactor, $height);
        $filename = Str::uuid() . '.png';
        $fullPath = $path . '/' . $filename;
        
        Storage::disk('public')->put($fullPath, $imageData);
        
        return $fullPath;
    }

    /**
     * Validate barcode format
     */
    public function validateBarcode(string $barcode, string $format = 'CODE128'): bool
    {
        if (empty($barcode)) {
            return false;
        }

        $type = $this->getBarcodeType($format);
        
        try {
            $generator = new BarcodeGeneratorHTML();
            $generator->getBarcode($barcode, $type);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate a unique barcode for a product
     */
    public function generateUniqueBarcode(string $prefix = 'PROD'): string
    {
        do {
            $timestamp = now()->format('YmdHis');
            $random = mt_rand(1000, 9999);
            $barcode = $prefix . $timestamp . $random;
        } while (!$this->validateBarcode($barcode));

        return $barcode;
    }

    /**
     * Get barcode type constant
     */
    protected function getBarcodeType(string $format): string
    {
        return $this->supportedFormats[strtoupper($format)] ?? BarcodeGenerator::TYPE_CODE_128;
    }

    /**
     * Get supported barcode formats
     */
    public function getSupportedFormats(): array
    {
        return array_keys($this->supportedFormats);
    }

    /**
     * Check if format is supported
     */
    public function isFormatSupported(string $format): bool
    {
        return isset($this->supportedFormats[strtoupper($format)]);
    }

    /**
     * Format barcode according to type
     */
    public function formatBarcode(string $barcode, string $format): string
    {
        $format = strtoupper($format);
        
        switch ($format) {
            case 'EAN-13':
                // Ensure 13 digits for EAN-13
                return str_pad(substr($barcode, 0, 12), 12, '0', STR_PAD_LEFT) . $this->calculateEan13Checksum($barcode);
            
            case 'UPC-A':
                // Ensure 12 digits for UPC-A
                return str_pad(substr($barcode, 0, 11), 11, '0', STR_PAD_LEFT) . $this->calculateUpcAChecksum($barcode);
            
            default:
                return $barcode;
        }
    }

    /**
     * Calculate EAN-13 checksum
     */
    protected function calculateEan13Checksum(string $barcode): string
    {
        $barcode = str_pad(substr($barcode, 0, 12), 12, '0', STR_PAD_LEFT);
        $sum = 0;
        
        for ($i = 0; $i < 12; $i++) {
            $digit = (int)$barcode[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        
        $checksum = (10 - ($sum % 10)) % 10;
        return (string)$checksum;
    }

    /**
     * Calculate UPC-A checksum
     */
    protected function calculateUpcAChecksum(string $barcode): string
    {
        $barcode = str_pad(substr($barcode, 0, 11), 11, '0', STR_PAD_LEFT);
        $sum = 0;
        
        for ($i = 0; $i < 11; $i++) {
            $digit = (int)$barcode[$i];
            $sum += ($i % 2 === 0) ? $digit * 3 : $digit;
        }
        
        $checksum = (10 - ($sum % 10)) % 10;
        return (string)$checksum;
    }

    /**
     * Generate barcode label with product information
     */
    public function generateBarcodeLabel(string $barcode, string $productName, float $price, string $format = 'CODE128'): array
    {
        return [
            'barcode' => $barcode,
            'barcode_html' => $this->generateHtml($barcode, $format),
            'product_name' => $productName,
            'price' => number_format($price, 2),
            'formatted_price' => '$' . number_format($price, 2),
        ];
    }
}