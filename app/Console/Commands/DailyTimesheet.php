<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class DailyTimesheet extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily:timesheet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily Timesheet';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $todayDate = date('Y-m-d');

            $timesheetList = \App\Models\Backend\Timesheet::select('u.userfullname', 'u.user_bio_id', 'e.trading_name', 'm.name as master', 't.name as task', 's.subactivity_full_name', 'w.start_date', 'w.end_date', 'f.frequency_name', 'timesheet.name_of_employee', "timesheet.notes", "timesheet.user_id")
                    ->leftjoin("entity as e", "e.id", "timesheet.entity_id")
                    ->leftjoin("sub_client as e1", "e1.id", "timesheet.subclient_id")
                    ->leftjoin("worksheet as w", "w.id", "timesheet.worksheet_id")
                    ->leftJoin('user as u', 'u.id', '=', 'timesheet.user_id')
                    ->leftJoin('master_activity as m', 'm.id', '=', 'w.master_activity_id')
                    ->leftJoin('task as t', 't.id', '=', 'w.task_id')
                    ->leftJoin('subactivity as s', 's.subactivity_code', '=', 'timesheet.subactivity_code')
                    ->leftJoin('frequency as f', 'f.id', '=', 'timesheet.frequency_id')
                    ->whereIn("user_id", [700, 1335])
                    ->where("date", $todayDate);
            if ($timesheetList->count() > 0) {
                $timesheetList = $timesheetList->get();
                // $content = "<table><tr><td>Sr.No.</td><td>User Name</td><td>Trading Name</td><td>Master Name</td><td>Task Name</td><td>SubActivity Name</td><td>Unit</td><td>Notes</td></tr>";
                $i = 1;
                foreach ($timesheetList as $t) {
                    $content = '';
                    $content = "<table><tr><td>Today's Work</td></tr>";
                    if ($t->user_id == '700') {
                        $content .= "<tr><td>" . $t->name_of_employee . "</td></tr>";
                    } else {
                        $content .= "<tr><td>" . $t->notes . "</td></tr>";
                    }
                    $i++;


                    $content .= "</table>";

                    $emailData['to'] = 'vasant.d@befree.com.au,rajesh@superrecords.com.au,jigneshk@befree.com.au';
                    //$emailData['to'] = 'pankaj.k@befree.com.au';   
                    $emailData['cc'] = 'pankaj.k@befree.com.au';
                    $emailData['subject'] = $t->userfullname . ' ' . $todayDate . " Timesheet";
                    $emailData['content'] = html_entity_decode($content);

                    $sendMail = cronStoreMail($emailData);
                }
            }
        } catch (Exception $ex) {
            $cronName = "Daily Timesheet";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
