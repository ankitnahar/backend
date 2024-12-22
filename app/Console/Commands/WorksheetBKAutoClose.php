<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class WorksheetBKAutoClose extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Worksheet:bkautoclose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bookkeeping worksheet auto closed';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            /*
             * Reason: Below mention master activity need to auto closed when report sent and due date is equal or greater than today date. below is master activity.
             * 5 =>  Bookkeeping
             * 6 =>  Preparation & Lodgment of BAS
             * 7 =>  Preparation & Lodgment of IAS (BK)
             * 8 =>  Payroll
             * 9 =>  Accounts Receivable
             * 10 => Accounts Payable
             * 11 => Debtors Management
             */

            $todayDate = date('Y-m-d');
            $bkWorksheet = \App\Models\Backend\Worksheet::where('status_id', 13)->get()->toArray();

            if (!empty($bkWorksheet)) {
                foreach ($bkWorksheet as $key => $value) {
                    $updateData['completed_on'] = date('Y-m-d');
                    $updateData['status_id'] = 4;
                    $updateData['completed_by'] = 1;
                    $updateData['is_there_delay'] = 2;
                    \App\Models\Backend\Worksheet::where('id', $value['id'])->update($updateData);

                    $logData['worksheet_id'] = $value['id'];
                    $logData['status_id'] = 4;
                    $logData['created_by'] = 1;
                    $logData['created_on'] = date('Y-m-d H:i:s');
                    $worksheetLog = \App\Models\Backend\WorksheetLog::insert($logData);
                }
            }
            
            $bkNonchargeableWorksheet = \App\Models\Backend\Worksheet::whereIn('task_id', [3,58])->whereRaw("status_id != 4")->where('due_date', '<=', $todayDate)->get()->toArray();

            if (!empty($bkNonchargeableWorksheet)) {
                foreach ($bkNonchargeableWorksheet as $key => $value) {
                    $updateData['completed_on'] = date('Y-m-d');
                    $updateData['status_id'] = 4;
                    $updateData['completed_by'] = 1;
                    $updateData['is_there_delay'] = 2;
                    
                    \App\Models\Backend\Worksheet::where('id', $value['id'])->update($updateData);
                    $logData['worksheet_id'] = $value['id'];
                    $logData['status_id'] = 4;
                    $logData['created_by'] = 1;
                    $logData['created_on'] = date('Y-m-d H:i:s');
                    $worksheetLog = \App\Models\Backend\WorksheetLog::insert($logData);
                }
            }
        } catch (Exception $ex) {
            $cronName = "Worksheet Bk Auto Close";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
