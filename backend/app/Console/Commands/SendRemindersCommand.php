<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

/**
 * Scheduled daily at 08:00 Asia/Manila (routes/console.php). Safe to run by hand:
 * notification_dedupes keeps each reminder to once per window.
 */
class SendRemindersCommand extends Command
{
    protected $signature = 'gias:send-reminders';

    protected $description = 'Send scheduled GIAS reminders: overdue reports, signatures, evaluation windows and reviews, Monday digest';

    public function handle(ReminderService $reminders): int
    {
        $counts = $reminders->run();

        $this->table(['Reminder', 'Sent'], collect($counts)->map(fn (int $n, string $type) => [$type, $n])->values()->all());
        $this->info('Total sent: '.array_sum($counts));

        return self::SUCCESS;
    }
}
