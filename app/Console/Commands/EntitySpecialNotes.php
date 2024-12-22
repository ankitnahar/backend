<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class EntitySpecialNotes extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Entity:Specialnotes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Entity special note expire if type is temp';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $today = date("Y-m-d");
            $updateData['is_active'] = 0;
            $updateData['modified_by'] = 1;
            $updateData['modified_on'] = $today;
            $entitySpecialNote = \App\Models\Backend\EntitySpecialnotes::where('type', 0)->where('is_active', 1)->where('expiry_on', $today)->update($updateData);
        } catch (Exception $ex) {
            $cronName = "Entity Special Notes";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
