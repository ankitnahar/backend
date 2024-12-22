<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel {

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\SendmailCommand::class,
        Commands\PriorRecurringCommand::class,
        Commands\CheckRecurringCommand::class,
        Commands\MoveToDMCommand::class,
        Commands\AuditInvoiceCommand::class,
        Commands\RecurringStopCommand::class,
        Commands\TicketMonthlyCommand::class,
        Commands\TicketReportCommand::class,
        Commands\PendingTicketCommand::class,
        Commands\BillingStageCommand::class,
        Commands\FutureProposalCommand::class,
        Commands\UpdateBillingCommand::class,
        Commands\InvoiceAutoCommand::class,
        Commands\AutoInvoiceSendCommand::class,
        Commands\InvoiceSendToXeroCommand::class,
        Commands\InvoiceMoveToPaidCommand::class,
        Commands\MoveToDMCommand::class,
        Commands\WriteoffBefreeCommand::class,
        Commands\WriteoffBefreeWeeklyCommand::class,
        Commands\ArchiveSpecialNotes::class,
        Commands\ConferenceRoomReset::class,
        Commands\Duetimesheet::class,
        Commands\JiraTimesheet::class,
        Commands\TimesheetSummary::class,
        Commands\WorksheetAutoClose::class,
        Commands\WorksheetReminder::class,
        Commands\WorksheetBKAutoClose::class, // Pending
        Commands\WorksheetAutoCloseAfterOverdue::class,
        //Commands\WriteoffBefree::class,
        Commands\HrLatesetting::class,
        Commands\HrLateComingNotification::class,
        Commands\HrBioTime::class,
        Commands\HrPunchBetween8PMTO4AM::class,
        Commands\HrUpdateRemarkPreviousDay::class,
        Commands\HrRemainingApprovalNotification::class,
        Commands\HrRejectedRemainingApproval::class,
        Commands\HrAutoRejectedMissedTimesheet::class,
        Commands\HrMonthlyAttendanceSummary::class,
        Commands\HrHalfDayNotification::class,
        Commands\OpportunitiesFromZoho::class,
        Commands\EntitySpecialNotes::class,
        Commands\ResetSoftwareCommand::class,
        Commands\UserRightCommand::class,
        Commands\HrConsecutiveLeave::class,
        Commands\HrNoJobArchive::class,
        Commands\ResetTicketFlagCommand::class,
        Commands\LeadFromZoho::class,
        Commands\BDMSLiveReplicaCommand::class,
        Commands\QuoteFuturequoteReminder::class,
        Commands\QuoteDocusignStatus::class,
        Commands\QuoteAutoReminder::class,
        Commands\HrReminderCommand::class,
        Commands\PendingWorksheetSechdule::class,
        Commands\FeedbackTaskCommand::class,
        Commands\FeedbackExceptionCommand::class,
        Commands\GoogleRevokePermissionCommand::class,
        Commands\GoogleDriveFolderCommand::class,
        Commands\FeedbackTaskResourceCommand::class,                
        Commands\InformationGenerateCommand::class,
        Commands\AutoInformationReminderCommand::class,
        Commands\DailyTimesheet::class,
        Commands\ReminderEmailCommand::class,
        Commands\RemoveFilesCommand::class,
        Commands\BirthdayCommand::class,
        Commands\SendmailClientCommand::class,
        Commands\HrAddSturdayInHoliday::class,
        Commands\FiveYearDataRetentionCommand::class,
        Commands\UserFromZoho::class,
        Commands\HrLeaveCalculation::class,
        Commands\WorksheetAutoCreation::class,
        Commands\GoogleDriveYearFolderCommand::class,
        Commands\AwardUserDetails::class,
        Commands\FoodReportCommand::class,
        Commands\FoodFeedbackSendCommand::class,
        Commands\FoodFeedbackCommand::class,
        Commands\BillingScript::class,
        Commands\XeroGetDefaultCommand::class,
        Commands\XeroPayrunCommand::class,
        Commands\XeroEmployeeCommand::class,
        Commands\HrWelcomeKit::class,
        Commands\AdjustInvoiceCommand::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule) {
        //
    }

}
