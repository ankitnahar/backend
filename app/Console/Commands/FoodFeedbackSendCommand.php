<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class FoodFeedbackSendCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'food:feedbackmail';

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
        //try {
            \App\Http\Controllers\Backend\Food\FoodController::foodFeedback();
          
        /*} catch (Exception $e) {
            $cronName = "Food Report";
            $message = $e->getMessage();
            cronNotWorking($cronName,$message);
        }*/
    }

}
