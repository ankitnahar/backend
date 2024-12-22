<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class XeroEmployeeCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xero:employee';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Xero employee create';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $xeroEntity = \App\Models\Backend\XeroEntityAuth::where("is_active","1")->get();
            foreach ($xeroEntity as $e) {
                $employee = \App\Http\Controllers\Backend\XeroPayroll\XeroMasterController::getEmployee($e->entity_id);
                $createSuper = \App\Http\Controllers\Backend\XeroPayroll\XeroMasterController::createSuperFund($e->entity_id);
            }
        } catch (Exception $ex) {
            $cronName = "Xero Employee Cron not working";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
