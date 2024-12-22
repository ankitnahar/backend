<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class ConferenceRoomReset extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conferenceroom:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'If conference booking date one day past then it should be hard remove';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $todayDate = date('Y-m-d');
            $conferenceRoom = \App\Models\Backend\ConferenceRoomBookDetail::where('date', '<=',$todayDate)->delete();
        } catch (Exception $ex) {
           $cronName = "Conference room";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
