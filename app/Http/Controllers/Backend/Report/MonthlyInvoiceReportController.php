<?php

namespace App\Http\Controllers\Backend\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\Invoice;

class MonthlyInvoiceReportController extends Controller {

    /**
     * Get Bank detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function generateReport(Request $request) {
        //try {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'invoice.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $monthlyInvoice = Invoice::getMonthlyInvoiceData();
            //showArray($monthlyInvoice->get());exit;
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array("created_on" => "invoice");                
                $monthlyInvoice = search($monthlyInvoice, $search,$alias);
            }
             
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $monthlyInvoice = $monthlyInvoice->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $monthlyInvoice->count();

                $monthlyInvoice = $monthlyInvoice->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $monthlyInvoice = $monthlyInvoice->get();

                $filteredRecords = count($monthlyInvoice);

                $pager = ['sortBy'   => $sortByName,
                    'sortOrder'      => $sortOrder,
                    'pageNumber'     => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords'   => $totalRecords,
                    'filteredRecords'=> $filteredRecords];
            }   
            //For download excel if excel =1
            if ($request->has('excel') && $request->get('excel') == 1) {               
                //format data in array 
                $data = $monthlyInvoice->toArray();
                $column = array();
                $column[] = ['Sr.No','Parent Trading Name', 'Client Code','Entity Name', 'Invoice No', 'BK', 'AR', 'AP','DM','Payroll','Created On'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['parent_name'];
                        $columnData[] = $data['code'];
                        $columnData[] = $data['name'];
                        $columnData[] = $data['invoice_no'];
                        $columnData[] = $data['fixed_fee'];
                        $columnData[] = $data['AR'];  
                        $columnData[] = $data['AP'];  
                        $columnData[] = $data['DM'];  
                        $columnData[] = $data['Payroll'];  
                        $columnData[] = dateFormat($data['created_on']);  
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'MonthlyInvoiceReport', 'xlsx', 'A1:J1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Monthly Invoice Report list.", ['data' => $monthlyInvoice], $pager);
       /* } catch (\Exception $e) {
            app('log')->error("Bank listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Bank", ['error' => 'Server error.']);
        }*/
    }   

}
