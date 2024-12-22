<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\ClientUserDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * This is a client class controller.
 * 
 */
class ClientUserDocumentController extends Controller {

    /**
     * Get clients detail
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
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['data' => $validator->errors()->first()]);

            //return $request;
            // define soring parameters
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];


            $clientuserdocument = ClientUserDocument::with('created_by:id,userfullname')->where('entity_id', $id);
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $clientuserdocument = $clientuserdocument->leftjoin("user as u", "u.id", "client_user_document.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $clientuserdocument = search($clientuserdocument, $search);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $clientuserdocument = $clientuserdocument->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $clientuserdocument->count();

                $clientuserdocument = $clientuserdocument->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $clientuserdocument = $clientuserdocument->get();

                $filteredRecords = count($clientuserdocument);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Document list.", ['data' => $clientuserdocument], $pager);
        } catch (\Exception $e) {
            app('log')->error("document listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing document", ['error' => 'Server error.']);
        }
    }

    /**
     * Store document details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric',
                'client_user_id' => 'required|numeric',
                'document_name' => 'required|mimes:jpg,jpeg,png,pdf,csv,xlsx,xls',
                'document_type' => 'required',
                'document_title' => 'required'
            ]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $file = $request->file('document_name');
            $extention = $file->getClientOriginalExtension();
            $filename = time() . '.' . $extention;

            $commanFolder = '/uploads/documents/';
            $uploadPath = storage_path() . $commanFolder;
            if (date("m") >= 7) {
                $dir = date("Y") . "-" . date('Y', strtotime('+1 years'));
                if (!is_dir($uploadPath . $dir)) {
                    mkdir($uploadPath . $dir, 0777, true);
                }
            } else if (date("m") <= 6) {
                $dir = date('Y', strtotime('-1 years')) . "-" . date("Y");
                if (!is_dir($uploadPath . $dir)) {
                    mkdir($uploadPath . $dir, 0777, true);
                }
            }

            $location = 'clientuserdoc';
            $document_path = $uploadPath . $dir . '/' . $location;

            if (!is_dir($document_path))
                mkdir($document_path, 0777, true);
            $clientuserdocument = 0;
            if ($file->move($document_path, $filename)) {
                $clientuserdocument = 1;
                $data['entity_id'] = $request->get('entity_id');
                $data['client_user_id'] = $request->get('client_user_id');
                $data['document_title'] = $request->get('document_title');
                $data['document_name'] = $filename;
                $data['document_path'] = $commanFolder . $dir . '/' . $location . '/';
                $data['document_type'] = $extention;
                $data['created_by'] = app('auth')->guard()->id();
                $data['created_on'] = date('Y-m-d H:i:s');
                ClientUserDocument::insert($data);
            }
            if ($clientuserdocument == 1)
                return createResponse(config('httpResponse.SUCCESS'), 'Document has been uploaded successfully', ['data' => 'Document has been uploaded successfully']);

            return createResponse(config('httpResponse.SERVER_ERROR'), 'upload  document', ['error' => $clientuserdocument]);
        } catch (\Exception $e) {
            app('log')->error("Document upload failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not upload  document', ['error' => 'Could not upload worksheet document']);
        }
    }

    /**
     * get particular document details
     *
     * @param  int  $id   //Client id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            //  $clientuserquery = ClientuserQuery::with('created_by:entity_id,comment,type')->find($id);
            $clientuserdocument = ClientUserDocument::find($id);

            if (!isset($clientuserdocument))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The document does not exist', ['error' => 'The document does not exist']);

            //send client information
            return createResponse(config('httpResponse.SUCCESS'), 'client user document data', ['data' => $clientuserdocument]);
        } catch (\Exception $e) {
            app('log')->error("Document details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get client user document.', ['error' => 'Could not get client user query.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Update entity software
     */

    public function update(Request $request, $id) {
        try {

            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric',
                'client_user_id' => 'required|numeric',
                'document_name' => 'required|mimes:jpg,jpeg,png,pdf,csv,xlsx,xls',
                'document_type' => 'required',
                'document_title' => 'required'
            ]);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $clientuserdocument = ClientUserDocument::find($id);
            return $clientuserdocument;

            if (!$clientuserdocument)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The client user query does not exist', ['error' => 'The client user query does not exist']);

            $clientuserdocumentDuplication = ClientUserDocument::where('id', '!=', $id)->where('entity_id', $request->get('entity_id'))->count();
            if ($clientuserdocumentDuplication > 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Duplication user query', ['error' => 'Duplication user query']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['entity_id', 'client_user_id', 'document_name', 'document_type', 'document_title', 'document_path'], $request);
            $clientuserdocument->created_by = app('auth')->guard()->id();
            $clientuserdocument->created_on = date('Y-m-d H:i:s');
            //update the details
            $clientuserdocument->update($updateData);


            if ($clientuserdocument == 1)
                return createResponse(config('httpResponse.SUCCESS'), 'Document has been uploaded successfully', ['data' => 'Document has been uploaded successfully']);

            return createResponse(config('httpResponse.SERVER_ERROR'), 'upload  document', ['error' => $clientuserdocument]);
        } catch (\Exception $e) {
            app('log')->error("Document upload failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not upload  document', ['error' => 'Could not upload  document']);
        }
    }

    public function destroy(Request $request, $id) {
        try {
            $clientuserdocument = ClientUserDocument::find($id);
            // Check weather dynamic group exists or not
            if (!isset($clientuserdocument))
                return createResponse(config('httpResponse.NOT_FOUND'), 'user document  does not exist', ['error' => 'user document  does not exist']);

            $clientuserdocument->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'user document  has been deleted successfully', ['message' => 'user document  has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("user document info deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete user document.', ['error' => 'Could not delete user document.']);
        }
    }

}
