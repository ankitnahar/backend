<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class FoodReportCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'food:report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily food report';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            \App\Http\Controllers\Backend\Food\FoodController::dailyReportMail();
          
        } catch (Exception $e) {
            $cronName = "Food Report";
            $message = $e->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
