<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

abstract class BaseExportAction extends Action
{
    protected static string $resourceClass;
    
    protected static string $exportClass;
    
    protected static array $availableColumns = [];
    
    protected static array $exportOptions = [];
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->label('Export ' . static::getResourceName())
            ->icon('heroicon-o-arrow-up-tray')
            ->color('success')
            ->form([
                Select::make('format')
                    ->label('Export Format')
                    ->options([
                        'csv' => 'CSV',
                        'xlsx' => 'Excel (XLSX)',
                        'json' => 'JSON',
                    ])
                    ->default('xlsx')
                    ->required(),
                    
                Select::make('store_id')
                    ->label('Store')
                    ->options(\App\Models\Store::pluck('name', 'id'))
                    ->placeholder('All Stores')
                    ->visible(fn() => static::supportsStores()),
                    
                DatePicker::make('date_from')
                    ->label('Date From')
                    ->visible(fn() => static::supportsDateRange()),
                    
                DatePicker::make('date_to')
                    ->label('Date To')
                    ->visible(fn() => static::supportsDateRange()),
                    
                CheckboxList::make('columns')
                    ->label('Columns to Export')
                    ->options(static::$availableColumns)
                    ->columns(3)
                    ->default(array_keys(static::$availableColumns)),
                    
                Toggle::make('include_relations')
                    ->label('Include Related Data')
                    ->helperText('Export related records (e.g., order items, customer details)')
                    ->default(false)
                    ->visible(fn() => static::supportsRelations()),
                    
                Toggle::make('send_email')
                    ->label('Send Download Link via Email')
                    ->default(false),
            ])
            ->action(function (array $data) {
                $this->handleExport($data);
            });
    }
    
    protected function handleExport(array $data): void
    {
        try {
            $format = $data['format'];
            $storeId = $data['store_id'] ?? null;
            $dateFrom = $data['date_from'] ?? null;
            $dateTo = $data['date_to'] ?? null;
            $columns = $data['columns'] ?? array_keys(static::$availableColumns);
            $includeRelations = $data['include_relations'] ?? false;
            $sendEmail = $data['send_email'] ?? false;
            
            // Create export job
            $exportJob = \App\Models\ImportExportJob::create([
                'type' => 'export',
                'resource_type' => static::getResourceType(),
                'status' => 'pending',
                'user_id' => auth()->id(),
                'options' => [
                    'format' => $format,
                    'store_id' => $storeId,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'columns' => $columns,
                    'include_relations' => $includeRelations,
                    'send_email' => $sendEmail,
                    'export_class' => static::$exportClass,
                ],
            ]);
            
            // Dispatch export job
            \App\Jobs\ExportJob::dispatch($exportJob);
            
            Notification::make()
                ->success()
                ->title('Export Started')
                ->body('Your export has been queued and will be processed shortly.')
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Export Failed')
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
    
    protected static function supportsStores(): bool
    {
        return false;
    }
    
    protected static function supportsDateRange(): bool
    {
        return false;
    }
    
    protected static function supportsRelations(): bool
    {
        return false;
    }
    
    public static function make(string $name = null): static
    {
        $static = app(static::class, ['name' => $name]);
        $static->configure();
        
        return $static;
    }
}