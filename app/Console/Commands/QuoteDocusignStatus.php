<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class QuoteDocusignStatus extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Quote:Docusignstatus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'To checkout docusign status';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle($date = null) {
        try {
           $result= new \App\Http\Controllers\Backend\Quote\QuoteDocuSignController();
           $status = $result->checkStatus();           
           
        } catch (Exception $ex) {
            $cronName = "Docusign status cron";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
