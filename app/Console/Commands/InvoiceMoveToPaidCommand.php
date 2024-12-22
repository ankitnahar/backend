<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class InvoiceMoveToPaidCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:movetopaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'invoice move to paid using cron file';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            set_time_limit(0);
            $invoiceRecurring = \App\Http\Controllers\Backend\Invoice\InvoiceXeroController::updateXeroPaidInvoice();
            
        } catch (\Exception $e) {
            $cronName = "Invoice Move to Paid";
            $message = $e->getMessage();
            cronNotWorking($cronName,$message);
           }
    }

}
