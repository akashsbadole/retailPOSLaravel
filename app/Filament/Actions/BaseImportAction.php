<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

abstract class BaseImportAction extends Action
{
    protected static string $resourceClass;
    
    protected static string $importClass;
    
    protected static array $importOptions = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Import ' . static::getResourceName())
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->form([
                FileUpload::make('file')
                    ->label('Import File')
                    ->disk('temp')
                    ->directory('imports')
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->helperText('Supported formats: CSV, XLS, XLSX')
                    ->required(),
                    
                Select::make('import_mode')
                    ->label('Import Mode')
                    ->options([
                        'create_update' => 'Create New & Update Existing',
                        'create_only' => 'Create New Only',
                        'update_only' => 'Update Existing Only',
                        'skip_duplicates' => 'Skip Duplicates',
                    ])
                    ->default('create_update')
                    ->required(),
                    
                Toggle::make('validate_only')
                    ->label('Validate Only (No Import)')
                    ->helperText('Check data validity without importing')
                    ->default(false),
                    
                Toggle::make('send_notifications')
                    ->label('Send Email Notifications')
                    ->default(true),
            ])
            ->action(function (array $data) {
                $this->handleImport($data);
            });
    }
    
    protected function handleImport(array $data): void
    {
        try {
            $filePath = $data['file'];
            $importMode = $data['import_mode'];
            $validateOnly = $data['validate_only'] ?? false;
            $sendNotifications = $data['send_notifications'] ?? true;
            
            // Move file from temp to permanent storage
            $file = Storage::disk('temp')->get($filePath);
            $permanentPath = 'imports/' . basename($filePath);
            Storage::disk('local')->put($permanentPath, $file);
            Storage::disk('temp')->delete($filePath);
            
            // Create import job
            $importJob = \App\Models\ImportExportJob::create([
                'type' => 'import',
                'resource_type' => static::getResourceType(),
                'status' => 'pending',
                'user_id' => auth()->id(),
                'file_path' => $permanentPath,
                'options' => [
                    'import_mode' => $importMode,
                    'validate_only' => $validateOnly,
                    'send_notifications' => $sendNotifications,
                    'import_class' => static::$importClass,
                ],
            ]);
            
            // Dispatch import job
            \App\Jobs\ImportJob::dispatch($importJob);
            
            Notification::make()
                ->success()
                ->title('Import Started')
                ->body('Your import has been queued and will be processed shortly.')
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Import Failed')
                ->body('Error: ' . $e->getMessage())
                ->send();
        }
    }
    
    protected static function getResourceName(): string
    {
        return class_basename(static::$resourceClass);
    }
    
    protected static function getResourceType(): string
    {
        return strtolower(class_basename(static::$resourceClass));
    }
    
    public static function make(string $name = null): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();
        
        return $static;
    }
}