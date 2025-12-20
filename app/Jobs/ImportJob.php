<?php

namespace App\Jobs;

use App\Models\ImportExportJob;
use App\Models\User;
use App\Notifications\ImportCompleted;
use App\Notifications\ImportFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportJob implements ShouldQueue
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
            $importClass = $options['import_class'];
            $importMode = $options['import_mode'] ?? 'create_update';
            $validateOnly = $options['validate_only'] ?? false;
            $sendNotifications = $options['send_notifications'] ?? true;
            
            // Check if file exists
            if (!Storage::disk('local')->exists($this->job->file_path)) {
                throw new \Exception("Import file not found: {$this->job->file_path}");
            }
            
            // Read file data
            $data = \App\Helpers\ImportExportHelper::readFileData($this->job->file_path);
            $totalRecords = count($data);
            
            $this->job->update(['total_records' => $totalRecords]);
            
            if ($totalRecords === 0) {
                throw new \Exception("No data found in import file");
            }
            
            // Process import
            $import = new $importClass($importMode, $validateOnly);
            $import->withJob($this->job);
            
            Excel::import($import, $this->job->file_path, 'local');
            
            // Update job status
            $this->job->markAsCompleted();
            
            // Send notification
            if ($sendNotifications) {
                $user = User::find($this->job->user_id);
                if ($user) {
                    $user->notify(new ImportCompleted($this->job));
                }
            }
            
            // Clean up temporary file
            Storage::disk('local')->delete($this->job->file_path);
            
        } catch (\Exception $e) {
            Log::error('Import job failed: ' . $e->getMessage(), [
                'job_id' => $this->job->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->job->addError(0, 'system', $e->getMessage());
            $this->job->markAsFailed();
            
            // Send notification
            $user = User::find($this->job->user_id);
            if ($user) {
                $user->notify(new ImportFailed($this->job));
            }
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Import job failed permanently: ' . $exception->getMessage(), [
            'job_id' => $this->job->id,
            'trace' => $exception->getTraceAsString()
        ]);
        
        $this->job->addError(0, 'system', $exception->getMessage());
        $this->job->markAsFailed();
        
        // Send notification
        $user = User::find($this->job->user_id);
        if ($user) {
            $user->notify(new ImportFailed($this->job));
        }
    }
}