<?php

namespace App\Notifications;

use App\Models\ImportExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportFailed extends Notification
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
            ->subject('Import Failed')
            ->greeting('Hello ' . $notifiable->name)
            ->line("Your {$this->job->resource_type} import has failed.")
            ->line("Processed: {$this->job->processed_records} records")
            ->line("Failed: {$this->job->failed_records} records")
            ->when(!empty($this->job->errors), function ($message) {
                $message->line("Please check the errors below and fix them before re-importing.");
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
            'title' => 'Import Failed',
            'message' => "Your {$this->job->resource_type} import has failed.",
            'type' => 'error',
            'job_id' => $this->job->id,
            'error_count' => count($this->job->errors ?? []),
        ];
    }
}