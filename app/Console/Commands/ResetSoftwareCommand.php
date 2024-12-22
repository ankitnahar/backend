<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class ResetSoftwareCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:software';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset Software';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {            
            \App\Models\Backend\SoftwareLogin::where("used_by","!=","0")->update(["used_by"=>'0',"login_time"=>'']);
        } catch (Exception $e) {
            $cronName = "Reset Software";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
