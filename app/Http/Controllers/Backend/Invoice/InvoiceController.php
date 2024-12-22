<?php

namespace App\Http\Controllers\Backend\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Invoice;

class InvoiceController extends Controller {

    /**
     * Get invoice detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'invoice.created_on';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $invoice = Invoice::invoiceData();
            $right = checkButtonRights(27, 'all_entity');
            if ($right ==false) { 
            //check client allocation
            $entity_ids = checkUserClientAllocation(loginUser());
            if (is_array($entity_ids)) {
                $invoice = $invoice->whereRaw("e.id IN(".implode(",",$entity_ids).")");
            }
            }
            if ($id != '8') {
                $invoice = $invoice->where("invoice.status_id", $id);
            }
             $invoice = $invoice->where("invoice.parent_id", "=", "0");
            $allservice = checkButtonRights(27, 'all_service');
            if ($allservice == false) {
                $userHierarchy = getLoginUserHierarchy();
                $serviceRight = $userHierarchy->other_right != '' ? $userHierarchy->other_right : '0';
                $invoice = $invoice->whereRaw("invoice.service_id IN ($serviceRight)");
            }
            $invoice = $invoice->groupBy("invoice.id");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array("entity_id" => "invoice","parent_id" => "invoice", "service_id" => "invoice", "created_on" => "invoice", "created_by" => "invoice", "invoice_no" => "invoice");
                $invoice = search($invoice, $search, $alias);
            }

            if ($request->has('technical_account_manager')) {
                $tam = $request->get('technical_account_manager');
                $invoice = $invoice->whereRaw("JSON_EXTRACT(ea.allocation_json, '$.9') = '" . $tam . "'");
            }

            if ($request->has('team_lead')) {
                $tl = $request->get('team_lead');
                $invoice = $invoice->whereRaw("JSON_EXTRACT(ea.allocation_json, '$.60') = '" . $tl . "'");
            }
            
            if ($request->has('associate_team_lead')) {
                $atl = $request->get('associate_team_lead');
                $invoice = $invoice->whereRaw("JSON_EXTRACT(ea.allocation_json, '$.61') = '" . $atl . "'");
            }

            // for relation ship sorting
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $invoice = $invoice->leftjoin("user as u", "u.id", "invoice.$sortBy");
                $sortBy = 'userfullname';
            }
//echo $invoice = $invoice->toSql();exit;
            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                if ($sortBy != '') {
                    $invoice = $invoice->orderBy($sortBy, $sortOrder)->get();
                } else {
                    $invoice = $invoice->orderByRaw("invoice.invoice_no desc,invoice.id desc")->get();
                }
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $invoice->get()->count();
                if ($sortBy != '') {
                    $invoice = $invoice->orderBy($sortBy, $sortOrder)
                            ->skip($skip)
                            ->take($take);
                } else {
                    $invoice = $invoice->orderByRaw("invoice.invoice_no desc,invoice.id desc")
                            ->skip($skip)
                            ->take($take);
                }
                $invoice = $invoice->get();

                $filteredRecords = count($invoice);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
             $invoices = array();
            foreach ($invoice as $inv) {
                $relatedInvoice = Invoice::where("parent_id",$inv['id']);
                $inv['totalamount'] = $inv['paid_amount']; 
                if($relatedInvoice->count() > 0){
                    $paidAmt = Invoice::where("parent_id",$inv['id'])->sum("paid_amount");
                    $totalAmt = $inv['totalamount'] + $paidAmt;
                $inv['totalamount'] = number_format($totalAmt,2);              
                }
                $invoices[] = $inv;
            }
            //For download excel if excel =1
            if ($request->has('excel') && $request->get('excel') == 1) {
                //format data in array
                $data = $invoices;
                $column = array();

                $column[] = ['Sr.No', 'Invoice no','Parent Client Name', 'Client', 'Service', 'Fixed Fee', 'Type', 'Amount(Inc GST)', 'Status', 'Credit Not Applied', 'Payment date', 'TAM', 'TL','ATL', 'Created on', 'Created By', 'Client Status'];

                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = ($data['invoice_no'] == '') ? '-' : $data['invoice_no'];
                        $columnData[] = $data['parent_name'];
                        $columnData[] = $data['billing_name'];
                        $columnData[] = $data['service_name'];
                        $columnData[] = ($data['is_fixed_fees'] == '1') ? 'Yes' : 'No';
                        $columnData[] = $data['invoice_type'];
                        $columnData[] = $data['totalamount'];
                        $columnData[] = $data['status'];
                        $columnData[] = ($data['allocate_credit'] == '2') ? 'Yes' : 'No';
                        $columnData[] = $data['payment_date'];
                        $columnData[] = $data['tam_name'];
                        $columnData[] = $data['tl_name'];
                         $columnData[] = $data['atl_name'];
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['createdBy']['created_by'];
                        $columnData[] = ($data['discontinue_stage'] == 1) ? 'Discontinue Process Initiated' : 'Active';
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'InvoiceList', 'xlsx', 'A1:P1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Invoice list.", ['data' => $invoices], $pager);
        /*} catch (\Exception $e) {
            app('log')->error("Invoice listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Invoice", ['error' => 'Server error.']);
        }*/
    }

    /**
     * Store invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        //try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'type' => 'required|in:new,adjuct',
                'from_date' => 'date',
                'to_date' => 'required|date',
                'service_id' => 'required|numeric',
                'entity_id' => 'required'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store bank details
            $loginUser = loginUser();
            $ids = $request->input('entity_id');
            if ($request->input('type') == 'new') {
                $entityIds = implode(",", $ids);
            } else {
                $entityIds = $request->input('entity_id');
            }
            $newInvoice = \App\Models\Backend\Billing::entityBillingData($entityIds, $request->input('service_id'));
            if ($request->input('type') == 'new') {
                if (in_array($request->input('service_id'), array(4, 5, 7)))
                    $status = '2';
                else
                    $status = '1';
            }else {
                $status = '10';
            }
            if ($newInvoice->count() == 0) {
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity Billing Info does not exist', ['error' => 'The Entity Billing Info does not exist']);
            } else {
                foreach ($newInvoice->get() as $row) {
                    if ($request->input('service_id') == 1 || $request->input('service_id') == 2 || $request->input('service_id') == 6) {
                        $entityAllocation = \App\Models\Backend\EntityAllocation::where("entity_id", $row->entity_id)->where("service_id", $request->input('service_id'));
                        if ($entityAllocation->count() == 0) {
                            return createResponse(config('httpResponse.NOT_FOUND'), 'Please Allocated Entity First in this service', ['error' => 'Please Allocated Entity First in this service']);
                        } else {
                            $entityAllocation = $entityAllocation->first();
                        }
                    }
                    $invoiceId = Invoice::create([
                                "entity_id" => $row->entity_id,
                                "billing_id" => $row->billing_id,
                                "parent_id" => 0,
                                "status_id" => $status,
                                "is_fixed_fees" => $row->inc_in_ff,
                                "service_id" => $request->input('service_id'),
                                "from_period" => ($request->input('from_date') != '') ? date("Y-m-d", strtotime($request->input('from_date'))) : '0000-00-00',
                                "to_period" => date("Y-m-d", strtotime($request->input('to_date'))),
                                "invoice_type" => 'Manual',
                                "net_amount" => '0',
                                "created_by" => loginUser(),
                                "created_on" => date('Y-m-d H:i:s')]);
                    $relatedInvoice = \App\Models\Backend\Billing::entityBillingData($row->entity_id, $request->input('service_id'), 1);
                    //add entry in log
                    $log = \App\Models\Backend\InvoiceLog::addLog($invoiceId->id, $status);
                    // ADD user Hierarchy

                    if ($request->input('service_id') == 1 || $request->input('service_id') == 2 || $request->input('service_id') == 6) {

                        \App\Models\Backend\InvoiceUserHierarchy::create([
                            'invoice_id' => $invoiceId->id,
                            'user_hierarchy' => $entityAllocation->allocation_json
                        ]);
                    }
                    if ($relatedInvoice->count() >0) {
                        foreach ($relatedInvoice->get() as $related) {
                            $invoice = Invoice::create([
                                        "entity_id" => $related->entity_id,
                                        "billing_id" => $related->billing_id,
                                        "parent_id" => $invoiceId->id,
                                        "status_id" => $status,
                                        "is_fixed_fees" => $related->inc_in_ff,
                                        "service_id" => $request->input('service_id'),
                                        "from_period" => ($request->input('from_date') != '') ? date("Y-m-d", strtotime($request->input('from_date'))) : '0000-00-00',
                                        "to_period" => date("Y-m-d", strtotime($request->input('to_date'))),
                                        "invoice_type" => 'Manual',
                                        "net_amount" => '0',
                                        "created_by" => loginUser(),
                                        "created_on" => date('Y-m-d')
                            ]);
                            //add entry in log
                            $log = \App\Models\Backend\InvoiceLog::addLog($invoice->id, $status);
                            // ADD user Hierarchy   
                            if ($request->input('service_id') == 1 || $request->input('service_id') == 2 || $request->input('service_id') == 6) {
                            $entityAllocation = \App\Models\Backend\EntityAllocation::where("entity_id", $related->entity_id)->where("service_id", $request->input('service_id'))->first();
                            \App\Models\Backend\InvoiceUserHierarchy::create([
                                'invoice_id' => $invoice->id,
                                'user_hierarchy' => $entityAllocation->allocation_json
                            ]);
                            }
                        }
                    }
                }
            }
            return createResponse(config('httpResponse.SUCCESS'), 'New Invoice has been added successfully', ['message' => 'New Invoice has been added successfully']);
        /*} catch (\Exception $e) {
            app('log')->error("New Invoice creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add invoice', ['error' => 'Could not add invoice']);
        }*/
    }

    /**
     * Store oneoff invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function oneoff(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'invoice_type' => 'required|in:Advance,Setup,Formation,Audit',
                'service_id' => 'numeric',
                'entity_id' => 'required',
                'amount' => 'required'
                    ], []);
            if ($request->input('invoice_type') == 'Setup') {
                $service_id = 1;
            } else if ($request->input('invoice_type') == 'Audit') {
                $service_id = 4;
            } else {
                $service_id = $request->input('service_id');
            }
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $status = 2;
            $billingDetail = \App\Models\Backend\BillingServices::where("entity_id", $request->input('entity_id'))
                            ->where("service_id",$service_id)
                            ->where("is_latest","1")->where("is_active","1")->where("is_updated","1")->first();
            $invoice = Invoice::create([
                        "entity_id" => $request->input('entity_id'),
                        "billing_id" => $billingDetail->id,
                        "parent_id" => 0,
                        "status_id" => 2,
                        "is_fixed_fees" => $billingDetail->inc_in_ff,
                        "service_id" => $service_id,
                        "invoice_type" => $request->input('invoice_type'),
                        "gross_amount" => $request->input('amount'),
                        "net_amount" => $request->input('amount'),
                        "created_by" => loginUser(),
                        "created_on" => date('Y-m-d')]);
            // ADD user Hierarchy
            if ($service_id == 1 || $service_id == 2 || $service_id == 6) {
                $entityAllocation = \App\Models\Backend\EntityAllocation::where("entity_id", $request->input('entity_id'))->where("service_id", $service_id);
                if ($entityAllocation->count() == 0) {
                    return createResponse(config('httpResponse.NOT_FOUND'), 'Please Allocated Entity First in this service', ['error' => 'Please Allocated Entity First in this service']);
                }
                $entityAllocation = $entityAllocation->first();
                \App\Models\Backend\InvoiceUserHierarchy::create([
                    'invoice_id' => $invoice->id,
                    'user_hierarchy' => $entityAllocation->allocation_json
                ]);
            }
            //add entry in log
            $log = \App\Models\Backend\InvoiceLog::addLog($invoice->id, $status);
            return createResponse(config('httpResponse.SUCCESS'), 'One off Invoice has been added successfully', ['data' => $invoice]);
        } catch (\Exception $e) {
            app('log')->error("One off creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add One off', ['error' => 'Could not add One off']);
        }
    }

    /**
     * update invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'timesheet_unit' => 'numeric',
                'carry_unit' => 'numeric',
                'fixed_unit' => 'numeric',
                'extra_unit' => 'numeric',
                'woff_unit' => 'numeric',
                'won_unit' => 'numeric',
                'charged_unit' => 'numeric',
                'total_charge_unit' => 'numeric',
                'ff_amount' => 'numeric',
                'extra_amount' => 'numeric',
                'gross_amount' => 'numeric',
                'discount_type' => 'in:None,Fixed,Advance',
                'discount_amount' => 'numeric',
                'net_amount' => 'numeric',
                'card_surcharge' => 'numeric',
                'surcharge_amount' => 'numeric',
                'gst_amount' => 'numeric',
                'paid_amount' => 'numeric',
                'timesheet' => 'array',
                'master_unit' => 'array'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $invoice = Invoice::find($id);

            if (!$invoice)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Invoice does not exist', ['error' => 'The Invoice does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['woff_amount', 'won_amount', 'timesheet_unit', 'carry_unit', 'fixed_unit',
                'extra_unit', 'woff_unit', 'won_unit', 'charged_unit', 'ff_amount', 'extra_amount', 'gross_amount', 'discount_type', 'discount_amount', 'net_amount', 'card_surcharge',
                'surcharge_amount', 'gst_amount', 'paid_amount', 'outstanding_amount', 'payment_date', 'send_date', 'dm_date', 'due_date', 'adjusted', 'dismiss_reason'], $request);
            $updateData['total_charge_unit'] = $request->input('fixed_unit') + $request->input('extra_unit');
            $updateData['extra_woff'] = max($request->input('timesheet_unit') - ($request->input('fixed_unit') + $request->input('extra_unit') + $request->input('carry_unit')), 0);
            $updateData['extra_won'] = max(($request->input('fixed_unit') + $request->input('extra_unit') + $request->input('carry_unit')) - $request->input('timesheet_unit'), 0);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $invoice->update($updateData);

            if ($invoice->status_id == '10') {
                $log = \App\Models\Backend\InvoiceLog::addLog($invoice->id, "10");
            }
            //update timesheet detail
            if ($request->has('timesheet')) {
                $timesheets = $request->get('timesheet');
                // showArray($timesheets);exit;
                foreach ($timesheets as $timesheet) {
                    $carry_forward_invoice_ids = '';
                    if (isset($timesheet['carry_forward_invoice_ids'])) {
                        $carry_forward_invoice_ids = ($timesheet['billing_status'] == 2 && $timesheet['carry_forward_invoice_ids'] == '') ? $id :
                                (($timesheet['billing_status'] == 2 && $timesheet['carry_forward_invoice_ids'] != '') ? $timesheet['carry_forward_invoice_ids'] . "," . $id : '');
                    }
                    $timesheetData = [
                        'billing_status' => $timesheet['billing_status'],
                        'invoice_id' => $id,
                        'invoice_amt' => $timesheet['amount'],
                        'carry_forward_invoice_ids' => $carry_forward_invoice_ids];

                    \App\Models\Backend\Timesheet::where("id", $timesheet['timesheet_id'])->update($timesheetData);
                }
            }

            if ($request->has('master_unit')) {
                $masterUnits = $request->get('master_unit');

                foreach ($masterUnits as $master) {
                    $masterData = [
                        'invoice_id' => $master['invoice_id'],
                        'master_id' => $master['master_id'],
                        'timesheet_unit' => $master['timesheet_unit'],
                        'woffunit' => $master['woffunit'],
                        'wonunit' => $master['wonunit'],
                        'woff_unit' => $master['woff_unit'],
                        'carry_unit' => $master['carry_unit'],
                        'charge_unit' => $master['charge_unit'],
                        'rate_per_hour' => $master['rate_per_hour'],
                        'amount' => $master['amount'],
                        'created_on' => date("Y-m-d:h:i:s"),
                        'created_by' => loginUser()];

                    if (isset($master['id']) && $master['id'] != 0) {
                        \App\Models\Backend\InvoiceMasterUnitCalc::where("id", $master['id'])->update($masterData);
                    } else {
                        \App\Models\Backend\InvoiceMasterUnitCalc::Insert($masterData);
                    }
                }
            }


            return createResponse(config('httpResponse.SUCCESS'), 'Invoice has been updated successfully', ['message' => 'Invoice has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Invoice updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update invoice details.', ['error' => 'Could not update invoice details.']);
        }
    }

    public function addInvoiceNotes(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'notes' => 'required'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store bank details
            $loginUser = loginUser();
            $invoiceNotes = \App\Models\Backend\InvoiceNotes::create([
                        'invoice_id' => $id,
                        'notes' => $request->input('notes'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Invoice Notes has been added successfully', ['data' => $invoiceNotes]);
        } catch (\Exception $e) {
            app('log')->error("Invoice Notes creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add invoice notes', ['error' => 'Could not add invoice notes']);
        }
    }

    /**
     * log particular Invoice
     *
     * @param  int  $id   //invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function log(Request $request, $id) {
        try {
            $invoiceLog = \App\Models\Backend\InvoiceLog::with("statusId:id,name", "modifiedBy:id,userfullname as modified_by")
                    ->where("invoice_id", $id);
            if ($invoiceLog->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Invoice Log does not exist', ['error' => 'The Invoice Log does not exist']);

            //invoice log
            return createResponse(config('httpResponse.SUCCESS'), 'Invoice Log data', ['data' => $invoiceLog->get()]);
        } catch (\Exception $e) {
            app('log')->error("Invoice log updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get invoice log details.', ['error' => 'Could not get invoice log details.']);
        }
    }

    /**
     * Invoice Status particular Invoice
     *
     * @param  int  $id   //invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function invoiceStatus() {
        try {
            $invoiceStatus = \App\Models\Backend\InvoiceStatus::where("is_active", 1)->orderBy("sort_order")->get();
            //invoice log
            return createResponse(config('httpResponse.SUCCESS'), 'Invoice Status List', ['data' => $invoiceStatus]);
        } catch (\Exception $e) {
            app('log')->error("Invoice Status detail failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get invoice status details.', ['error' => 'Could not get invoice status details.']);
        }
    }

    /**
     * Invoice Status particular Invoice
     *
     * @param  int  $id   //invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function advanceInvoice($entityId) {
        try {
            $advanceInvoice = Invoice::with("createdBy:id,userfullname")
                            ->select("invoice_no", "net_amount", "created_by", "created_on")
                            ->where("invoice_type", 'Advance')->where("status_id", "4")->where("entity_id", $entityId)->get();
            $totalAmount = Invoice::where("invoice_type", 'Advance')->where("status_id", "4")->where("entity_id", $entityId)->sum('net_amount');
            $adjustAmount = Invoice::where("discount_type", "Advance")->where("entity_id", $entityId)->sum('discount_amount');
            $balanceAmount = $totalAmount - $adjustAmount;
            $amountArray = array("Total" => $totalAmount, "Adjust" => $adjustAmount, "Balance" => $balanceAmount);
            //invoice log
            return createResponse(config('httpResponse.SUCCESS'), 'Advance invoice Status List', ['data' => $advanceInvoice, $amountArray]);
        } catch (\Exception $e) {
            app('log')->error("Advance Invoice Status detail failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Advance invoice details.', ['error' => 'Could not get Advance invoice details.']);
        }
    }

    /**
     * Invoice dismiss
     *
     * @param  int  $id   //invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function dismissInvoice(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'dismiss_reason' => 'required'
                    ], []);

            // store dismiss invoice details

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $invoice = Invoice::find($id);

            if (!$invoice)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Invoice does not exist', ['error' => 'The Invoice does not exist']);
            $statusArray = array(1, 2, 6, 7);
            if (!in_array($invoice->status_id, $statusArray))
                return createResponse(config('httpResponse.NOT_FOUND'), 'After send to client we can not dismiss invoice', ['error' => 'After send to client we can not dismiss invoice']);

            $inv = Invoice::where("id", $id);
            if ($invoice->parent_id == 0) {
                $inv = $inv->orWhere("parent_id", $id);
            } else {
                $inv = $inv->orWhere("id", $invoice->parent_id)->orWhere("parent_id", $invoice->parent_id);
            }
            $inv = $inv->get();

            foreach ($inv as $dismiss) {
                Invoice::where("id", $dismiss->id)->update(["dismiss_reason" => $request->input('dismiss_reason'),
                    "status_id" => "5",
                    "discount_type" =>'None',
                    "discount_amount" =>'0.00']);
                //add log
                $log = \App\Models\Backend\InvoiceLog::addLog($dismiss->id, 5);

                //update all timesheet entry
                $timesheet = \App\Models\Backend\Timesheet::where('invoice_id', '=', $dismiss->id)->update(['billing_status' => '0', 'invoice_amt' => '0', "invoice_id" => '', "invoice_desc" => '']);
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Invoice has been dismissed', ['message' => 'Invoice has been dismissed successfully']);
        } catch (\Exception $e) {
            app('log')->error("Invoice dismiss failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not dismiss invoice', ['error' => 'Could not dismiss invoice']);
        }
    }

    /**
     * Invoice status change save log
     *
     * @param  int  $id   //invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function invoiceStatusChange(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'status_id' => 'required'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $invoice = Invoice::find($id);

            if (!$invoice)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Invoice does not exist', ['error' => 'The Invoice does not exist']);

            if ($invoice->status_id == '4' || $invoice->status_id == '5' || $invoice->status_id == '10' || $invoice->status_id == $request->input('status_id')) {
                return createResponse(config('httpResponse.NOT_FOUND'), 'Can not changes invoice status', ['error' => 'Can not changes invoice status']);
            }
            if ($invoice->invoice_no != '') {
                Invoice::where("invoice_no", $invoice->invoice_no)->update(["status_id" => $request->input("status_id")]);
                // add log
                $allInvoice = Invoice::where("invoice_no", $invoice->invoice_no)->get();
                foreach ($allInvoice as $invoices) {
                    $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($invoices->id, $request->input('status_id'));
                }
            } else {
                Invoice::where("id", $id)->update(["status_id" => $request->input("status_id")]);
                // add log            
                $invoiceLog = \App\Models\Backend\InvoiceLog::addLog($id, $request->input('status_id'));
            }


            return createResponse(config('httpResponse.SUCCESS'), 'Invoice status has been changes successfully', ['message' => 'Invoice status has been changes successfully']);
        } catch (\Exception $e) {
            app('log')->error("Invoice Status not change " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update invoice status', ['error' => 'Could not update invoice status']);
        }
    }

    /**
     * get particular invoice List details
     *
     * @param  int  $id   //invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $invoiceLog = Invoice::invoiceData()->find($id);

            if (!isset($invoiceLog))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Invoice List does not exist', ['error' => 'The Invoice does not exist']);

            //send invoice information
            return createResponse(config('httpResponse.SUCCESS'), 'Invoice List data', ['data' => $invoiceLog]);
        } catch (\Exception $e) {
            app('log')->error("Invoice List details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Invoice List.', ['error' => 'Could not get Invoice List.']);
        }
    }

}

?>