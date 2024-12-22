<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;
class WorksheetAutoClose extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Worksheet:autoclose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Worksheet auto closed';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {
            /*
             *  Non-Chargeable Processing task auto closed when task mark as report sent
             */
            $nonChargebleTask = \App\Models\Backend\Worksheet::whereIn('task_id', [58,160,250])->where('status_id', 13)->get()->toArray();
            if (!empty($nonChargebleTask)) {
                foreach ($nonChargebleTask AS $key => $value) {
                    $worksheetMaster = \App\Models\Backend\WorksheetMaster::find($value['id']);
                    if (!empty($worksheetMaster)) {
                        $worksheetMasterData['created_by'] = $worksheetMaster->created_by;
                        $worksheetMasterData['entity_id'] = $worksheetMaster->entity_id;
                        $worksheetMasterData['master_activity_id'] = $worksheetMaster->master_activity_id;
                        $worksheetMasterData['task_id'] = $worksheetMaster->task_id;
                        $worksheetMasterData['task_name'] = $worksheetMaster->task_name;
                        $worksheetMasterData['ask_repeat_task'] = $worksheetMaster->ask_repeat_task;
                        $worksheetMasterData['start_date'] = $worksheetMaster->start_date;
                        $worksheetMasterData['end_date'] = $worksheetMaster->end_date;
                        $worksheetMasterData['frequency'] = $worksheetMaster->frequency;
                        $worksheetMasterData['expert_day'] = $worksheetMaster->expert_day;
                        $worksheetMasterData['expert_month'] = $worksheetMaster->expert_month;
                        $worksheetMasterData['duedate'] = $worksheetMaster->duedate;
                        $worksheetMasterData['duedate_days'] = $worksheetMaster->duedate_days;
                        $worksheetMasterData['duedate_date'] = $worksheetMaster->duedate_date;
                        $worksheetMasterData['notes'] = $worksheetMaster->notes;
                        $worksheetMasterData['created_on'] = $worksheetMaster->created_on;
                        $wmData = \App\Models\Backend\WorksheetMaster::insert($worksheetMasterData);

                        $worksheet['worksheet_master_id'] = $wmData->id;
                        $worksheet['due_date'] = $wmData->due_date;
                        $worksheet['start_date'] = $wmData->start_date;
                        $worksheet['end_date'] = $wmData->end_date;
                        $worksheet['notes'] = $wmData->notes;
                        $worksheet['status_id'] = $wmData->status_id;
                        $worksheet['created_on'] = date("Y-m-d H:i:s");
                        $worksheet['modified_on'] = date("Y-m-d H:i:s");
                        \App\Models\Backend\Worksheet::insert($worksheet);

                        $updateData['completed_on'] = date("Y-m-d H:i:s");
                        $updateData['status_id'] = 4;
                        $updateData['completed_by'] = 1;
                        \App\Models\Backend\Worksheet::where('id', $value['id'])->update($updateData);
                    }
                }
            }

            /*
             *  Chargeable Processing task auto closed when task mark as report sent
             */
            $chargebleTask = \App\Models\Backend\Worksheet::where('task_id', 2)->where('status_id', 13)->get()->toArray();
            if (!empty($chargebleTask)) {
                foreach ($chargebleTask AS $key => $value) {
                    $worksheetMaster = array();
                    $worksheetMaster = \App\Models\Backend\WorksheetMaster::find($value['id']);
                    if (!empty($worksheetMaster)) {
                        $worksheetMasterData['created_by'] = $worksheetMaster->created_by;
                        $worksheetMasterData['entity_id'] = $worksheetMaster->entity_id;
                        $worksheetMasterData['master_activity_id'] = $worksheetMaster->master_activity_id;
                        $worksheetMasterData['task_id'] = $worksheetMaster->task_id;
                        $worksheetMasterData['task_name'] = $worksheetMaster->task_name;
                        $worksheetMasterData['ask_repeat_task'] = $worksheetMaster->ask_repeat_task;
                        $worksheetMasterData['start_date'] = $worksheetMaster->start_date;
                        $worksheetMasterData['end_date'] = $worksheetMaster->end_date;
                        $worksheetMasterData['frequency'] = $worksheetMaster->frequency;
                        $worksheetMasterData['expert_day'] = $worksheetMaster->expert_day;
                        $worksheetMasterData['expert_month'] = $worksheetMaster->expert_month;
                        $worksheetMasterData['duedate'] = $worksheetMaster->duedate;
                        $worksheetMasterData['duedate_days'] = $worksheetMaster->duedate_days;
                        $worksheetMasterData['duedate_date'] = $worksheetMaster->duedate_date;
                        $worksheetMasterData['notes'] = $worksheetMaster->notes;
                        $worksheetMasterData['created_on'] = $worksheetMaster->created_on;
                        $wmData = \App\Models\Backend\WorksheetMaster::insert($worksheetMasterData);

                        $worksheet['worksheet_master_id'] = $wmData->id;
                        $worksheet['due_date'] = $wmData->due_date;
                        $worksheet['start_date'] = $wmData->start_date;
                        $worksheet['end_date'] = $wmData->end_date;
                        $worksheet['notes'] = $wmData->notes;
                        $worksheet['status_id'] = $wmData->status_id;
                        $worksheet['created_on'] = date("Y-m-d H:i:s");
                        $worksheet['modified_on'] = date("Y-m-d H:i:s");
                        \App\Models\Backend\Worksheet::insert($worksheet);

                        $updateData['completed_on'] = date("Y-m-d H:i:s");
                        $updateData['status_id'] = 4;
                        $updateData['completed_by'] = 1;
                        \App\Models\Backend\Worksheet::where('id', $value['id'])->update($updateData);
                    }
                }
            }
            /*
             *  404 Worksheet Auto Closed
             *  As discuss with Dilip Bhai only report sent status auto closed
             */
            $task404 = \App\Models\Backend\Worksheet::select('id')->where('task_id', 57)->where('status_id', 13)->get()->toArray();
            if (!empty($task404)) {
                foreach ($task404 AS $key => $value) {
                    $timesheet404 = \App\Models\Backend\Timesheet::select('no_of_value')->where('worksheet_id', $value['id'])->where('subactivity_code', 404)->get()->toArray();
                    $timesheet402 = \App\Models\Backend\Timesheet::select('no_of_value')->where('worksheet_id', $value['id'])->where('subactivity_code', 402)->get()->toArray();

                    if (!empty($timesheet404) && !empty($timesheet402) && ($timesheet404[0] == $timesheet402[0])) {
                        $updateData['completed_on'] = date('Y-m-d H:i:s');
                        $updateData['completed_by'] = '1';
                        $updateData['status_id']    = '4';
                        \App\Models\Backend\Worksheet::where('id', $value['id'])->update($updateData);
                    }
                }
            }

            /*
             * Lodgement of PAYG Payment Summary  - 53,
             * Lodgment of Payroll Tax Return - 54,
             * Payment setup - 47,
             * Preparation of IAS - 1,
             * Preparation of Super Report - 50,
             * Preparing PAYG Payment summary - 52,
             * Sending payslip to employees - 48,
             * Setting up super with bank/super fund - 51,
             * As discuss with Dilip Bhai only report sent status auto closed - Email on 21/05/2017
             */
            $payrollTask = \App\Models\Backend\Worksheet::select('id')->where('service_id', "2")->whereIn('status_id', [13,15])->get();
            
            if (!empty($payrollTask)) {
                foreach($payrollTask as $p){
                $updateData = array();
                $updateData['completed_on'] = date('Y-m-d H:i:s');
                $updateData['completed_by'] = 1;
                $updateData['status_id'] = 4;
                \App\Models\Backend\Worksheet::where('id', $p->id)->update($updateData);
                }
            }
            
             $otherTask = \App\Models\Backend\Worksheet::select('id')->where('service_id', "0")->where('status_id', 26)->get();
            
            if (!empty($otherTask)) {
                foreach($otherTask as $p){
                $updateData = array();
                $updateData['completed_on'] = date('Y-m-d H:i:s');
                $updateData['completed_by'] = 1;
                $updateData['status_id'] = 4;
                \App\Models\Backend\Worksheet::where('id', $p->id)->update($updateData);
                }
            }
            
       /* } catch (Exception $ex) {
            $cronName = "Worksheet Auto Close";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }*/
    }

}