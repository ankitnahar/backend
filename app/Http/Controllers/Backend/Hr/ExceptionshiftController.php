<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\HrExceptionshift;

class ExceptionshiftController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Fetch exception shift data
     */

    public function index(Request $request) {
        //try {
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
            if($sortBy == 'id')
               $sortBy = 'hr_exception_shift.id';
            
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $exceptionShift = HrExceptionshift::select('hr_exception_shift.*')->with('shift_id:id,shift_name','created_by:id,userfullname','modified_by:id,userfullname');
            $exceptionShift = $exceptionShift->leftjoin("hr_shift_master as hsm", "hsm.id", "hr_exception_shift.shift_id");
            
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $exceptionShift = $exceptionShift->leftjoin("user as u", "u.id", "hr_exception_shift.$sortBy");
                $sortBy = 'userfullname';
            }
            
            if ($request->has('search')) {
                $search = $request->get('search');
                $exceptionShift = search($exceptionShift, $search);
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $exceptionShift = $exceptionShift->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $exceptionShift->count();
                $exceptionShift = $exceptionShift->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                //echo $exceptionShift->toSql(); die;
                $exceptionShift = $exceptionShift->get();
                $filteredRecords = count($exceptionShift);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            if ($request->has('excel') && $request->get('excel') == 1) {
                $data = $exceptionShift->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Shift Name', 'Start Date', 'End Date', 'Shift Start Time', 'Shift End Time', 'Grace Period', 'Consider Late Period', 'Late Coming Allowed Count', 'Break Time', 'Note', 'Status', 'Created By', 'Created On', 'Modified By', 'Modified On'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['shift_id']['shift_name'];
                        $columnData[] = dateFormat($data['start_date']);
                        $columnData[] = dateFormat($data['end_date']);
                        $columnData[] = $data['from_time'];
                        $columnData[] = $data['to_time'];
                        $columnData[] = $data['grace_period'];
                        $columnData[] = $data['late_period'];
                        $columnData[] = $data['late_allowed_count'];
                        $columnData[] = $data['break_time'];
                        $columnData[] = $data['description'];
                        $columnData[] = $data['is_active'] == 1 ? 'Active' : 'Inactive';
                        $columnData[] = isset($data['created_by']['userfullname']) ? $data['created_by']['userfullname'] : '';
                        $columnData[] = $data['created_on'];
                        $columnData[] = isset($data['modified_by']['userfullname']) ? $data['modified_by']['userfullname'] : '-';
                        $columnData[] = $data['modified_on'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                
                return exportExcelsheet($column, 'Exceptionshift', 'xlsx', 'A1:P1');
            }

            return createResponse(config('httpResponse.SUCCESS'), "Exception shift list.", ['data' => $exceptionShift], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Exceptionshift listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing exception shift list", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Store exception shift data
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = $this->validateInput($request);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store exception shift  details
            $exceptionShift = HrExceptionshift::create(['shift_id' => $request->get('shift_id'),
                        'user_id' => !empty($request->get('user_id')) ? implode(",",$request->get('user_id')):'',
                        'start_date' => $request->get('start_date'),
                        'end_date' => $request->get('end_date'),
                        'from_time' => $request->get('from_time'),
                        'to_time' => $request->get('to_time'),
                        'grace_period' => $request->get('grace_period'),
                        'late_period' => $request->get('late_period'),
                        'late_allowed_count' => $request->get('late_allowed_count'),                
                        'break_time' => $request->get('break_time'),
                        'description' => $request->get('description'),
                        'is_active' => $request->get('is_active'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);

            return createResponse(config('httpResponse.SUCCESS'), 'Exception shift has been added successfully', ['data' => $exceptionShift]);
        } catch (\Exception $e) {
            app('log')->error("Exceptionshift  creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add exception shift ', ['error' => 'Could not add exception shift ']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Show exception shift data
     */

    public function show($id) {
        try {
            $exceptionShift = HrExceptionshift::where('id', $id)
                            ->with('shift_id:id,shift_name,from_time,to_time,grace_period,late_period,late_allowed_count,break_time')
                            ->with('created_by:id,userfullname')
                            ->with('modified_by:id,userfullname')->get();

            if (!isset($exceptionShift))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The exception shift does not exist', ['error' => 'The exception shift does not exist']);

            //send exception shift information
            return createResponse(config('httpResponse.SUCCESS'), 'Exceptionshift  data', ['data' => $exceptionShift]);
        } catch (\Exception $e) {
            app('log')->error("Exceptionshift details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get exception shift.', ['error' => 'Could not get exception shift.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Show exception shift data
     */

    public function update(Request $request, $id) {
        //try {
            $validator = $this->validateInput($request);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $exceptionShift = HrExceptionshift::find($id);

            if (!$exceptionShift)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Exceptionshift does not exist', ['error' => 'The Exceptionshift does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $exceptionShift->modified_on = date('Y-m-d H:i:s');
            $exceptionShift->modified_by = app('auth')->guard()->id();
            $exceptionShift->user_id = !empty($request->input('user_id')) ? implode(",",$request->input('user_id')):'';
            $updateData = filterFields(['shift_id', 'start_date', 'end_date', 'from_time', 'to_time', 'grace_period', 'late_period', 'late_allowed_count', 'break_time', 'description', 'is_active'], $request);

            $exceptionShift->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Exception shift has been updated successfully', ['message' => 'Exception shift has been updated successfully']);
        /*} catch (\Exception $e) {
            app('log')->error("Exceptionshift updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update exception shift details.', ['error' => 'Could not update exception shift details.']);
        }*/
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Make exception shift In active.
     */

    public function destroy(Request $request, $id) {
        try {

            $exceptionShift = HrExceptionshift::find($id);
            if (!$exceptionShift)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Exceptionshift does not exist', ['error' => 'The Exceptionshift does not exist']);
            // Filter the fields which need to be updated
//            $exceptionShift->is_active = 0;
//            $exceptionShift->modified_on = date('Y-m-d H:i:s');
//            $exceptionShift->modified_by = app('auth')->guard()->id();

            //update the details
            $exceptionShift->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Exception shift has been deleted successfully', ['message' => 'Exception shift has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted exception shift details.', ['error' => 'Could not deleted exception shift details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate user input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'shift_id' => 'numeric',
            'start_date' => 'date_format:Y-m-d',
            'end_date' => 'date_format:Y-m-d',
                ], ['shift_id.numeric' => 'The shift must be numberic']);
        return $validator;
    }

}
