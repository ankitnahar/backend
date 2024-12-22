<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class InvoiceSendToXeroCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:sendtoxero';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'invoice send to xero using cron file';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            \App\Models\Backend\Invoice::where("xero_responce","0")->update(["xero_responce"=>1]);
            $invoiceSendToXero = \App\Http\Controllers\Backend\Invoice\InvoiceXeroController::invoiceSendToXero();
            app('log')->error("Invoice Move to Xero failed " . $invoiceSendToXero);
        } catch (\Exception $e) {
            $cronName = "Invoice Send To Xero";
            $message = $e->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
