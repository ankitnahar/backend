<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class ArchiveSpecialNotes extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'specialnotes:archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Temporary special notes should be permantly archive';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $todayDate = date('Y-m-d');
            $specialNotes = \App\Models\Backend\EntitySpecialnotes::where('type', 0)->where('expiry_on', $todayDate)->pluck('id', 'id')->toArray();
            $data['is_active'] = 1;
            $data['modified_by'] = 0;
            $data['modified_on'] = date('Y-m-d H:i:s');
            \App\Models\Backend\EntitySpecialnotes::whereIn('id', $specialNotes)->update($data);
        } catch (Exception $ex) {
            $cronName = "Archive Special Notes";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
