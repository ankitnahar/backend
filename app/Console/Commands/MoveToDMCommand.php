<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class MoveToDMCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:movedm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invoice move to Debtors';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $updateDebtors = \App\Models\Backend\Invoice::updateDebtors();
            // echo $updateDebtors->toSql();exit;
            // echo $updateDebtors->count();exit;
            if ($updateDebtors->count() > 0) {
                foreach ($updateDebtors->get() as $dm) {
                    \App\Models\Backend\Invoice::where("invoice_no", $dm->invoice_no)->update(["debtors_stage" => 1]);
                }
            }
            $updateDMError = isset($updateDebtors->original['payload']['error']) ? $updateDebtors->original['payload']['error'] : '';
            if ($updateDMError != '') {
                $cronName = "Move To DM";
                $message = $updateDMError;
                cronNotWorking($cronName, $message);
            }
        } catch (Exception $e) {
            $cronName = "Move To DM";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
