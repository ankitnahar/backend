<?php

namespace App\Http\Controllers\Backend\DebtorsManagament;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DebtorsController extends Controller {

    /**
     * Get Bank detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : '';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];
            //$InvoiceAmount = \App\Models\Backend\Invoice::sumAmount();
            //showArray($InvoiceAmount);exit;
            $debtors = \App\Models\Backend\Invoice::getDebtorsList()->with("createdBy:id,userfullname");

            $right = checkButtonRights(38, 'all_service');
            if ($right == false) {
                $userHierarchy = getLoginUserHierarchy();
                $serviceRight = $userHierarchy->other_right != '' ? $userHierarchy->other_right : '0';
                $debtors = $debtors->whereRaw("invoice.service_id IN ($serviceRight)");
            }
            $followUp = checkButtonRights(38, 'all_followup');
            if ($followUp == false) {
                $debtors = $debtors->where("b.debtor_followup", "1");
            } else {
                $debtors = $debtors->whereIn("b.debtor_followup", [0, 1]);
            }
            //check client allocation
            $right = checkButtonRights(38, 'all_entity');
            if ($right ==false) { 
            $entity_ids = checkUserClientAllocation(loginUser());
            if (is_array($entity_ids))
                $debtors = $debtors->whereRaw("e.id IN(". implode(",",$entity_ids).")");
            }
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $aliase = array("entity_id" => "invoice", "service_id" => "invoice","parent_id" => "ed","billing_name"=>'e');
                $debtors = search($debtors, $search, $aliase);
            }
             if ($request->has('technical_account_manager')) {
                $tam = $request->get('technical_account_manager');
                $debtors = $debtors->whereRaw("JSON_EXTRACT(ea.allocation_json, '$.9') = '" . $tam . "'");
            }
            if ($sortBy == 'created_by') {
                $debtors = $debtors->leftjoin("user as u", "u.id", "invoice.$sortBy");
                $sortBy = 'userfullname';
            }
            $debtors = $debtors->groupBy("invoice.id");
            //echo getSQL($debtors);exit;
            //echo $debtors = $debtors->toSql();exit;
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                if ($sortBy != '') {
                    $debtors = $debtors->orderBy($sortBy, $sortOrder)->get();
                } else {
                    $debtors = $debtors->orderByRaw("invoice.invoice_no asc,e.id asc")->get();
                }
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $debtors->get()->count();

                if ($sortBy != '') {
                    $debtors = $debtors->orderBy($sortBy, $sortOrder)->skip($skip)
                            ->take($take);
                } else {
                    $debtors = $debtors->orderByRaw("invoice.invoice_no asc,e.id asc")->skip($skip)
                            ->take($take);
                }

                $debtors = $debtors->get();

                $filteredRecords = count($debtors);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            $debtor = array();
            foreach ($debtors as $d) {
                if($d['is_related'] == 1){
                    $paidAmt = \App\Models\Backend\Invoice::where("invoice_no",$d['invoice_no'])->sum('paid_amount');
                    //$outatandingAmt = $d->outstanding_amount;
                    $outatandingAmt = \App\Models\Backend\Invoice::where("invoice_no",$d->invoice_no)->sum('outstanding_amount');
                $d->paid_amount = $paidAmt;                
                $d->outstanding_amount = ($outatandingAmt > 0 ) ? $outatandingAmt : $paidAmt;
                }
                $debtor[] = $d;
            }
            //For download excel if excel =1
            if ($request->has('excel') && $request->get('excel') == 1) {
                //format data in array 
                $data = $debtor;
                $column = array();
                $column[] = ['Sr.No', 'Invoice No','Parent Trading Name', 'Client', 'Fixed Fee', 'Service','TAM','Amount (Inc GST)', 'Amount due(Inc GST)','Payment Type','Sent Date', 'Due Date', 'Created By', 'Follow Up', 'Client Status'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    $payment = config("constant.payment");
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['invoice_no'];
                        $columnData[] = $data['parent_name'];
                        $columnData[] = $data['billing_name'];
                        $columnData[] = ($data['is_fixed_fees'] == 1) ? 'Yes' : 'No';
                        $columnData[] = $data['service_name'];
                        $columnData[] = $data['tam_name'];
                        $columnData[] = $data['paid_amount'];
                        $columnData[] = $data['outstanding_amount'];
                        $columnData[] = $payment[$data['payment_id']];
                        $columnData[] = dateFormat($data['send_date']);
                        $columnData[] = dateFormat($data['due_date']);
                        $columnData[] = $data['createdBy']['userfullname'];
                        $columnData[] = ($data['debtor_followup'] == 1) ? 'Yes' : 'No';
                        $columnData[] = ($data->discountinue_stage == 1) ? 'Discontinue Process Initiated' : 'Active';
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'DMList', 'xlsx', 'A1:N1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "DM list.", ['data' => $debtor], $pager);
       /* } catch (\Exception $e) {
            app('log')->error("DM listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing DM", ['error' => 'Server error.']);
        }*/
    }

    /**
     * Store bank details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
       try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required',
                'to' => 'required|email_array',
                'cc' => 'email_array',
                'bcc' => 'email_array',
                'template_type' => 'required',
                'subject' => 'required',
                'content' => 'required',
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store bank details
            $loginUser = loginUser();
            $debtors = \App\Models\Backend\DMMail::create([
                        'entity_id' => $request->input('entity_id'),
                        'to' => $request->input('to'),
                        'cc' => $request->input('cc'),
                        'bcc' => $request->input('bcc'),
                        'template_type' => $request->input('template_type'),
                        'subject' => $request->input('subject'),
                        'content' => $request->input('content'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser
            ]);
            
            $comment = \App\Models\Backend\DMComment::create([
                            'entity_id' => $request->input('entity_id'),
                            'sent_notification' => 1,
                            'to_mail' => $request->input('to'),
                            'cc_mail' => $request->input('cc'),
                            'comment' => $request->input('subject'),
                            'created_by' => loginUser(),
                            'created_on' => date('Y-m-d H:i:s')]
                );

            $data['to'] = $request->input('to');
            $data['cc'] = $request->input('cc');
            $data['bcc'] = $request->input('bcc');
            $data['subject'] = $request->input('subject');
            $data['content'] = $request->input('content');
            storeMail($request, $data);
            return createResponse(config('httpResponse.SUCCESS'), 'DM Mail has been send successfully', ['data' => $debtors]);
        } catch (\Exception $e) {
            app('log')->error("DM Mail creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not send client DDR mail', ['error' => 'Could not send client DDR mail']);
        }
    }

    public static function templateList() {
        try {
            $template = \App\Models\Backend\DMTemplate::select("id", "template_name")->get();
            return createResponse(config('httpResponse.SUCCESS'), 'Template List', ['data' => $template]);
        } catch (\Exception $e) {
            app('log')->error("Template List failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get template list', ['error' => 'Could not get template list']);
        }
    }

    public static function templateData(Request $request) {
       // try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'template_id' => 'required|numeric',
                'entity_id' => 'required|numeric'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $id = $request->input('template_id');
            $templateData = \App\Models\Backend\DMTemplate::find($id);
            if (!$templateData)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Template does not exist', ['error' => 'Template does not exist']);
            $entityName = \App\Models\Backend\Entity::find($request->input('entity_id'));
            $subject = "Be Free:" . $entityName->billing_name . "- " . $templateData->template_name;
            $content = \App\Models\Backend\DMTemplate::getTemplateDetail($request->input('entity_id'), $id);

            return createResponse(config('httpResponse.SUCCESS'), 'Template data', ['subject' => $subject, 'content' => $content]);
       /* } catch (\Exception $e) {
            app('log')->error("Template Detail failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get template details.', ['error' => 'Could not get template details.']);
        }*/
    }

    /**
     * Get mail detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function mailList(Request $request, $entityId) {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'e.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $DMMail = \App\Models\Backend\DMMail::getMailList($entityId);
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $DMMail = search($DMMail, $search);
            }

            $DMMail = $DMMail->groupby("e.id");
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $DMMail = $DMMail->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $DMMail->count();

                $DMMail = $DMMail->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $DMMail = $DMMail->get();

                $filteredRecords = count($DMMail);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "DM Mail list.", ['data' => $DMMail], $pager);
        } catch (\Exception $e) {
            app('log')->error("DMlisting failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing DM", ['error' => 'Server error.']);
        }
    }

    public static function updateDebtors() {
        try {
            //$right = checkButtonRights(38, 'update_debtors');
            //if ($right == false) {
                $updateDebtors = \App\Models\Backend\Invoice::updateDebtors();
                //echo getSQL($updateDebtors);exit;
                if ($updateDebtors->count() > 0) {
                    foreach ($updateDebtors->get() as $dm) {
                        \App\Models\Backend\Invoice::where("invoice_no", $dm->invoice_no)->update(["debtors_stage" => 1]);
                    }
                    return createResponse(config('httpResponse.SUCCESS'), "Update Debtors Sucessfully", ['message' => 'Update Debtors Sucessfully']);
                } else {
                    return createResponse(config('httpResponse.SUCCESS'), "No Record Found", ['message' => 'No Record Found']);
                }
           /* } else {
                return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
            }*/
        } catch (\Exception $e) {
            app('log')->error("Debtors Updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while update debtors", ['error' => 'Server error.']);
        }
    }

}
