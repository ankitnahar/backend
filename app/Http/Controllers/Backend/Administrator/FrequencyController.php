<?php

namespace App\Http\Controllers\Backend\Administrator;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FrequencyController extends Controller {

    /**
     * Get Frequency detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'frequency.sort_order';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];
            $frequency = \App\Models\Backend\Frequency::where("is_active", "1");
            if ($request->has('search')) {
                $search = $request->get('search');
                $frequency = search($frequency, $search);
            }
            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $billing = $billing->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $frequency->get()->count();

                $frequency = $frequency->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $frequency = $frequency->get();

                $filteredRecords = count($frequency);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            //send recurring
            return createResponse(config('httpResponse.SUCCESS'), 'Frequencty List', ['data' => $frequency]);
        } catch (\Exception $e) {
            app('log')->error("Frequencty api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Frequencty.', ['error' => 'Could not get Frequencty.']);
        }
    }

}
