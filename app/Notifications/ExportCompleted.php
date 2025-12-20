<?php

namespace App\Notifications;

use App\Models\ImportExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExportCompleted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private ImportExportJob $job,
        private bool $sendEmail = false
    ) {}

    /**
     * Get notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->sendEmail) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Export Completed Successfully')
            ->greeting('Hello ' . $notifiable->name)
            ->line("Your {$this->job->resource_type} export has been completed successfully.")
            ->line("Exported: {$this->job->processed_records} records")
            ->line("File size: {$this->job->formatted_file_size}")
            ->action('Download File', $this->job->download_url)
            ->line('The download link will be available for 24 hours.')
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Export Completed',
            'message' => "Your {$this->job->resource_type} export has been completed successfully.",
            'type' => 'success',
            'job_id' => $this->job->id,
            'processed_records' => $this->job->processed_records,
            'download_url' => $this->job->download_url,
        ];
    }
}