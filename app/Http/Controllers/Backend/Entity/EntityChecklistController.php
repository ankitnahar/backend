<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\EntityChecklist;
use App\Models\Backend\EntityChecklistAudit;

/**
 * This is a entity class controller.
 * 
 */
class EntityChecklistController extends Controller {

    /**
     * Get entity detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id) {
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
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'master_checklist.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $entityChecklist = EntityChecklist::entityChecklist($id);

            if ($request->has('search')) {
                $search = $request->get('search');
                $entityChecklist = search($entityChecklist, $search);
            }

            if (empty($entityChecklist))
                return createResponse(config('httpResponse.UNPROCESSED'), "Service is not agreed by client.", ['error' => "Service is not agreed by client."]);

            // Checkout whethere client agreed service or not
            $isEntitychecklist = Entitychecklist::where('entity_id', $id)->count();
            // Check if all records are requested 
            $entityChecklist = $entityChecklist->groupBy("master_checklist.id");
            if ($request->has('records') && $request->get('records') == 'all') {
                $entityChecklist = $entityChecklist->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $entityChecklist->count();
                $entityChecklist = $entityChecklist->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $entityChecklist = $entityChecklist->get();

                $filteredRecords = count($entityChecklist);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Entity checklist.", ['data' => $entityChecklist, 'isClientchecklist' => $isEntitychecklist], $pager);
        /*} catch (\Exception $e) {
            app('log')->error("Client listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while client checklist", ['error' => 'Server error.']);
        }*/
    }

    /**
     * update entity details
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'checklist' => 'json'], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);


            $addData = $entityChecklist = array();
            $requestedChecklist = \GuzzleHttp\json_decode($request->get('checklist'));
            foreach ($requestedChecklist as $key => $value) {
                $addData['entity_id'] = $id;
                $addData['master_checklist_id'] = $value->checklist_id;
                $addData['is_applicable'] = $value->status;
                $addData['created_by'] = app('auth')->guard()->id();
                $addData['created_on'] = date('Y-m-d H:i:s');
                $entityChecklist[] = $addData;
            }
            Entitychecklist::insert($entityChecklist);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity checklist has been added successfully', ['message' => 'Entity checklist has been added successfully.']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity checklist details', ['error' => 'Could not add entity checklist details.']);
        }
    }

    /**
     * update entity details
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'checklist' => 'json'], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);


            $masterTaskactivity = \App\Models\Backend\MasterChecklist::where('is_active', 1)->get()->pluck('name', 'id')->toArray();
            $entityChecklistexsiting = \App\Models\Backend\EntityChecklist::where('entity_id', $id)->get()->pluck('is_applicable', 'master_checklist_id')->toArray();
            $entityChecklist = \GuzzleHttp\json_decode($request->get('checklist'));

            $status = convertcamalecasetonormalcase(config('constant.entityCheckliststatus'));
            $entityChecklistchanges = $checklistUpdate = $newChecklist = array();
            foreach ($entityChecklist as $key => $value) {
                if (isset($entityChecklistexsiting[$value->checklist_id]) && $value->status != $entityChecklistexsiting[$value->checklist_id]) {
                    $entityChecklistchanges[] = array('checklist' => $masterTaskactivity[$value->checklist_id], 'before' => $status[$entityChecklistexsiting[$value->checklist_id]], 'now' => $status[$value->status]);
                    $checklistUpdate[$value->checklist_id] = $value->status;
                } else if (isset($entityChecklistexsiting[$value->checklist_id])) {
                    continue;
                } else {
                    $addData['entity_id'] = $id;
                    $addData['master_checklist_id'] = $value->checklist_id;
                    $addData['is_applicable'] = $value->status;
                    $addData['created_by'] = app('auth')->guard()->id();
                    $addData['created_on'] = date('Y-m-d H:i:s');
                    $newChecklist[] = $addData;
                }
            }

            if (empty($entityChecklistchanges)) {
                if (empty($newChecklist)) {
                    return createResponse(config('httpResponse.SUCCESS'), 'Nothing for update entity checklist', ['message' => 'Nothing for update entity checklist']);
                } else {
                    Entitychecklist::insert($newChecklist);
                    return createResponse(config('httpResponse.SUCCESS'), 'New entity checklist has been added successfully', ['message' => 'New entity checklist has been added successfully']);
                }
            } else {
                foreach ($checklistUpdate as $updateKey => $updateValue) {
                    $update = ['is_applicable' => $updateValue];
                    Entitychecklist::where('master_checklist_id', $updateKey)->where('entity_id', $id)->update($update);
                }
                // Add new checklist add while agreed new service
                Entitychecklist::insert($newChecklist);

                $entityChecklisthistory['entity_id'] = $id;
                $entityChecklisthistory['changes'] = json_encode($entityChecklistchanges);
                $entityChecklisthistory['modified_by'] = app('auth')->guard()->id();
                $entityChecklisthistory['modified_on'] = date('Y-m-d H:i:s');
                EntitychecklistAudit::insert($entityChecklisthistory);
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Entity checklist has been updated successfully', ['message' => 'Entity checklist has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update entity checklist details.', ['error' => 'Could not update entity checklist details.']);
        }
    }

    /**
     * update entity details
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function history(Request $request, $id) {
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

            $entityChecklisthistory = EntityChecklistAudit::with('modified_by:id,userfullname,email')->where('entity_id', $id);
            if ($request->has('search')) {
                $search = $request->get('search');
                $entityChecklisthistory = search($entityChecklisthistory, $search);
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $entityChecklisthistory = $entityChecklisthistory->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $entityChecklisthistory->count();
                $entityChecklisthistory = $entityChecklisthistory->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $entityChecklisthistory = $entityChecklisthistory->get();

                $filteredRecords = count($entityChecklisthistory);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Entity checklist history detail', ['data' => $entityChecklisthistory, 'format' => 1], $pager);
        } catch (\Exception $e) {
            app('log')->error("Entity checklist history failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while entity checklist history", ['error' => 'Server error.']);
        }
    }   
   
}
