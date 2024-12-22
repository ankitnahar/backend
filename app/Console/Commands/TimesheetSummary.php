<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;
class TimesheetSummary extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Timesheet:summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Timesheet summary';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $date = date('Y-m-d', strtotime('-45 days'));
            $entity_id = '1410,1412,1414,1416,1418,385,1126,2620,3320,3554';

            $query = "SELECT A.month_year, A.user_id, A.CLIENT_UNIT, IF(B.NON_CHARGE IS NULL, 0, B.NON_CHARGE) as NON_CHARGE,(A.CLIENT_UNIT + IFNULL(B.NON_CHARGE, 0))TOTAL,A.technical_account_manager,A.division_head,A.user_writeoff
        FROM (SELECT SUBSTR(t.date, 1, 7) AS month_year, t.user_id, SUM(t.units) AS CLIENT_UNIT,IF(uh.designation_id = 9, uh.user_id, 0) AS technical_account_manager, IF(uh.designation_id = 15, uh.user_id, 0) AS division_head,u.user_writeoff
        FROM timesheet t LEFT JOIN worksheet_master wm ON wm.id=t.worksheet_id
        LEFT JOIN user u ON u.id=t.user_id
        LEFT JOIN user_hierarchy AS uh
          ON uh.user_id = u.id
        WHERE t.date >= '" . $date . "'
            AND wm.entity_id NOT IN (1410,1412,1414,1416,1418,385,1126,2620,3320,3554)
            AND FIND_IN_SET(1,uh.team_id)
            AND uh.designation_id IN (9,10,22)
            GROUP BY month_year, user_id) AS A
        LEFT JOIN( SELECT SUBSTR(t.date, 1, 7) AS month_year, t.user_id, SUM(t.units) AS NON_CHARGE
        FROM timesheet t LEFT JOIN worksheet_master wm ON wm.id=t.worksheet_id
         WHERE t.date >= '" . $date . "'
         AND wm.entity_id IN (1410,1412,1414,1416,1418,385,1126,2620,3320,3554)
         GROUP BY month_year, user_id) AS  B
          ON A.month_year=B.month_year AND A.user_id=B.user_id";

            $executeQuery = app('db')->select($query);
            foreach ($executeQuery as $row => $value) {
                $data = array(
                    "month_year" => $value->month_year,
                    "from_date" => date("Y-m-01", strtotime($value->month_year)),
                    "to_date" => date("Y-m-t", strtotime($value->month_year)),
                    "user_id" => $value->user_id,
                    "technical_account_manager" => $value->technical_account_manager,
                    "division_head" => $value->division_head,
                    "total_unit" => $value->TOTAL,
                    "client_unit" => $value->CLIENT_UNIT,
                    "nonchargeable_unit" => $value->NON_CHARGE,
                    "user_writeoff" => $value->user_writeoff,
                    "created_on" => date('Y-m-d H:i:s')
                );

                $alreadyExist = \App\Models\Backend\TimesheetSummary::where('month_year', $value->month_year)->where('user_id', $value->user_id)->get()->toArray();

                if (count($alreadyExist) > 0)
                    \App\Models\Backend\TimesheetSummary::where('id', $alreadyExist[0]['id'])->update($data);
                else
                    \App\Models\Backend\TimesheetSummary::insert($data);
            }
        } catch (Exception $ex) {
            $cronName = "Timesheet Summary";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
