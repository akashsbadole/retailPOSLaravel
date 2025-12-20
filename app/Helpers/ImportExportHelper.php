<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;

class ImportExportHelper
{
    /**
     * Validate import file
     */
    public static function validateImportFile($file): array
    {
        $validator = Validator::make(['file' => $file], [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:51200', // 50MB max
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Get file extension from file path
     */
    public static function getFileExtension(string $filePath): string
    {
        return strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    }

    /**
     * Generate unique filename
     */
    public static function generateUniqueFilename(string $originalName, string $extension): string
    {
        return Str::uuid() . '_' . time() . '.' . $extension;
    }

    /**
     * Store uploaded file
     */
    public static function storeUploadedFile($file, string $directory = 'imports'): string
    {
        $extension = self::getFileExtension($file->getClientOriginalName());
        $filename = self::generateUniqueFilename($file->getClientOriginalName(), $extension);
        
        return $file->storeAs($directory, $filename, 'local');
    }

    /**
     * Move file from temp to permanent storage
     */
    public static function moveFileToPermanentStorage(string $tempPath, string $directory = 'imports'): string
    {
        $filename = basename($tempPath);
        $permanentPath = $directory . '/' . $filename;
        
        if (Storage::disk('temp')->exists($tempPath)) {
            $fileContents = Storage::disk('temp')->get($tempPath);
            Storage::disk('local')->put($permanentPath, $fileContents);
            Storage::disk('temp')->delete($tempPath);
        }
        
        return $permanentPath;
    }

    /**
     * Read file data based on format
     */
    public static function readFileData(string $filePath, string $format = 'auto'): array
    {
        if ($format === 'auto') {
            $format = self::getFileExtension($filePath);
        }

        switch ($format) {
            case 'csv':
                return self::readCsvFile($filePath);
            case 'xlsx':
            case 'xls':
                return self::readExcelFile($filePath);
            case 'json':
                return self::readJsonFile($filePath);
            default:
                throw new \Exception("Unsupported file format: {$format}");
        }
    }

    /**
     * Read CSV file
     */
    protected static function readCsvFile(string $filePath): array
    {
        $data = [];
        $handle = fopen(storage_path('app/' . $filePath), 'r');
        
        if ($handle === false) {
            throw new \Exception("Unable to open file: {$filePath}");
        }

        // Skip metadata rows (starting with #)
        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0]) || !str_starts_with($row[0], '#')) {
                // This is the header row
                $headers = $row;
                break;
            }
        }

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row[0])) continue;
            
            $data[] = array_combine($headers, $row);
        }

        fclose($handle);
        return $data;
    }

    /**
     * Read Excel file
     */
    protected static function readExcelFile(string $filePath): array
    {
        $data = [];
        $rows = Excel::toArray([], storage_path('app/' . $filePath));
        
        if (empty($rows) || empty($rows[0])) {
            return $data;
        }

        $sheet = $rows[0];
        $headers = [];
        $dataStartIndex = 0;

        // Find header row (skip metadata rows)
        foreach ($sheet as $index => $row) {
            if (empty($row[0]) || (is_string($row[0]) && str_starts_with($row[0], '#'))) {
                continue;
            }
            $headers = $row;
            $dataStartIndex = $index + 1;
            break;
        }

        // Read data rows
        for ($i = $dataStartIndex; $i < count($sheet); $i++) {
            $row = $sheet[$i];
            if (empty($row[0])) continue;
            
            $data[] = array_combine($headers, $row);
        }

        return $data;
    }

    /**
     * Read JSON file
     */
    protected static function readJsonFile(string $filePath): array
    {
        $content = file_get_contents(storage_path('app/' . $filePath));
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON file: " . json_last_error_msg());
        }
        
        return $data;
    }

    /**
     * Write data to file
     */
    public static function writeDataToFile(array $data, string $filePath, string $format = 'xlsx'): void
    {
        switch ($format) {
            case 'csv':
                self::writeCsvFile($data, $filePath);
                break;
            case 'xlsx':
                self::writeExcelFile($data, $filePath);
                break;
            case 'json':
                self::writeJsonFile($data, $filePath);
                break;
            default:
                throw new \Exception("Unsupported export format: {$format}");
        }
    }

    /**
     * Write CSV file
     */
    protected static function writeCsvFile(array $data, string $filePath): void
    {
        $handle = fopen(storage_path('app/' . $filePath), 'w');
        
        if ($handle === false) {
            throw new \Exception("Unable to create file: {$filePath}");
        }

        // Write metadata
        fputcsv($handle, ['# Resource: ' . class_basename($data[0] ?? 'Data')]);
        fputcsv($handle, ['# Version: 1.0']);
        fputcsv($handle, ['# Export Date: ' . now()->format('Y-m-d H:i:s')]);
        fputcsv($handle, ['# Store: ' . (auth()->user()->store->name ?? 'All Stores')]);

        if (!empty($data)) {
            // Write headers
            $headers = array_keys($data[0]);
            fputcsv($handle, $headers);

            // Write data rows
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
        }

        fclose($handle);
    }

    /**
     * Write Excel file
     */
    protected static function writeExcelFile(array $data, string $filePath): void
    {
        // This would typically use Laravel Excel package
        // For now, we'll use a simple approach
        $exportData = [];
        
        if (!empty($data)) {
            // Add metadata rows
            $exportData[] = ['# Resource: ' . class_basename($data[0])];
            $exportData[] = ['# Version: 1.0'];
            $exportData[] = ['# Export Date: ' . now()->format('Y-m-d H:i:s')];
            $exportData[] = ['# Store: ' . (auth()->user()->store->name ?? 'All Stores')];
            $exportData[] = []; // Empty row
            
            // Add headers
            $headers = array_keys($data[0]);
            $exportData[] = $headers;
            
            // Add data rows
            foreach ($data as $row) {
                $exportData[] = array_values($row);
            }
        }
        
        Excel::store(new class($exportData) implements \Maatwebsite\Excel\Concerns\FromCollection, \Maatwebsite\Excel\Concerns\WithHeadings {
            private $data;
            
            public function __construct(array $data)
            {
                $this->data = $data;
            }
            
            public function collection()
            {
                return collect($this->data);
            }
            
            public function headings(): array
            {
                return [];
            }
        }, $filePath, 'local');
    }

    /**
     * Write JSON file
     */
    protected static function writeJsonFile(array $data, string $filePath): void
    {
        $exportData = [
            'metadata' => [
                'resource' => class_basename($data[0] ?? 'Data'),
                'version' => '1.0',
                'export_date' => now()->format('Y-m-d H:i:s'),
                'store' => auth()->user()->store->name ?? 'All Stores',
            ],
            'data' => $data,
        ];
        
        file_put_contents(storage_path('app/' . $filePath), json_encode($exportData, JSON_PRETTY_PRINT));
    }

    /**
     * Clean up temporary files
     */
    public static function cleanupTempFiles(string $directory = 'imports'): void
    {
        $files = Storage::disk('local')->allFiles($directory);
        
        foreach ($files as $file) {
            // Delete files older than 24 hours
            if (Storage::disk('local')->lastModified($file) < now()->subHours(24)->timestamp) {
                Storage::disk('local')->delete($file);
            }
        }
    }

    /**
     * Format file size for display
     */
    public static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Generate template file for import
     */
    public static function generateTemplate(string $resourceType, array $columns, string $format = 'xlsx'): string
    {
        $templateData = [];
        
        // Create sample data row with column names as values
        $sampleRow = [];
        foreach ($columns as $column => $label) {
            $sampleRow[$column] = ''; // Empty values for template
        }
        
        $templateData[] = $sampleRow;
        
        $filename = "templates/{$resourceType}_template." . $format;
        self::writeDataToFile($templateData, $filename, $format);
        
        return $filename;
    }

    /**
     * Validate email address
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number
     */
    public static function validatePhone(string $phone): bool
    {
        // Basic phone validation - can be enhanced based on requirements
        return preg_match('/^[+]?[\d\s\-\(\)]+$/', $phone);
    }

    /**
     * Validate GST number (Indian format)
     */
    public static function validateGST(string $gst): bool
    {
        // Basic GST validation - 15 characters, alphanumeric
        return preg_match('/^[0-9A-Z]{15}$/', $gst);
    }

    /**
     * Clean and format phone number
     */
    public static function formatPhone(string $phone): string
    {
        // Remove all non-numeric characters except +
        return preg_replace('/[^0-9+]/', '', $phone);
    }

    /**
     * Generate unique barcode
     */
    public static function generateUniqueBarcode(string $prefix = 'PROD'): string
    {
        $barcodeService = app(\App\Services\BarcodeService::class);
        return $barcodeService->generateUniqueBarcode($prefix);
    }

    /**
     * Handle image import
     */
    public static function handleImageImport($imageSource, string $destinationDisk = 'public_uploads'): ?string
    {
        if (empty($imageSource)) {
            return null;
        }

        try {
            // Handle URL
            if (filter_var($imageSource, FILTER_VALIDATE_URL)) {
                $imageContents = file_get_contents($imageSource);
                $filename = uniqid() . '.' . pathinfo($imageSource, PATHINFO_EXTENSION);
                Storage::disk($destinationDisk)->put($filename, $imageContents);
                return $filename;
            }
            
            // Handle base64
            if (str_starts_with($imageSource, 'data:image')) {
                $imageData = explode(',', $imageSource)[1];
                $imageContents = base64_decode($imageData);
                $filename = uniqid() . '.jpg';
                Storage::disk($destinationDisk)->put($filename, $imageContents);
                return $filename;
            }
            
            // Handle file path
            if (Storage::disk('temp')->exists($imageSource)) {
                $filename = uniqid() . '.' . pathinfo($imageSource, PATHINFO_EXTENSION);
                Storage::disk($destinationDisk)->move($imageSource, $filename);
                return $filename;
            }
            
            return null;
        } catch (\Exception $e) {
            \Log::error('Image import failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Handle image export
     */
    public static function handleImageExport(?string $imagePath, string $sourceDisk = 'public_uploads'): ?string
    {
        if (!$imagePath) {
            return null;
        }
        
        return Storage::disk($sourceDisk)->url($imagePath);
    }
}