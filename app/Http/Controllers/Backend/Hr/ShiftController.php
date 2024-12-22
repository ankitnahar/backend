<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\HrShift;

class ShiftController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Fetch shift data
     */

    public function index(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $shift = HrShift::with('created_by:id,userfullname')->with('modified_by:id,userfullname');

            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $shift = $shift->leftjoin("user as u", "u.id", "hr_shift_master.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $shift = search($shift, $search);
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $shift = $shift->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $shift->count();
                $shift = $shift->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                //echo $shift->toSql(); die;
                $shift = $shift->get();
                $filteredRecords = count($shift);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            if ($request->has('excel') && $request->get('excel') == 1) {
                $data = $shift->toArray();
                $column = array();
                $staoff = config('constant.hrsat');
                $column[] = ['Sr.No', 'Shift Name', 'Shift Start Time', 'Shift End Time', 'Grace Period', 'Consider Late Period', 'Late Coming Allowed Count', 'Break Time', 'Saturday off', 'Note', 'Created By', 'Created On', 'Modified By', 'Modified On'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['shift_name'];
                        $columnData[] = $data['from_time'];
                        $columnData[] = $data['to_time'];
                        $columnData[] = $data['grace_period'];
                        $columnData[] = $data['late_period'];
                        $columnData[] = $data['late_allowed_count'];
                        $columnData[] = $data['break_time'];
                        $columnData[] = $data['break_time'];
                        $columnData[] = $staoff[$data['sat_off']];
                        $columnData[] = isset($data['created_by']['userfullname']) ? $data['created_by']['userfullname'] : '-';
                        $columnData[] = $data['created_on'];
                        $columnData[] = isset($data['modified_by']['userfullname']) ? $data['modified_by']['userfullname'] : '-';
                        $columnData[] = $data['modified_on'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Shift', 'xlsx', 'A1:N1');
            }

            return createResponse(config('httpResponse.SUCCESS'), "Shift list.", ['data' => $shift], $pager);
        } catch (\Exception $e) {
            app('log')->error("Shift listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing shift list", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Store shift data
     */

    public function store(Request $request) {
        // try {
        //validate request parameters
        $validator = $this->validateInput($request);
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);


        // store shift  details
        $shift = HrShift::create(['shift_name' => $request->get('shift_name'),
                    'from_time' => $request->get('from_time'),
                    'to_time' => $request->get('to_time'),
                    'grace_period' => $request->get('grace_period'),
                    'late_period' => $request->get('late_period'),
                    'late_allowed_count' => $request->get('late_allowed_count'),
                    'break_time' => $request->get('break_time'),
                    'sat_off' => $request->get('sat_off'),
                    'description' => $request->get('description'),
                    'is_active' => $request->get('is_active'),
                    'is_visible' => $request->get('is_active'),
                    'sort_order' => $request->get('sort_order'),
                    'created_by' => app('auth')->guard()->id(),
                    'created_on' => date('Y-m-d H:i:s')]);

        if ($request->get('sat_off') > 0) {
            self::addSaturday($shift->id, $request->get('sat_off'));
        }
        $holidayShift = $request->get('holiday_shift_id');
        $year = date('Y');
        $holidayDetail = \App\Models\Backend\HrHolidayDetail::leftjoin("hr_holiday as h", "h.id", "hr_holiday_detail.hr_holiday_id")->select("hr_holiday_detail.hr_holiday_id")
                        ->where('hr_holiday_detail.shift_id', $holidayShift)->where("h.year", $year)->get()->toArray();
        //$hrHolidayDetail = array();
        if (count($holidayDetail) > 0) {
            foreach ($holidayDetail as $h) {
                 $holidayDetail = \App\Models\Backend\HrHolidayDetail::where('shift_id', $holidayShift)->where('hr_holiday_id',$h['hr_holiday_id']);
                        if ($holidayDetail->count() == 0) {
                \App\Models\Backend\HrHolidayDetail::create(['hr_holiday_id' => $h['hr_holiday_id'],
                    'shift_id' => $shift->id,
                    'created_by' => app('auth')->guard()->id(),
                    'created_on' => date('Y-m-d H:i:s')]);
                        }
            }
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Shift has been added successfully', ['data' => $shift]);
        /* } catch (\Exception $e) {
          app('log')->error("Shift  creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add shift ', ['error' => 'Could not add shift ']);
          } */
    }

    public static function addSaturday($shiftId, $sat) {
        $year = date("Y"); //You can add custom year also like $year=1997 etc.
        $dateSun = getSaturday($year . '-01-01', $year . '-12-31', 6);
        $sundayArray = array();
        foreach ($dateSun as $index => $date) {
            $date = date('Y-m-d', strtotime($date));
            $checkHoliday = \App\Models\Backend\HrHoliday::where("date", $date)->where("Year", $year);
            if ($checkHoliday->count() > 0) {
                $checkHoliday = $checkHoliday->first();
                if ($sat == 2) {
                    $month = date('m', strtotime($date));
                    $firstSat = date('Y-m-d', strtotime('first Saturday', strtotime("$month $year")));
                    $thirdSat = date('Y-m-d', strtotime('third Saturday', strtotime("$month $year")));
                    if ($date == $firstSat || $date == $thirdSat) {
                        $holidayDetail = \App\Models\Backend\HrHolidayDetail::where('shift_id', $shiftId)->where('hr_holiday_id', $checkHoliday->id);
                        if ($holidayDetail->count() == 0) {
                            \App\Models\Backend\HrHolidayDetail::create([
                                'hr_holiday_id' => $checkHoliday->id,
                                'shift_id' => $shiftId,
                                'created_on' => date('Y-m-d h:i:s'),
                                "created_by" => loginUser()
                            ]);
                        }
                    }
                } else {
                    $holidayDetail = \App\Models\Backend\HrHolidayDetail::where('shift_id', $shiftId)->where('hr_holiday_id', $checkHoliday->id);
                    if ($holidayDetail->count() == 0) {
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

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Show shift data
     */

    public function show($id) {
        try {
            $shift = HrShift::where('id', $id)->get();
            if (!isset($shift))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The shift does not exist', ['error' => 'The shift does not exist']);

            $shift = HrShift::with('created_by:id,userfullname')->with('modified_by:id,userfullname')->find($id);
            //send shift information
            return createResponse(config('httpResponse.SUCCESS'), 'Shift  data', ['data' => $shift]);
        } catch (\Exception $e) {
            app('log')->error("Shift details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get shift.', ['error' => 'Could not get shift.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Show shift data
     */

    public function update(Request $request, $id) {
        //try {
        $shift = HrShift::find($id);
        if ($request->get('actionType') == 0) {
            $validator = app('validator')->make($request->all(), [
                'from_time' => 'date_format:H:i',
                'to_time' => 'date_format:H:i',
                'grace_period' => 'date_format:H:i',
                'late_period' => 'date_format:H:i',
                'late_allowed_count' => 'numeric',
                'break_time' => 'date_format:H:i',
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);
        }else {
            $shift->is_active = 0;
        }

        if (!$shift)
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Shift does not exist', ['error' => 'The Shift does not exist']);

        $updateData = array();

        if ($request->get('actionType') == 1) {
            if ($request->get('type') == 1) {
                $userId = \App\Models\User::where('shift_id', $id)->pluck('id', 'id')->toArray();
                \App\Models\User::whereIn('id', $userId)->where('shift_id', $id)->update(['shift_id' => $request->get('shift_id')]);
            } else {
                $userDetail = \GuzzleHttp\json_decode($request->get('usershiftDetail'), true);
                foreach ($userDetail as $key => $value) {
                    \App\Models\User::where('id', $value['id'])->where('shift_id', $value['old_shift_id'])->update(['shift_id' => $value['new_shift_id']]);
                }
            }
        }
        // Filter the fields which need to be updated
        $shift->modified_on = date('Y-m-d H:i:s');
        $shift->modified_by = app('auth')->guard()->id();
        $shift->sat_off = $request->input('sat_off');
        $updateData = filterFields(['shift_name', 'from_time', 'to_time', 'grace_period', 'late_period', 'late_allowed_count', 'break_time', 'description', 'is_active', 'sort_order'], $request);

        //update the details
        $shift->update($updateData);
        if ($request->get('sat_off') > 0) {
            self::addSaturday($id, $request->get('sat_off'));
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Shift has been updated successfully', ['message' => 'Shift has been updated successfully']);
//        } catch (\Exception $e) {
//            app('log')->error("Shift updation failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update shift details.', ['error' => 'Could not update shift details.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Make shift In active.
     */

    public function destroy(Request $request, $id) {
        try {
            $shift = HrShift::find($id);
            if (!$shift)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Shift does not exist', ['error' => 'The Shift does not exist']);

            if ($request->get('type') == 1) {
                $userId = \App\Models\User::where('shift_id', $id)->pluck('id', 'id')->toArray();
                \App\Models\User::whereIn('id', $userId)->where('shift_id', $id)->update(['shift_id' => $request->get('shift_id')]);
            } else {
                $userDetail = \GuzzleHttp\json_decode($request->get('usershiftDetail'), true);
                foreach ($userDetail as $key => $value) {
                    \App\Models\User::where('id', $value['id'])->where('shift_id', $value['old_shift_id'])->update(['shift_id' => $value['new_shift_id']]);
                }
            }

            // Filter the fields which need to be updated
//            $shift->is_active = 0;
//            $shift->modified_on = date('Y-m-d H:i:s');
//            $shift->modified_by = app('auth')->guard()->id();
            //update the details
            $shift->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Shift has been deleted successfully', ['message' => 'Shift has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted shift details.', ['error' => 'Could not deleted shift details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate user input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'shift_name' => 'required',
            'from_time' => 'required|date_format:H:i',
            'to_time' => 'required|date_format:H:i',
            'grace_period' => 'required|date_format:H:i',
            'late_period' => 'required|date_format:H:i',
            'late_allowed_count' => 'required|numeric',
            'break_time' => 'required|date_format:H:i',
            'is_active' => 'required',
                ], ['is_active.required' => 'Shift status is required']);
        return $validator;
    }

}
