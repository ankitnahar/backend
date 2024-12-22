<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrBioTime extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:BioTime';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bio time';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            ini_set('max_execution_time', '0');
            $todayDate = date("Y-m-d");
            $hrDetail = \App\Models\Backend\HrDetail::where("date", $todayDate);
           $checkuser = \App\Models\User::where("is_active","1")->where('shift_id', '!=', 0)->count();
            if ($hrDetail->count() != $checkuser) {
                \App\Http\Controllers\Backend\Hr\HRController::addHrDetail($todayDate);
            }

            //$punchUser = self::fetchQuery($todayDate);

            //\App\Http\Controllers\Backend\Hr\HRController::addInOut($punchUser, $todayDate);
        } catch (Exception $ex) {
            $cronName = "HR Bio Time";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

    public static function fetchQuery($todayDate) {
        $bioServer = env('DB_HOST_SECOND');
        $bioUser = env('DB_USERNAME_SECOND');
        $bioPass = env('DB_PASSWORD_SECOND');
        $bioDB = env('DB_DATABASE_SECOND');

        $connInfo = array("Database" => $bioDB, "UID" => $bioUser, "PWD" => $bioPass);
        $conn = sqlsrv_connect($bioServer, $connInfo);
        
        if (!$conn) {
            die( print_r( sqlsrv_errors(), true));
           // die('Not connecting to MSSQL');
        } else {
            $nextDate = date('Y-m-d', strtotime($todayDate . ' +1 day'));
            $sql = "select CardNo, DeviceCode,CASE
                  WHEN Mode = 'IN'
                     THEN 1
                  ELSE 0
             END AS Mode, convert(varchar, In_Out_Time, 120) as dateT from tmpDmpTerminalData
            WHERE Mode IN ('IN','OUT') AND In_Out_Time >='" . $todayDate . "' 
            And In_Out_Time < '" . $nextDate . "' order by In_Out_Time";
            $stmt = sqlsrv_query($conn, $sql);
            if ($stmt) {
                $punchUser = array();
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_NUMERIC)) {
                    $location = 0;
                    if ($row[1] == 1 || $row[1] == 2) {
                        $location = 1;
                    } else if ($row == 3 || $row[1] == 4) {
                        $location = 5;
                    } else if ($row[1] == 5 || $row[1] == 11 || $row[1] == 6 || $row[1] == 12) {
                        $location = 2;
                    } else if ($row[1] == 7 || $row[1] == 8) {
                        $location = 6;
                    } else if ($row[1] = 9) {
                        $location = 4;
                    } else if ($row[1] == 13 || $row[1] == 14) {
                        $location = 3;
                    }
                    $r1['CardNo'] = $row[0];
                    $r1['DeviceCode'] = $row[1];
                    $r1['Mode'] = $row[2];
                    $r1['DateT'] = $row[3];
                    $r1['location'] = $location;
                    $punchUser[$row[0]][] = $r1;
                }
                sqlsrv_close($conn);

                return $punchUser;
            }
        }
    }
}