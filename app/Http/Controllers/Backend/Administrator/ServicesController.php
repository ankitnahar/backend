<?php

namespace App\Http\Controllers\Backend\Administrator;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\Services;

class ServicesController extends Controller {

    /**
     * Get Service detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'services.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $service = Services::with("createdBy:id,userfullname as created_by", "modifiedBy:id,userfullname as modified_by");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $service = search($service, $search);
            }
            // for relation ship sorting
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $service = $service->leftjoin("user as u", "u.id", "services.$sortBy");
                $sortBy = 'userfullname';
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $service = $service->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $service->count();

                $service = $service->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $service = $service->get();

                $filteredRecords = count($service);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Services list.", ['data' => $service], $pager);
        } catch (\Exception $e) {
            app('log')->error("Services listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Services", ['error' => 'Server error.']);
        }
    }

}
