<?php

namespace App\Http\Controllers\Backend\Contact;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\ContactRemark;

class ContactRemarkController extends Controller {

    /**
     * Get contact remark detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id) {
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

            $contactRemark = ContactRemark::with('createdBy:id,userfullname')->with('modifiedBy:id,userfullname')->where('contact_id', $id);
            if ($request->has('search')) {
                $search = $request->get('search');
                $contactRemark = search($contactRemark, $search);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $contactRemark = $contactRemark->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $contactRemark->count();

                $contactRemark = $contactRemark->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $contactRemark = $contactRemark->get();

                $filteredRecords = count($contactRemark);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), "Contact remark list.", ['data' => $contactRemark], $pager);
        } catch (\Exception $e) {
            app('log')->error("Contact remark listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while contact remark listing", ['error' => 'Server error.']);
        }
    }

    /**
     * Store contact details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'contact_id' => 'required|integer',
                'notes' => 'required'], []);


            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store client details
            $contact = \App\Models\Backend\ContactRemark::create([
                        'contact_id' => $request->input('contact_id'),
                        'notes' => $request->input('notes'),
                        'is_active' => 1,
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]
            );

            return createResponse(config('httpResponse.SUCCESS'), 'Contact remark has been added successfully', ['data' => $contact]);
        } catch (\Exception $e) {
            app('log')->error("Contact remark creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add contact remark', ['error' => 'Could not add contact remark']);
        }
    }

    /**
     * update contact details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // contact id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'is_active' => 'integer|in:1,0'], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $contactRemark = ContactRemark::find($id);

            if (!$contactRemark)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Contact does not exist', ['error' => 'The Contact does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['is_active','notes'], $request);

            $contactRemark->modified_by = app('auth')->guard()->id();
            $contactRemark->modified_on = date('Y-m-d H:i:s');
            //update the details
            $contactRemark->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Contact remark has been updated successfully', ['message' => 'Contact remark has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Address updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update contact remark details.', ['error' => 'Could not update contact remark details.']);
        }
    }

}
