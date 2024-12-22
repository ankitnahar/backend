<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrBioTimeWorkingFromHome extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:BioTimeWorkingFromHome';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Store bio time for working from home staff';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            echo $today = date("Y-m-d");
            die;
            //$today = "2018-01-20";
            $activeUser = \App\Models\User::select('id', 'user_bio_id', 'shift_id')->where("is_active", 1)->where('shift_id', '!=', 0)->orderBy('id', 'asc')->get();
            $activeUserShift = $activeUser->pluck('shift_id', 'id')->toArray();
            $activeUserBio = $activeUser->pluck('id', 'user_bio_id')->toArray();

            $exceptionShift = \App\Models\Backend\HrExceptionshift::where('start_date', '<=', $today)->where('end_date', '>=', $today)->where("is_active", 1)->orderBy('shift_id', 'asc')->get()->toArray();

            $exceptionShiftData = array();
            foreach ($exceptionShift as $key => $value)
                $exceptionShiftData[$value['shift_id']] = $value;

            $exceptionShiftId = array_keys($exceptionShiftData);
            $normalShift = \App\Models\Backend\HrShift::whereNotin('id', $exceptionShiftId)->where('is_active', 1)->get()->toArray();

            $normalShiftData = array();
            foreach ($normalShift as $keyNormailShift => $valueNormailShift)
                $normalShiftData[$valueNormailShift['id']] = $valueNormailShift;

            $hr_detail = array();
            foreach ($activeUserShift as $keyUser => $valueUser) {
                $userId = $keyUser;
                $shiftId = $valueUser;
                $isSundayOrHoliday = todayisSundayOrHoliday($today, $shiftId);

                if ($isSundayOrHoliday == 'Sunday')
                    $remark = 2;
                else if ($isSundayOrHoliday == 'Holiday')
                    $remark = 1;
                else
                    $remark = 0;

                $data = array();
                $data['user_id'] = $userId;
                $data['shift_id'] = $shiftId;
                $data['date'] = $today;

                if (isset($exceptionShiftData[$valueUser]) && !empty($exceptionShiftData[$valueUser])) {
                    $data['shift_from_time'] = $exceptionShiftData[$shiftId]['from_time'];
                    $data['shift_to_time'] = $exceptionShiftData[$shiftId]['to_time'];
                    $data['grace_period'] = $exceptionShiftData[$shiftId]['grace_period'];
                    $data['late_period'] = $exceptionShiftData[$shiftId]['late_period'];
                    $data['late_allowed_count'] = $exceptionShiftData[$shiftId]['late_allowed_count'];
                    $data['allowed_break'] = $exceptionShiftData[$shiftId]['break_time'];
                } else {
                    $data['shift_from_time'] = $normalShiftData[$shiftId]['from_time'];
                    $data['shift_to_time'] = $normalShiftData[$shiftId]['to_time'];
                    $data['grace_period'] = $normalShiftData[$shiftId]['grace_period'];
                    $data['late_period'] = $normalShiftData[$shiftId]['late_period'];
                    $data['late_allowed_count'] = $normalShiftData[$shiftId]['late_allowed_count'];
                    $data['allowed_break'] = $normalShiftData[$shiftId]['break_time'];
                }
                $data['remark'] = $remark;
                $data['created_by'] = 1;
                $data['created_on'] = date('Y-m-d H:i:s');
                $hr_detail[] = $data;
            }

            \App\Models\Backend\HrDetail::insert($hr_detail);
            $hrDetailId = \App\Models\Backend\HrDetail::where('date', $today)->pluck('id', 'user_id')->toArray();
            $fileName = "/biotime/Giftcity/" . $today . ".csv";
            $connection = \Illuminate\Support\Facades\Storage::disk('ftp');
            if ($connection->exists($fileName)) {
                $csvFile = explode("\n", $connection->get($fileName));
                $punchUser = $withoutPunchUser = array();
                foreach ($csvFile as $key => $line) {
                    $csvRow = explode(",", $line);
                    if (!empty($csvRow) && count($csvRow) == 4) {
                        if ($csvRow[0] == 'CardNo') {
                            continue;
                        } else if ($csvRow[0] == '------') {
                            continue;
                        } else {
                            if (isset($activeUserBio[$csvRow[0]])) {
                                $explodeDateTime = explode(' ', $csvRow[2]);
                                $type = 0;
                                if (trim($csvRow[3]) == 'IN')
                                    $type = 1;

                                $location = 0;
                                if ($csvRow[1] == 1 || $csvRow[1] == 2)
                                    $location = 1;
                                else if ($csvRow[1] == 3 || $csvRow[1] == 4)
                                    $location = 5;
                                else if ($csvRow[1] == 5 || $csvRow[1] == 11 || $csvRow[1] == 6 || $csvRow[1] == 12)
                                    $location = 2;
                                else if ($csvRow[1] == 7 || $csvRow[1] == 8)
                                    $location = 6;
                                else if ($csvRow[1] = 9)
                                    $location = 4;
                                else if ($csvRow[1] == 13 || $csvRow[1] == 14)
                                    $location = 3;

                                $punchUser[$activeUserBio[$csvRow[0]]][$explodeDateTime[0]][$type][] = $time = str_replace('.000', '', $explodeDateTime[1]);
                            } else {
                                $withoutPunchUser[] = $csvRow;
                            }
                        }
                    }
                }

                $alreadyExistInOut = \App\Models\Backend\HrUserInOuttime::where('date', $today)->get()->toArray();
                $alreadyInOut = array();
                foreach ($alreadyExistInOut as $keyInOut => $valueInOut) {
                    $alreadyInOut[$valueInOut['user_id']][$valueInOut['punch_type']][] = $valueInOut['punch_time'];
                }

                foreach ($punchUser as $keyPunchUser => $valuePunchUser) {

                    foreach ($valuePunchUser as $keyUserId => $valueDate) {
                        $newInArray = isset($valueDate[$today][1]) ? $valueDate[$today][1] : array();
                        $newOutArray = isset($valueDate[$today][0]) ? $valueDate[$today][0] : array();

                        $exsitInArray = isset($alreadyInOut[$keyUserId][0]) ? $alreadyInOut[$keyUserId][0] : array();
                        $exsitOutArray = isset($alreadyInOut[$keyUserId][1]) ? $alreadyInOut[$keyUserId][1] : array();

                        $actulInInsert = array_diff($newInArray, $exsitInArray);
                        $actulOutInsert = array_diff($newOutArray, $exsitOutArray);

                        if (!empty($actulInInsert)) {
                            $appendIn = array();
                            foreach ($actulInInsert as $KeyIn => $valueIn) {
                                $data = array();
                                $data['hr_detail_id'] = $hrDetailId[$keyUserId];
                                $data['user_id'] = $keyUserId;
                                $data['date'] = $today;
                                $data['punch_time'] = $valueIn;
                                $data['punch_type'] = 1;
                                $data['office_location'] = $location;
                                $data['created_by'] = 1;
                                $data['created_on'] = date('Y-m-d H:i:s');
                                $appendIn[] = $data;
                            }

                            \App\Models\Backend\HrUserInOuttime::insert($appendIn);
                            $userFirstIn = \App\Models\Backend\HrUserInOuttime::select('punch_time')->where('date', $today)->where('punch_type', 1)->where('user_id', $keyUserId)->orderBy('punch_time', 'ASC')->limit(1)->get();
                            $userFirstInVal = isset($userFirstIn->punch_time) ? $userFirstIn->punch_time : $actulInInsert[0];

                            if (isset($hrDetailId[$keyUserId])) {
                                $updateData['punch_in'] = $userFirstInVal;
                                $updateData['office_location'] = $location;
                                $updateData['remark'] = $remark;
                                $updateData['status'] = 0;
                                $updateData['modified_by'] = 1;
                                $updateData['modified_on'] = date('Y-m-d H:i:s');
                                \App\Models\Backend\HrDetail::where('id', $hrDetailId[$keyUserId])->update($updateData);
                            }
                        }

                        if (!empty($actulOutInsert)) {
                            $appendOut = array();
                            foreach ($actulOutInsert as $KeyIn => $valueIn) {
                                $data = array();
                                $data['hr_detail_id'] = $hrDetailId[$keyUserId];
                                $data['user_id'] = $keyUserId;
                                $data['date'] = $today;
                                $data['punch_time'] = $valueIn;
                                $data['punch_type'] = 0;
                                $data['office_location'] = $location;
                                $data['created_by'] = 1;
                                $data['created_on'] = date('Y-m-d H:i:s');
                                $appendOut[] = $data;
                            }

                            \App\Models\Backend\HrUserInOuttime::insert($appendOut);
                            $userLastOutVal = isset($userLastOut->punch_time) ? $userLastOut->punch_time : $actulOutInsert[0];

                            $WorkingDetail = getWorkingTime($keyUserId, $today);
                            if (isset($hrDetailId[$keyUserId])) {
                                $updateData = array();
                                $updateData['punch_out'] = $WorkingDetail['punch_out'];
                                $updateData['working_time'] = $WorkingDetail['working_time'];
                                $updateData['break_time'] = $WorkingDetail['break_time'];
                                $updateData['modified_by'] = 1;
                                $updateData['modified_on'] = date('Y-m-d H:i:s');
                                \App\Models\Backend\HrDetail::where('id', $hrDetailId[$keyUserId])->update($updateData);
                            }
                        }
                    }
                }
            } else {
                app('log')->error("Error : CSV not exist");
            }
        } catch (Exception $ex) {
            $cronName = "HR Bio Time Working From Home";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
