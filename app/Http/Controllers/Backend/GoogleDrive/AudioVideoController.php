<?php

namespace App\Http\Controllers\Backend\GoogleDrive;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AudioVideoController extends Controller {

    /**
     * Get Bank detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
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
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'directory_audiovideo.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];

        $audiovideo = \App\Models\Backend\DirectoryAudioVideo::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id')
                ->where("directory_audiovideo.entity_id", $id);
        //for search
        if ($request->has('search')) {
            $search = $request->get('search');
            $audiovideo = search($audiovideo, $search);
        }
        // for relation ship sorting
        if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
            $audiovideo = $audiovideo->leftjoin("user as u", "u.id", "directory_audiovideo.$sortBy");
            $sortBy = 'userfullname';
        }

        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $audiovideo = $audiovideo->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $audiovideo->count();

            $audiovideo = $audiovideo->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $audiovideo = $audiovideo->get();

            $filteredRecords = count($audiovideo);

            $pager = ['sortBy' => $sortByName,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        return createResponse(config('httpResponse.SUCCESS'), "Audio Video list.", ['data' => $audiovideo], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Audio Video listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Audio Video", ['error' => 'Server error.']);
          } */
    }

    /**
     * Store bank details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'document_name' => 'required',
                'document_type' => 'required',
                'document_link' => 'required'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store bank details
            $loginUser = loginUser();
            $audiovideo = \App\Models\Backend\DirectoryAudioVideo::create([
                        'entity_id' => $id,
                        'document_name' => $request->input('document_name'),
                        'document_type' => $request->input('document_type'),
                        'document_link' => $request->input('document_link'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Audio Video has been added successfully', ['data' => $audiovideo]);
        } catch (\Exception $e) {
            app('log')->error("Audio Video creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Audio Video', ['error' => 'Could not add Audio Video']);
        }
    }

    /**
     * update Bank details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'document_name' => 'required',
                'document_type' => 'required',
                'document_link' => 'required'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $audiovideo = \App\Models\Backend\DirectoryAudioVideo::find($id);

            if (!$audiovideo)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Audio Video does not exist', ['error' => 'The Audio Video does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['document_name', 'document_type', 'document_link'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $audiovideo->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Audio Video has been updated successfully', ['message' => 'Audio Video has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Audio Video updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Audio Video details.', ['error' => 'Could not update Audio Video details.']);
        }
    }

    /**
     * get particular bank details
     *
     * @param  int  $id   //bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $audiovideo = \App\Models\Backend\DirectoryAudioVideo::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id')->find($id);

            if (!isset($audiovideo))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Audio Video does not exist', ['error' => 'The Audio Video does not exist']);

            //send bank information
            return createResponse(config('httpResponse.SUCCESS'), 'Audio Video data', ['data' => $audiovideo]);
        } catch (\Exception $e) {
            app('log')->error("Audio Video details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Audio Video.', ['error' => 'Could not get Audio Video.']);
        }
    }
    
   /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Ip address details permanently removed.
     */

    public function destroy(Request $request, $id) {
        try {
            // If validation fails then return error response
            if (!is_numeric($id))
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => 'Please provice numeric id']);

            $audioVideo = \App\Models\Backend\DirectoryAudioVideo::find($id);
            if (!$audioVideo)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Audio Video address does not exist', ['error' => 'The Audio Video address does not exist']);

            $audioVideo->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Audio Video address has been deleted successfully', ['message' => 'Audio Video address has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client deltion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted Audio Video address details.', ['error' => 'Could not deleted Audio Video address details.']);
        }
    }

}
