<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;

class LeadFromZoho extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Lead:fromzoho';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lead get from zoho and store it to bdms database';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $leadClass = new \App\Http\Controllers\Backend\Quote\LeadsFromZohoController();

            $obj =new \App\Models\Backend\ZohoClass();
            $data = $obj->getQuoteLeadInstance();

            $leadClass::parseJson($data);

        } catch (Exception $ex) {
            app('log')->error("Oportunities related error : " . $e->getMessage());
        }
    }

}
