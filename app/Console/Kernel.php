<?php

namespace Acelle\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Acelle\Model\Automation2;
use Acelle\Model\Notification;
use Acelle\Cashier\Cashier;
use Acelle\Model\Subscription;
use Acelle\Model\Setting;
use Laravel\Tinker\Console\TinkerCommand;
use Exception;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Commands\Inspire::class,
        /* no longer needed as of Laravel 5.5
        Commands\TestCampaign::class,
        Commands\UpgradeTranslation::class,
        Commands\RunHandler::class,
        Commands\ImportList::class,
        Commands\VerifySender::class,
        Commands\SystemCleanup::class,
        Commands\GeoIpCheck::class,
        TinkerCommand::class,
        */
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     */
    protected function schedule(Schedule $schedule)
    {
        if (!isInitiated()) {
            return;
        }

        $title = 'PHP extension error';
        if (!class_exists('SQLite3')) {
            $message = "Class 'SQLite3' not found, please check with your hosting provider to enable the related PHP package (php*-sqlite3 extension)";
            Notification::cleanupDuplicateNotifications($title);
            Notification::warning(['title' => $title, 'message' => $message]);
            throw new Exception($message);
        } else {
            Notification::cleanupDuplicateNotifications($title);
        }

        $title = 'Server does not support PHP process';
        $message = 'The Process class relies on proc_open, which is not available on your hosting server PHP installation. Please check with your hosting provider to enable it.';
        if (!function_exists('proc_open')) {
            Notification::cleanupDuplicateNotifications($title);
            Notification::warning(['title' => $title, 'message' => $message]);
            throw new Exception($message);
        } else {
            Notification::cleanupDuplicateNotifications($title);
        }

        // Log last execution time
        // Move the event into a schedule::call to prevent it from triggering every time "php artisan" command is executed
        $schedule->call(function () {
            event(new \Acelle\Events\CronJobExecuted());
        })->name('cronjob_event:log')->everyMinute();

        // Automation2
        $schedule->call(function () {
            Automation2::run();
        })->name('automation:run')->everyFiveMinutes();

        // Bounce/feedback handler
        $schedule->command('handler:run')->everyThirtyMinutes();

        // Queued import/export/campaign
        $schedule->command('queue:work --once --tries=1')->everyMinute();

        // Sender verifying
        $schedule->command('sender:verify')->everyFiveMinutes();

        // System clean up
        $schedule->command('system:cleanup')->daily();

        // GeoIp database check
        $schedule->command('geoip:check')->everyMinute()->withoutOverlapping(60);

        // Subscription
        $schedule->call(function () {
            Subscription::checkAll();
        })->name('subscription:run')->everyFiveMinutes();
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
