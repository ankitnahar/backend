<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class ResetTicketFlagCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:ticket';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset Ticket';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {            
            \App\Models\Backend\Ticket::where("flag_open","=","1")->update(["flag_open"=>'0',"open_time"=>'00.00',"opened_by"=>'0']);
            \App\Models\Backend\Ticket::where("opened_by","!=","0")->update(["flag_open"=>'0',"open_time"=>'00.00',"opened_by"=>'0']);
            /*$email = \App\Models\Backend\EmailContent::where("status","2")->get();
            foreach($email as $e){
                \App\Models\Backend\EmailContent::where("id",$e->id)->update(["to_email"=>trim($e->to_email),"status"=>"0"]);
            }*/
            
        } catch (Exception $e) {
            $cronName = "Reset Ticket Flag";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
