<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class DeliverNotificationChannels implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public User $recipient, public Notification $notification, public array $channels)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    public function handle(): void
    {
        NotificationFacade::sendNow($this->recipient, $this->notification, $this->channels);
    }
}
