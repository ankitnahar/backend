<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class HrAddSturdayInHoliday extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:sat';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add Saturday';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $day = date('d');
            if ($day == '15') {
                $year = date("Y"); //You can add custom year also like $year=1997 etc.
                $dateSun = getSaturday($year . '-01-01', $year . '-12-31', 6);
                $sundayArray = array();
                foreach ($dateSun as $index => $date) {
                    $date = date('Y-m-d', strtotime($date));
                    $checkHoliday = \App\Models\Backend\HrHoliday::where("date", $date)->where("Year", $year);
                    if ($checkHoliday->count() == 0) {
                        $holidayList = \App\Models\Backend\HrHoliday::create([
                                    'date' => $date,
                                    'year' => $year,
                                    'is_client' => 0,
                                    'description' => "Staurday",
                                    'created_on' => date('Y-m-d H:i:s'),
                                    'created_by' => 1
                        ]);
                        $holidayId = $holidayList->id;
                    } else {
                        $holidayId = $checkHoliday->id;
                    }
                    // check holiday and 
                    $shiftDetail = \App\Models\Backend\HrShift::where("sat_off", "!=", "0")->get();
                    foreach ($shiftDetail as $s) {
                        $sat = $s->sat_off;
                        $checkshifthoilday = \App\Models\Backend\HrHolidayDetail::where("shift_id", $s->id)->where("date", $date);
                        if ($checkshifthoilday->count() == 0) {
                            if ($sat == 2) {
                                $month = date('m', strtotime($date));
                                $firstSat = date('Y-m-d', strtotime('first Saturday', strtotime("$month $year")));
                                $thirdSat = date('Y-m-d', strtotime('third Saturday', strtotime("$month $year")));
                                if ($date == $firstSat || $date == $thirdSat) {

                                    \App\Models\Backend\HrHolidayDetail::create([
                                        'hr_holiday_id' => $checkHoliday->id,
                                        'shift_id' => $shiftId,
                                        'created_on' => date('Y-m-d h:i:s'),
                                        "created_by" => loginUser()
                                    ]);
                                }
                            } else {

                                \App\Models\Backend\HrHolidayDetail::create([
                                    'hr_holiday_id' => $checkHoliday->id,
                                    'shift_id' => $shiftId,
                                    'created_on' => date('Y-m-d h:i:s'),
                                    "created_by" => loginUser()
                                ]);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $cronName = "Audit Invoice";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}

function getSaturday($startDt, $endDt, $weekNum) {
    $startDt = strtotime($startDt);
    $endDt = strtotime($endDt);
    $dateSun = array();
    do {
        if (date("w", $startDt) != $weekNum) {
            $startDt += (24 * 3600); // add 1 day
        }
    } while (date("w", $startDt) != $weekNum);
    while ($startDt <= $endDt) {
        $dateSun[] = date('d-m-Y', $startDt);
        $startDt += (7 * 24 * 3600); // add 7 days
    }
    return($dateSun);
}
