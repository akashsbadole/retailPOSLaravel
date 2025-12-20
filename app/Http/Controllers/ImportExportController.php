<?php

namespace App\Http\Controllers;

use App\Helpers\ImportExportHelper;
use App\Models\ImportExportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    /**
     * Download template for import.
     */
    public function downloadTemplate(Request $request, string $resource)
    {
        $columns = $this->getTemplateColumns($resource);
        $format = $request->get('format', 'xlsx');
        
        $filename = ImportExportHelper::generateTemplate($resource, $columns, $format);
        
        if (!Storage::disk('local')->exists($filename)) {
            abort(404, 'Template not found');
        }
        
        $fileContents = Storage::disk('local')->get($filename);
        $mimeType = match ($format) {
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
        
        return new StreamedResponse(
            $fileContents,
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . basename($filename) . '"',
            ]
        );
    }
    
    /**
     * Download exported file.
     */
    public function download(ImportExportJob $job)
    {
        if ($job->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }
        
        if (!$job->file_path || $job->type !== 'export') {
            abort(404, 'File not found');
        }
        
        if (!Storage::disk('local')->exists($job->file_path)) {
            abort(404, 'File not found');
        }
        
        $fileContents = Storage::disk('local')->get($job->file_path);
        $mimeType = $this->getMimeType($job->file_path);
        
        return new StreamedResponse(
            $fileContents,
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . basename($job->file_path) . '"',
            ]
        );
    }
    
    /**
     * Get template columns for a resource.
     */
    protected function getTemplateColumns(string $resource): array
    {
        return match ($resource) {
            'products' => [
                'name' => 'Name',
                'barcode' => 'Barcode',
                'sku' => 'SKU',
                'price' => 'Selling Price',
                'cost_price' => 'Cost Price',
                'quantity' => 'Quantity',
                'minimum_stock' => 'Minimum Stock',
                'tax' => 'Tax (%)',
                'status' => 'Status',
                'image' => 'Image URL',
            ],
            'customers' => [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'email' => 'Email',
                'phone' => 'Phone',
                'address' => 'Address',
                'avatar' => 'Avatar URL',
            ],
            'suppliers' => [
                'name' => 'Name',
                'contact_person' => 'Contact Person',
                'email' => 'Email',
                'phone' => 'Phone',
                'address' => 'Address',
                'gst_number' => 'GST Number',
                'status' => 'Status',
            ],
            'orders' => [
                'customer_identifier' => 'Customer Identifier (Email/Phone/ID)',
                'total_price' => 'Total Price',
                'discount_amount' => 'Discount Amount',
                'status' => 'Status',
                'items' => 'Items (JSON format)',
            ],
            'purchases' => [
                'supplier_identifier' => 'Supplier Identifier (Email/Phone/ID)',
                'purchase_date' => 'Purchase Date',
                'total_amount' => 'Total Amount',
                'discount_amount' => 'Discount Amount',
                'tax_amount' => 'Tax Amount',
                'final_amount' => 'Final Amount',
                'status' => 'Status',
                'payment_status' => 'Payment Status',
                'items' => 'Items (JSON format)',
            ],
            default => [],
        };
    }
    
    /**
     * Get MIME type based on file extension.
     */
    protected function getMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        return match ($extension) {
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }
}