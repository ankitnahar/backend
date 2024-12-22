<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
class UserRightCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:right';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'User Right';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $userList = \App\Models\User::leftjoin("user_hierarchy as uh", "uh.user_id", "user.id")
                            ->select("user.id", "uh.designation_id")
                            ->where("is_active", 1)->where("designation_id", "!=", "7")->get();
            foreach ($userList as $user) {
                $designationRight = \App\Models\Backend\DesignationTabRight::where("designation_id", $user->designation_id)->get();
                $userTabRight = \App\Models\Backend\UserTabRight::where("user_id", $user->id);
                if ($userTabRight->count() == 0) {
                    foreach ($designationRight as $desrow) {
                        //Check Value already exiest or not
                        \App\Models\Backend\UserTabRight::create([
                            'tab_id' => $desrow->tab_id,
                            'user_id' => $user->id,
                            'view' => $desrow->view,
                            'add_edit' => $desrow->add_edit,
                            'delete' => $desrow->delete,
                            'export' => $desrow->export,
                            'download' => $desrow->download,
                            'other_right' => $desrow->other_right,
                            'created_on' => date('Y-m-d h:i:s'),
                            'created_by' => 1
                        ]);
                    }
                }
                $designationFieldRight = \App\Models\Backend\DesignationFieldRight::where("designation_id", $user->designation_id)->get();
                $userFieldRight = \App\Models\Backend\UserFieldRight::where("user_id", $user->id);
                if ($userFieldRight->count() == 0) {
                    foreach ($designationFieldRight as $desrow) {
                        //Check Value already exiest or not
                        \App\Models\Backend\UserFieldRight::create([
                            'field_id' => $desrow->field_id,
                            'user_id' => $user->id,
                            'view' => $desrow->view,
                            'add_edit' => $desrow->add_edit,
                            'delete' => $desrow->delete,
                            'created_on' => date('Y-m-d h:i:s'),
                            'created_by' => 1
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            $cronName = "User Right";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
