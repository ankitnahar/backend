<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HolidayControllerDetail extends Controller {    

    public function index(Request $request) {
        // try {
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
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'hr_holiday.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        //$holiday = HrHoliday::with('created_by:id,userfullname')->with('modified_by:id,userfullname')->with('shiftdetails:id,hr_holiday_id,shift_id');
        $holiday = \App\Models\Backend\HrHoliday::select('hr_holiday.*',"hr_holiday.id as holiday_id", app('db')->raw('GROUP_CONCAT(hs.id) AS shift_id'), app('db')->raw('GROUP_CONCAT(hs.shift_name) AS shift_name'), app('db')->raw("CONCAT('January', '-', YEAR, ' To ', 'December', '-', YEAR) AS holiday_year"))->with('created_by:id,userfullname')->with('modified_by:id,userfullname');
        $holiday = $holiday->leftJoin('hr_holiday_detail AS hd', 'hd.hr_holiday_id', '=', 'hr_holiday.id')
                ->leftJoin('hr_shift_master AS hs', 'hs.id', '=', 'hd.shift_id');

        if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
            $holiday = $holiday->leftjoin("user as u", "u.id", "hr_holiday.$sortBy");
            $sortBy = 'userfullname';
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $holiday = search($holiday, $search);
        }

        $holiday = $holiday->groupBy('date');
        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $holiday = $holiday->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            //$totalRecords = $holiday->count();
            $totalRecords = count($holiday->get());
            $holiday = $holiday->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);
            //echo $holiday->toSql(); die;
            $holiday = $holiday->get(['hs.*']);
            $filteredRecords = count($holiday);

            $pager = ['sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        // $holiday = HrHoliday::arrangeData($holiday);
        if ($request->has('excel') && $request->get('excel') == 1) {
            $data = $holiday->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Holiday Date', 'Year', 'Shift Name', 'Notes', 'Created By', 'Created On', 'Modified By', 'Modified On'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
//                    $shiftDetail = array();
//                    foreach ($data['shiftdetails'] as $key => $value)
//                        $shiftDetail[] = $value['shift_id']['shift_name'];

                    $columnData[] = $i;
                    $columnData[] = dateFormat($data['date']);
                    $columnData[] = $data['holiday_year'];
                    $columnData[] = $data['shift_name']; //implode(', ', $shiftDetail);
                    $columnData[] = $data['description'];
                    $columnData[] = $data['created_by']['userfullname'];
                    $columnData[] = $data['created_on'];
                    $columnData[] = $data['modified_by']['userfullname'] != '' ? $data['modified_by']['userfullname'] : '-';
                    $columnData[] = $data['modified_on'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'Holiday', 'xlsx', 'A1:I1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "Holiday list.", ['data' => $holiday], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Holiday listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing holiday list", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Store holiday data
     */

    public function store(Request $request) {
        //try {
        //validate request parameters
        $validator = $validator = app('validator')->make($request->all(), [
            'holiday_id' => 'required',            
            'is_active' => 'required|numeric',
            'shift_id' =>  'required',
                ], []);
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);
        
            $hr_holiday_id = explode(",",$request->get('holiday_id'));
            
            
            $newShift = $request->get('shift_id');
            
            \App\Models\Backend\HrHolidayDetail::where('shift_id', $newShift)->whereNotIn('hr_holiday_id', $hr_holiday_id)->delete();
        
        $hrHolidayDetail = array();
        foreach ($hr_holiday_id as $key => $value) {            
             $checkshift=\App\Models\Backend\HrHolidayDetail::where('shift_id', $newShift)->where('hr_holiday_id', $value)->count();
             if($checkshift == 0){
            $hrHolidayDetail[] = ['hr_holiday_id' => $value,
                'shift_id' => $newShift,
                'created_by' => app('auth')->guard()->id(),
                'created_on' => date('Y-m-d H:i:s')];
             }
        }

        \App\Models\Backend\HrHolidayDetail::insert($hrHolidayDetail);

        $holidayDetail = \App\Models\Backend\HrHoliday::with('created_by')->with('shiftdetails')->where('id', $hr_holiday_id)->get();
        return createResponse(config('httpResponse.SUCCESS'), 'Holiday  has been added successfully', ['data' => $holidayDetail]);
        /* } catch (\Exception $e) {
          app('log')->error("Holiday  creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add holiday ', ['error' => 'Could not add holiday ']);
          } */
    }
    
     public function update(Request $request,$id) {
        //try {
        //validate request parameters
        $validator = $this->validateInput($request);
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);
        
            $hr_holiday_id = $request->get('holiday_id');  
            $newShift = explode(",",$request->get('shift_id'));
            
            \App\Models\Backend\HrHolidayDetail::whereNotIn('shift_id', $newShift)->where('hr_holiday_id', $hr_holiday_id)->delete();
        
        $hrHolidayDetail = array();
        foreach ($newShift as $key => $value) {
            
             $checkshift=\App\Models\Backend\HrHolidayDetail::where('shift_id', $value)->where('hr_holiday_id', $hr_holiday_id)->count();
             if($checkshift == 0){
            $hrHolidayDetail[] = ['hr_holiday_id' => $hr_holiday_id,
                'shift_id' => $value,
                'created_by' => app('auth')->guard()->id(),
                'created_on' => date('Y-m-d H:i:s')];
             }
        }

        \App\Models\Backend\HrHolidayDetail::insert($hrHolidayDetail);

        $holidayDetail = \App\Models\Backend\HrHoliday::with('created_by')->with('shiftdetails')->where('id', $hr_holiday_id)->get();
        return createResponse(config('httpResponse.SUCCESS'), 'Holiday  has been added successfully', ['data' => $holidayDetail]);
        /* } catch (\Exception $e) {
          app('log')->error("Holiday  creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add holiday ', ['error' => 'Could not add holiday ']);
          } */
    }

    public function show($id) {
        try {
            $holiday = \App\Models\Backend\HrHoliday::with('created_by:id,userfullname')->with('modified_by:id,userfullname')->with('shiftdetails:id,hr_holiday_id,shift_id')->find($id);

            if (!isset($holiday))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The holiday does not exist', ['error' => 'The holiday does not exist']);

            //send holiday information
            return createResponse(config('httpResponse.SUCCESS'), 'Holiday  data', ['data' => $holiday]);
        } catch (\Exception $e) {
            app('log')->error("Holiday details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get holiday.', ['error' => 'Could not get holiday.']);
        }
    }

    public function destroy(Request $request, $id) {
        try {
            // If validation fails then return error response
            if (!is_numeric($id))
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => 'Please provice numeric id']);

            $holiday = \App\Models\Backend\HrHoliday::find($id);
            if (!$holiday)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Holiday does not exist', ['error' => 'The Holiday does not exist']);

            // Filter the fields which need to be updated
//            $holiday->is_active = 0;
//            $holiday->modified_on = date('Y-m-d H:i:s');
//            $holiday->modified_by = app('auth')->guard()->id();
//
//            //update the details
//            $holiday->update();
            \App\Models\Backend\HrHolidayDetail::where('hr_holiday_id', $holiday->id)->delete();
            $holiday->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Holiday has been deleted successfully', ['message' => 'Holiday has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted holiday details.', ['error' => 'Could not deleted holiday details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate user input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'holiday_id' => 'required',            
            'is_active' => 'required|numeric'
                ], ['date.date_format' => 'The holiday date format is not valid',
            'year.date_format' => 'The holiday year format is not valid']);
        return $validator;
    }
    
   
}
