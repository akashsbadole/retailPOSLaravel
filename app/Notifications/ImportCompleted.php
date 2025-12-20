<?php

namespace App\Notifications;

use App\Models\ImportExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportCompleted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private ImportExportJob $job
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Import Completed Successfully')
            ->greeting('Hello ' . $notifiable->name)
            ->line("Your {$this->job->resource_type} import has been completed successfully.")
            ->line("Processed: {$this->job->processed_records} records")
            ->line("Failed: {$this->job->failed_records} records")
            ->when($this->job->failed_records > 0, function ($message) {
                $message->line("Please check the errors and fix them before re-importing.");
            })
            ->action('View Details', route('filament.admin.import-exports.show', $this->job->id))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Import Completed',
            'message' => "Your {$this->job->resource_type} import has been completed successfully.",
            'type' => 'success',
            'job_id' => $this->job->id,
            'processed_records' => $this->job->processed_records,
            'failed_records' => $this->job->failed_records,
        ];
    }
}