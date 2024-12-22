<?php

namespace App\Http\Controllers\Backend\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FFRevisionReportController extends Controller {

    /**
     * Get Bank detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function generateReport(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder'     => 'in:asc,desc',
                'pageNumber'    => 'numeric|min:1',
                'recordsPerPage'=> 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'ff_proposal.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $ffrevision = \App\Models\Backend\FFProposal::getFFRevisionData();
            //showArray($ffrevision->get());exit;
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array("created_on" => "ff_proposal");                
                $ffrevision = search($ffrevision, $search,$alias);
            }
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $ffrevision = $ffrevision->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $ffrevision->count();

                $ffrevision = $ffrevision->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $ffrevision = $ffrevision->get();

                $filteredRecords = count($ffrevision);

                $pager = ['sortBy'   => $sortByName,
                    'sortOrder'      => $sortOrder,
                    'pageNumber'     => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords'   => $totalRecords,
                    'filteredRecords'=> $filteredRecords];
            } 
            $ff= array();
            foreach($ffrevision as $row){               
               $proposaldate = date("Y-m-d H:i:s",strtotime('01-'.$row->month.'-'.$row->year));
                $to = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $row->created_on);
                $from = \Carbon\Carbon::createFromFormat('Y-m-d H:s:i', $proposaldate);
                $diffMonths = $to->diffInMonths($from);
                $row['diff_months'] = $diffMonths;
                $writeoff = \App\Models\Backend\Invoice::where("entity_id",$row->entity_id)
                        ->where("service_id",$row->service_id)
                        ->whereBetween("created_on",[$proposaldate,$row->created_on])->avg("extra_woff");
                $row['avg_writeoff'] = $writeoff;
                $ff[] = $row;
            }
            
            //For download excel if excel =1
            if ($request->has('excel') && $request->get('excel') == 1) {               
                //format data in array 
                $data = $ff;
                $column = array();
                $column[] = ['Sr.No', 'Client Code','Entity Name', 'BK FF start month', 'FF Creation month', 'Difference Month', 'Total average w/off '];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['code'];
                        $columnData[] = $data['trading_name'];
                        $columnData[] = $data['month'];
                        $columnData[] = $data['createdMonth'];
                        $columnData[] = $data['diff_months'];  
                        $columnData[] = $data['avg_writeoff'];   
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'FFRevisionReport', 'xlsx', 'A1:G1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "FF Revision Report list.", ['data' => $ff], $pager);
        } catch (\Exception $e) {
            app('log')->error("FF Revision listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing FF Revision", ['error' => 'Server error.']);
        }
    }   

}
