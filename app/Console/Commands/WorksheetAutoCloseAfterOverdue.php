<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;
class WorksheetAutoCloseAfterOverdue extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Worksheet:autocloseafterdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Worksheet auto closed after overdue';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            //$day = date('d');
            $day = '01';
            if ($day == '01') {
                $startDate = date('Y-m-d', strtotime('first day of last month'));
                $endDate = date('Y-m-31', strtotime('last day of last month'));
                $worksheet = \App\Models\Backend\Worksheet::where('status_id', '!=', 4)->whereBetween('end_date', array($startDate, $endDate))->whereIn('master_activity_id', [3,12])->get()->toArray();
                
                $worksheetId = $worksheetStatusLog = array();
                foreach($worksheet as $key => $value){
                    $worksheetId[] = $value['id'];
                    $data = array();
                    $data['worksheet_id'] =  $value['id'];
                    $data['status_id'] = 4;
                    $data['created_on'] = date('Y-m-d H:i:s');
                    $data['created_by'] = 1;
                    $worksheetStatusLog[] = $data;                    
                }
                
                $updateData['completed_on'] = date('Y-m-d H:i:s');
                $updateData['status_id']    = 4;
                $updateData['completed_by'] = 1;
                
                \App\Models\Backend\Worksheet::whereIn('id', $worksheetId)->update($updateData);
                \App\Models\Backend\WorksheetLog::insert($worksheetStatusLog);
            }
        } catch (Exception $ex) {
            $cronName = "Worksheet Auto Close After Overdue";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
