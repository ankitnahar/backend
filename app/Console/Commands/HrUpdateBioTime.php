<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrUpdateBioTime extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:updatebiotime';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update bio machine time if in out count miss match';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            //$yesterDay = date("Y-m-d", strtotime("-1 days"));
            $yesterDay = date("Y-m-d", strtotime("-1 days"));
            $inOutTime = \App\Models\Backend\HrUserInOuttime::where('date', $yesterDay)->orderBy('punch_time', 'asc')->orderBy('id', 'asc')->get()->toArray();
            $arrangeData = array();
            foreach ($inOutTime as $key => $value) {
                $arrangeData[$value['user_id']][] = $value;
            }

            foreach ($arrangeData as $key => $value) {
                for ($i = 0; $i < count($value); $i++) {
                    $userData[$key][] = $value[$i];
                    if (count($value) == 1) {
                        $value[$i]['id'] = '';
                        $value[$i]['punch_type'] = $value[$i]['punch_type'] == 1 ? 0 : 1;
                        $value[$i]['punch_time'] = $value[$i]['punch_time'];
                        $userData[$key][] = $value[$i];
                    } else if (isset($value[$i + 1]['punch_type']) && $value[$i + 1]['punch_type'] == $value[$i]['punch_type']) {
                        $j = $i + 1;
                        $previous = strtotime($value[$i]['punch_time']);
                        $next = strtotime($value[$j]['punch_time']);
                        $diff = strtotime($next - $previous);
                        $minutes = round(((($diff % 604800) % 86400) % 3600) / 60);

                        if ($minutes < 2) {
                            $value[$i]['id'] = '';
                            $value[$i]['punch_type'] = $value[$j]['punch_type'] == 1 ? 0 : 1;
                            $userData[$key][] = $value[$i];
                        } else {
                            $value[$i]['id'] = '';
                            $value[$i]['punch_type'] = $value[$j]['punch_type'] == 1 ? 0 : 1;
                            $value[$i]['punch_time'] = $value[$j]['punch_time'];
                            $userData[$key][] = $value[$i];
                        }
                    } else if ($value[$i]['punch_type'] == 1 && (count($value) - 1) == $i) {
                        $value[$i]['id'] = '';
                        $value[$i]['punch_type'] = 0;
                        $value[$i]['punch_time'] = $value[$i]['punch_time'];
                        $userData[$key][] = $value[$i];
                    }
                }
            }

            \App\Models\Backend\HrUserInOuttime::where('date', $yesterDay)->delete();

            $inOutData = array();
            foreach ($userData as $keyData => $valueData) {
                foreach ($valueData as $keyQuery => $valueQuery) {
                    $rawData = array();
                    $type = $valueQuery['id'] != '' ? '1' : '0';
                    $rawData['hr_detail_id'] = $valueQuery['hr_detail_id'];
                    $rawData['user_id'] = $valueQuery['user_id'];
                    $rawData['date'] = $valueQuery['date'];
                    $rawData['punch_time'] = $valueQuery['punch_time'];
                    $rawData['punch_type'] = $valueQuery['punch_type'];
                    $rawData['type'] = $type;
                    $rawData['office_location'] = $valueQuery['office_location'];
                    $rawData['created_on'] = $valueQuery['created_on'];
                    $rawData['created_by'] = $valueQuery['created_by'];
                    $inOutData[] = $rawData;
                }
            }
            
            $isInsertedData = \App\Models\Backend\HrUserInOuttime::insert($inOutData);
            
            /**
             * Modified By - Alok Shukla
             * Modified On - 25-04-2019
             * Used - After updating or insert automatic bio time for mismatch data need to recalculate working time & break time
             */
            if($isInsertedData == 1) {
                $hrDetailId = \App\Models\Backend\HrDetail::where('date', $yesterDay)->where('punch_in', '!=', '')->pluck('id', 'user_id')->toArray();
                if($hrDetailId) {
                    foreach ($hrDetailId as $key => $val) {
                        if ($key > 0) {
                            $WorkingDetail = getWorkingTime($key, $yesterDay);
                            $updateData = array();
                            $updateData['punch_out'] = $WorkingDetail['punch_out'];
                            $updateData['working_time'] = $WorkingDetail['working_time'];
                            $updateData['break_time'] = $WorkingDetail['break_time'];
                            $updateData['modified_by'] = 1;
                            $updateData['modified_on'] = date('Y-m-d H:i:s');
                            \App\Models\Backend\HrDetail::where('id', $val)->update($updateData);
                        }
                    }
                }
            }
        } catch (Exception $ex) {
           $cronName = "HR Update Bio Time";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
