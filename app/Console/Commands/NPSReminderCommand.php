<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class NPSCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nps:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'NPS create';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try{
        $day = date("d");
        if($day == '01'){
            \App\Http\Controllers\Backend\NPS\NPSClientController::npsGenerate();
        }else if($day == '06'){
            \App\Http\Controllers\Backend\NPS\NPSClientController::sendMailtoClient();
        }
        /*} catch (Exception $ex) {
            $cronName = "NPS create Cron not working";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }*/
            
    }

}
