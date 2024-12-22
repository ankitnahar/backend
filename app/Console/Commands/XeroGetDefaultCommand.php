<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class XeroGetDefaultCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xero:entity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Xero entity create';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
       // try {
            $xeroEntity = \App\Models\Backend\XeroEntityAuth::where("is_active","1")->get();
            foreach ($xeroEntity as $e) {
                $payrollCalendar = \App\Http\Controllers\Backend\XeroPayroll\XeroMasterController::getPayrollCalendar($e->entity_id);
                $payitem = \App\Http\Controllers\Backend\XeroPayroll\XeroMasterController::getPayItems($e->entity_id);
                $super = \App\Http\Controllers\Backend\XeroPayroll\XeroMasterController::getSuperFund($e->entity_id);
                
                //$employee = \App\Http\Controllers\Backend\XeroPayroll\XeroMasterController::getEmployee($e->entity_id);
                //$createSuper = \App\Http\Controllers\Backend\XeroPayroll\XeroMasterController::createSuperFund($e->entity_id);
            }
       /* } catch (Exception $ex) {
            $cronName = "Xero Default Cron not working";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }*/
    }

}
