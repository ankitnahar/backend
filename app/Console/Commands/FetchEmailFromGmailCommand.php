<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class FetchEmailFromGmailCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:fetchgmail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch Gmail mail';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
          
        } catch (Exception $e) {
            $cronName = "Fetch Gmail";
            $message = $e->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
