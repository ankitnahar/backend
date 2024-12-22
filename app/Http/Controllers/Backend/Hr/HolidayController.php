<?php
namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HolidayController extends Controller {

    /**
     * Get Holiday detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
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
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'hr_holiday.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];

        $holiday = \App\Models\Backend\HrHoliday::with('created_by:userfullname as created_by,id', 'modified_by:userfullname as modified_by,id')
                ->where("is_active", "1");
        //for search
        if ($request->has('search')) {
            $search = $request->get('search');
            $holiday = search($holiday, $search);
        }
        // for relation ship sorting
        if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
            $holiday = $holiday->leftjoin("user as u", "u.id", "hr_holiday.$sortBy");
            $sortBy = 'userfullname';
        }

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
            $totalRecords = $holiday->count();

            $holiday = $holiday->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $holiday = $holiday->get();

            $filteredRecords = count($holiday);

            $pager = ['sortBy' => $sortByName,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }
        //For download excel if excel =1
        if ($request->has('excel') && $request->get('excel') == 1) {
            //format data in array 
            $data = $holiday->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Date', 'Year', 'Description', 'Created on', 'Created By', 'Modified On', 'Modified By'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $columnData[] = $i;
                    $columnData[] = $data['date'];
                    $columnData[] = $data['year'];
                    $columnData[] = $data['description'];
                    $columnData[] = $data['created_on'];
                    $columnData[] = $data['created_by']['created_by'];
                    $columnData[] = $data['modified_on'];
                    $columnData[] = $data['modified_by']['modified_by'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'HolidayList', 'xlsx', 'A1:H1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Holiday list.", ['data' => $holiday], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Holiday listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Holiday", ['error' => 'Server error.']);
          } */
    }

    public function storeholidaywithcsv(Request $request) {
        // try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'upload' => 'required|mimes:csv,vnd.ms-excel,txt',
        ]);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);
        //Get current user detail
        $loginUser = loginUser();

        $file = $request->file('upload');
        if (!empty($file)) {
            $filename = $file->getPathname();
            $row = 1;
            if (($handle = fopen($filename, "r")) !== FALSE) {
                $i = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $num = count($data);
                    $row++;
 
                    for ($c = 0; $c < $num; $c++) {
                        $date = "";
                        if (isset($data[0])) {
                            $date = rtrim($data[0]);
                            $date = date('Y-m-d',strtotime($date));
                        }
                        $year = "";
                        if (isset($data[1])) {
                            $year = rtrim($data[1]);
                        }
                        $client = "";
                        if (isset($data[2])) {
                            $client = rtrim($data[2]);
                        }
                        $description = "";
                        if (isset($data[2])) {
                            $description = rtrim($data[2]);
                        }
                    }
                    if ($date != '0000-00-00') {
                        $checkholiday = \App\Models\Backend\HrHoliday::where("date", $date)->where("year", $year);
                        if ($checkholiday->count() == 0) {
                            $holidayList = \App\Models\Backend\HrHoliday::create([
                                        'date' => $date,
                                        'year' => $year,
                                        'is_client' => $client,
                                        'description' => $description,
                                        'created_on' => date('Y-m-d H:i:s'),
                                        'created_by' => $loginUser
                            ]);
                        }
                    }
                }
                fclose($handle);
            }
        }

        return createResponse(config('httpResponse.SUCCESS'), 'Email List has been added successfully.', ['message' => 'Email List has been added successfully.']);
        /* } catch (\Exception $e) {
          app('log')->error("Email List failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Email List.', ['error' => 'Could not add Email List.']);
          } */
    }

    /**
     * Store holiday details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        //  try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'date' => 'required|date',
            'year' => 'required|date_format:Y',
            'is_client' => 'required|in:1,0',
            'is_active' => 'in:0,1',
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        // store holiday details
        $loginUser = loginUser();
        $holiday = \App\Models\Backend\HrHoliday::create([
                    'date' => $request->input('date'),
                    'year' => $request->input('year'),
                    'is_client' => $request->input('is_client'),
                    'description' => $request->input('description'),
                    'is_active' => 1,
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => $loginUser,
                    'modified_on' => date('Y-m-d H:i:s'),
                    'modified_by' => $loginUser
        ]);

        return createResponse(config('httpResponse.SUCCESS'), 'Holiday has been added successfully', ['data' => $holiday]);
        /* } catch (\Exception $e) {
          app('log')->error("Holiday creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add holiday', ['error' => 'Could not add holiday']);
          } */
    }

    /**
     * update Holiday details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // holiday id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        //  try {
        $validator = app('validator')->make($request->all(), [
            'date' => 'date',
            'year' => 'date_format:Y',
            'is_client' => 'required|in:1,0',
            'is_active' => 'in:0,1',
                ], ['holiday_name.unique' => "Holiday Name has already been taken"]);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
        $holiday = \App\Models\Backend\HrHoliday::find($id);

        if (!$holiday)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Holiday does not exist', ['error' => 'The Holiday does not exist']);

        $updateData = array();
        // Filter the fields which need to be updated
        $loginUser = loginUser();
        $updateData = filterFields(['date', 'year','is_client', 'description', 'is_active'], $request);
        $updateData['modified_on'] = date('Y-m-d H:i:s');
        $updateData['modified_by'] = $loginUser;
        //update the details
        $holiday->update($updateData);

        return createResponse(config('httpResponse.SUCCESS'), 'Holiday has been updated successfully', ['message' => 'Holiday has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Holiday updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update holiday details.', ['error' => 'Could not update holiday details.']);
          } */
    }

    /**
     * get particular holiday details
     *
     * @param  int  $id   //holiday id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $holiday = \App\Models\Backend\HrHoliday::with('created_by:userfullname as created_by,id', 'modified_by:userfullname as modified_by,id')->find($id);

            if (!isset($holiday))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The holiday does not exist', ['error' => 'The holiday does not exist']);

            //send holiday information
            return createResponse(config('httpResponse.SUCCESS'), 'Holiday data', ['data' => $holiday]);
        } catch (\Exception $e) {
            app('log')->error("Holiday details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get holiday.', ['error' => 'Could not get holiday.']);
        }
    }

    public function destroy($id) {
        try {
            $holiday = \App\Models\Backend\HrHoliday::find($id);

            if (!isset($holiday))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The holiday does not exist', ['error' => 'The holiday does not exist']);

            $holiday->delete();
            //send holiday information
            return createResponse(config('httpResponse.SUCCESS'), 'Holiday data delete sucessfully ', ['data' => 'Holiday data delete sucessfully']);
        } catch (\Exception $e) {
            app('log')->error("Holiday details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get holiday.', ['error' => 'Could not get holiday.']);
        }
    }
    
    public function userHolidayDetail($id){
        try {
            $date = date('Y-m-d');
            $year = date('Y');
            $holiday = \App\Models\Backend\HrHolidayDetail::leftjoin("hr_holiday as h","h.id","hr_holiday_detail.hr_holiday_id")
                    ->select("h.*")
                    ->where("hr_holiday_detail.shift_id",$id)
                    ->where("year",$year)->where("h.date",">",$date)->orderBy("date","asc")->get();
           
            return createResponse(config('httpResponse.SUCCESS'), 'User wise Holiday Detail', ['data' => $holiday]);
        } catch (\Exception $e) {
            app('log')->error("Holiday details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get holiday.', ['error' => 'Could not get holiday.']);
        }
    }

}
