<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

class TransactionAwareNotificationSender
{
    /** @param iterable<User>|User|null $notifiables */
    public function send(iterable|User|null $notifiables, Notification $notification): void
    {
        if ($notifiables === null) {
            return;
        }

        $recipients = $notifiables instanceof User ? [$notifiables] : $notifiables;

        foreach ($recipients as $recipient) {
            $channels = $notification->via($recipient);
            $databaseChannels = array_values(array_intersect($channels, ['database']));
            $afterCommitChannels = array_values(array_diff($channels, ['database']));

            if ($databaseChannels !== []) {
                NotificationFacade::sendNow($recipient, $notification, $databaseChannels);
            }

            if ($afterCommitChannels !== []) {
                DB::afterCommit(function () use ($recipient, $notification, $afterCommitChannels): void {
                    try {
                        NotificationFacade::sendNow($recipient, $notification, $afterCommitChannels);
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                });
            }
        }
    }
}
