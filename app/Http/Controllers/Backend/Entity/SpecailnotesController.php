<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\EntitySpecialnotes;

class SpecailnotesController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Fetch entity special notes data
     */

    public function index(Request $request, $id) {
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
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $specailnotes = EntitySpecialnotes::with('createdBy:id,userfullname')->with('modifiedBy:id,userfullname')->with('service_id:id,service_name')->with('archiveBy')->where('entity_id',$id);

            if ($request->has('search')) {
                $search = $request->get('search');
                $specailnotes = search($specailnotes, $search);
                $searchDecode = \GuzzleHttp\json_decode($search);
            }
            $tabs = \App\Models\Backend\Entity::entityService($id);

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $specailnotes = $specailnotes->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $specailnotes->count();

                $specailnotes = $specailnotes->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $specailnotes = $specailnotes->get();

                $filteredRecords = count($specailnotes);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Special notes list.", ['data' => $specailnotes,'tabs' => $tabs], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Client listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing clients", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Store entity special notes
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = $this->validateInput($request);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store special notes  details
            $specailnotes = EntitySpecialnotes::create([
                        'entity_id' => $request->get('entity_id'),
                        'service_id' => $request->get('service_id'),
                        'note' => $request->get('note'),
                        'type' => $request->get('type'),
                        'expiry_on' => $request->get('expiry_on'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s'),
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Special notes  has been added successfully', ['data' => $specailnotes]);
        } catch (\Exception $e) {
            app('log')->error("Special notes  creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add special notes ', ['error' => 'Could not add special notes ']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Show entity special notes
     */

    public function show(Request $request, $id) {
        try {
            $specailnotes = EntitySpecialnotes::with('created_by:id,userfullname,email')->with('service_id:id,service_name')->with('is_active')->find($id);

            if (!isset($specailnotes))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The special notes  does not exist', ['error' => 'The special notes  does not exist']);

            //send special notes  information
            return createResponse(config('httpResponse.SUCCESS'), 'Special notes  data', ['data' => $specailnotes]);
        } catch (\Exception $e) {
            app('log')->error("Special notes  details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get special notes .', ['error' => 'Could not get special notes .']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Update entity special notes
     */

    public function update(Request $request, $id) {
        try {
            $validator = $this->validateInput($request);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $specailnotes = EntitySpecialnotes::find($id);

            if (!$specailnotes)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Special notes does not exist', ['error' => 'The Special notes does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['entity_id', 'service_id', 'note', 'type', 'expiry_on'], $request);
            $specailnotes->modified_by = app('auth')->guard()->id();
            $specailnotes->modified_on = date('Y-m-d H:i:s');
            //update the details
            $specailnotes->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Special notes has been updated successfully', ['message' => 'Special notes has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update special notes details.', ['error' => 'Could not update special notes details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Update entity special notes fields by archive on and archive by.
     */

    public function destroy(Request $request, $id) {
        try {
            $specailnotes = EntitySpecialnotes::find($id);
            if (!$specailnotes)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Special notes does not exist', ['error' => 'The Special notes does not exist']);
            // Filter the fields which need to be updated

            $specailnotes->is_active = 0;
            $specailnotes->update();

            $specailnoteArchive = new \App\Models\Backend\EntitySpecialnoteArchive;
            $specailnoteArchive->entity_specialnotes_id = $id;
            $specailnoteArchive->archive_by = app('auth')->guard()->id();
            $specailnoteArchive->archive_on = date('Y-m-d H:i:s');
            $specailnoteArchive->save();

            return createResponse(config('httpResponse.SUCCESS'), 'Special notes has been deleted successfully', ['message' => 'Special notes has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted special notes details.', ['error' => 'Could not deleted special notes details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate user input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required',
            'service_id' => 'required',
            'note' => 'required',
            'type' => 'required|in:1,0',
            'expiry_on' => 'required_if:type,0|date|date_format:Y-m-d|after:yesterday'
                ], ['expiry_on.required' => 'The expiry date field is required',
            'expiry_on.date' => 'Enter valid date for expiry date',
            'expiry_on.date_format' => 'The expiry date format is not valid',
            'expiry_on.after' => 'The expiry date should not be past date']);
        return $validator;
    }

}
