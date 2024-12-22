<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class XeroPayrunCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xero:payrun';

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
        //try{
               // $super = \App\Http\Controllers\Backend\XeroPayroll\XeroEmployeeController::getSuperFund(2);
            $employeeList = \App\Models\Backend\XeroEmployee::whereRaw("xero_employee_id IS NULL")->get();
            foreach($employeeList as $e){
                \App\Http\Controllers\Backend\XeroPayroll\XeroEmployeeController::store($e);
            }
       /* } catch (Exception $ex) {

        }*/
            
    }

}
