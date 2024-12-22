<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\HrLocation;

class LocationController extends Controller {
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

            $location = HrLocation::with('created_by:id,userfullname');
            
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $location = $location->leftjoin("user as u", "u.id", "hr_location.$sortBy");
                $sortBy = 'userfullname';
            }
            
            if ($request->has('search')) {
                $search = $request->get('search');
                $location = search($location, $search);
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $location = $location->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $location->count();
                $location = $location->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                //echo $location->toSql(); die;
                $location = $location->get();
                $filteredRecords = count($location);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Location list.", ['data' => $location], $pager);
        } catch (\Exception $e) {
            app('log')->error("Location listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing location", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Store shift data
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = $this->validateInput($request);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store location  details
            $location = HrLocation::create(['location_name' => $request->get('location_name'),
                                            'status'        => $request->get('status'),
                                            'created_by'    => app('auth')->guard()->id(),
                                            'created_on'    => date('Y-m-d H:i:s')]);
            
            return createResponse(config('httpResponse.SUCCESS'), 'Location  has been added successfully', ['data' => $location]);
        } catch (\Exception $e) {
            app('log')->error("Shift  creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add location ', ['error' => 'Could not add location ']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Show shift data
     */

    public function show($id) {
        try {
            $location = HrLocation::with('created_by:id,userfullname,email')->find($id);
            
            if (!isset($location))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The shift does not exist', ['error' => 'The location detail does not exist']);

            //send shift information
            return createResponse(config('httpResponse.SUCCESS'), 'Location  data', ['data' => $location]);
        } catch (\Exception $e) {
            app('log')->error("Location details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get location detail.', ['error' => 'Could not get location detail.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Show shift data
     */

    public function update(Request $request, $id) {
        try {
            $validator = $this->validateInput($request);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $location = HrLocation::find($id);

            if (!$location)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The location does not exist', ['error' => 'The location does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['location_name', 'status'], $request);

            //update the details
            $location->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Location has been updated successfully', ['message' => 'Location has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Shift updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update location details.', ['error' => 'Could not update location details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Make shift In active.
     */

    public function destroy(Request $request, $id) {
        try {
            $location = HrLocation::find($id);
            if (!$location)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The location does not exist', ['error' => 'The Shift does not exist']);

            // Filter the fields which need to be updated
            $location->status = 0;
            $location->update();

            return createResponse(config('httpResponse.SUCCESS'), 'Location has been deleted successfully', ['message' => 'Location has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted location details.', ['error' => 'Could not deleted location details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate user input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'location_name' => 'required',
            'status' => 'required|numeric'
                ], []);
        return $validator;
    }

}
