<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class WorksheetAutoCreation extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worksheet:autocreation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Worksheet auto creation';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {
        //UPDATE worksheet_schedule SET start_date=DATE_FORMAT(start_date,'2025-%m-%d') , end_date=DATE_FORMAT(end_date,'2025-%m-%d');
        $today = date('Y-m-d');
        $worksheetSchdule = \App\Models\Backend\WorksheetSchedule::leftjoin("entity as e","e.id","worksheet_schedule.entity_id")
                ->select("worksheet_schedule.*")
                ->where("worksheet_schedule.is_created","0")
                ->where("e.discontinue_stage","=","0")
                ->orderBy("worksheet_schedule.entity_id","asc")
                ->get()->toArray();
        foreach($worksheetSchdule as $queryData){
        echo $id = $queryData['entity_id'];
        //$startDate = date('Y-m-d', strtotime('+1 year', strtotime($queryData['start_date'])));
        //$endDate = date('Y-m-d', strtotime('+1 year', strtotime($queryData['end_date'])));   
        $startDate =$queryData['start_date'];
        $endDate =$queryData['end_date'];
        $queryData['start_date'] = $startDate;
        $queryData['end_date'] = $endDate;
        $exprtDay = $queryData['expert_day'];
        $exprtMonth = $queryData['expert_month'];
        $dueAfterDay = $queryData['due_after_day'];
        $dueMonthDay = $queryData['due_month_day'];
        $dueOnParticulerDate = $queryData['due_on_particular_date'];
        $frequency = $queryData['frequency_id'];

        $worksheetData = array();
        $worksheetData = \App\Http\Controllers\Backend\Worksheet\WorksheetController::generateDailyWorksheet($startDate, $endDate, $frequency, $exprtDay, $exprtMonth, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);
       
        \App\Http\Controllers\Backend\Worksheet\WorksheetScheduleController::addWorksheet($id, $queryData, $worksheetData);
        \App\Models\Backend\WorksheetSchedule::where("id",$queryData['id'])->update(['is_created' => 1]);
            
        }
        /* } catch (Exception $ex) {
          $cronName = "Worksheet Auto Close";
          $message = $ex->getMessage();
          cronNotWorking($cronName, $message);
          } */
    }

}
