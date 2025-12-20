<?php

namespace App\Jobs;

use App\Models\ImportExportJob;
use App\Models\User;
use App\Notifications\ExportCompleted;
use App\Notifications\ExportFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour
    public int $tries = 3;
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private ImportExportJob $job
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->job->markAsStarted();
            
            $options = $this->job->options;
            $exportClass = $options['export_class'];
            $format = $options['format'] ?? 'xlsx';
            $storeId = $options['store_id'] ?? null;
            $dateFrom = $options['date_from'] ?? null;
            $dateTo = $options['date_to'] ?? null;
            $columns = $options['columns'] ?? [];
            $includeRelations = $options['include_relations'] ?? false;
            $sendEmail = $options['send_email'] ?? false;
            
            // Generate filename
            $filename = 'exports/' . $this->job->resource_type . '_' . date('Y-m-d_H-i-s') . '.' . $format;
            $this->job->update(['file_path' => $filename]);
            
            // Process export
            $export = new $exportClass(
                $storeId,
                $dateFrom,
                $dateTo,
                $columns,
                $includeRelations
            );
            $export->withJob($this->job);
            
            Excel::store($export, $filename, 'local');
            
            // Update job status
            $this->job->markAsCompleted();
            
            // Send notification
            $user = User::find($this->job->user_id);
            if ($user) {
                $user->notify(new ExportCompleted($this->job, $sendEmail));
            }
            
        } catch (\Exception $e) {
            Log::error('Export job failed: ' . $e->getMessage(), [
                'job_id' => $this->job->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->job->addError(0, 'system', $e->getMessage());
            $this->job->markAsFailed();
            
            // Send notification
            $user = User::find($this->job->user_id);
            if ($user) {
                $user->notify(new ExportFailed($this->job));
            }
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Export job failed permanently: ' . $exception->getMessage(), [
            'job_id' => $this->job->id,
            'trace' => $exception->getTraceAsString()
        ]);
        
        $this->job->addError(0, 'system', $exception->getMessage());
        $this->job->markAsFailed();
        
        // Send notification
        $user = User::find($this->job->user_id);
        if ($user) {
            $user->notify(new ExportFailed($this->job));
        }
    }
}