<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
use DB;

class ClientConsoleCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'client:console';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit Invoice create';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {
           
       /* } catch (Exception $e) {
            $cronName = "Audit Invoice";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }*/
    }

}
