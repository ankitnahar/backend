<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class OpportunitiesFromZoho extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Opportunities:fromzoho';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Oportunities get from zoho and store it to bdms database';

    

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {

            $opportunitiesClass = new \App\Http\Controllers\Backend\Entity\OpportunitiesFromZohoController();

            $obj =new \App\Models\Backend\ZohoClass();
            $data = $obj->getOrganizationInstance();

            $opportunitiesClass::parseJson($data);

        } catch (Exception $ex) {
            $cronName = "Opportunities From Zoho";
            $message = $ex->getMessage();
            //cronNotWorking($cronName, $message);
            echo $message;
        }
    }

}
