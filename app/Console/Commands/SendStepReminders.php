<?php

namespace App\Console\Commands;

use App\Approval\AutomaticStepReminders;
use Illuminate\Console\Command;

/**
 * Run hourly by the scheduler (routes/console.php). Safe to run as often as
 * you like: the reminder log makes a repeat run send nothing new.
 */
class SendStepReminders extends Command
{
    protected $signature = 'documents:send-step-reminders';

    protected $description = 'Send the 24 hour reminders and overdue alerts for documents waiting at an approver step.';

    public function handle(AutomaticStepReminders $reminders): int
    {
        $summary = $reminders->run();

        $this->info("Reminders sent: {$summary['reminders']}. SDAO overdue alerts sent: {$summary['overdue_alerts']}.");

        return self::SUCCESS;
    }
}
