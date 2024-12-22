<?php

namespace App\Http\Controllers\Backend\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\InvoiceRecurring;
use DB;

class InvoiceRecurringController extends Controller {

    /**
     * Get invoice recurring detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'invoice_recurring.created_on';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            //$recurring = InvoiceRecurring::invoiceRecurringData();
             $entity_ids = checkUserClientAllocation(loginUser());
            
            $recurring = InvoiceRecurring::with('createdBy:id,userfullname','modifiedBy:id,userfullname')
                        ->leftjoin("invoice_recurring_detail as ird", "ird.recurring_id", "invoice_recurring.id")
                        ->leftjoin("services as s", "s.id", "invoice_recurring.service_id")
                        ->leftjoin("frequency as f", "f.id", "invoice_recurring.frequency_id")
                        ->leftJoin('entity as e', function($query) use($entity_ids) {
                            $query->whereRaw('FIND_IN_SET(e.id,invoice_recurring.entity_id)');
                            if (is_array($entity_ids)){
                            $query->whereRaw("e.id IN(".implode(",",$entity_ids).")");
                            }
                        })
                        ->select('invoice_recurring.id', 'recurring_name', 'rec_type', 'entity_id', 'service_id', 'fixed_fee', 'frequency_id', 'next_due', 'last_due', 'inv_logic', 'inv_days', 'inv_weekday', 'repetition_type', 'repetition as times', 'last_due as repeat_date', 'last_due as repeat_indefinitely', 'notes', 'invoice_recurring.is_active','invoice_recurring.created_by','invoice_recurring.created_on', 'invoice_recurring.modified_by', 'invoice_recurring.modified_on', "ird.invoice_date", "s.service_name", "f.frequency_name", DB::raw("GROUP_CONCAT(DISTINCT e.billing_name) as entity_name"));
 
            
            //check client allocation
           /* $entity_ids = checkUserClientAllocation(loginUser());
            if (is_array($entity_ids))
                $recurring = $recurring->whereIn("e.id", $entity_ids);*/

            // echo $recurring = $recurring->toSql();exit;
            $recurring = $recurring->groupBy("invoice_recurring.id");
            //exit;
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $aliase = array("is_active" => "invoice_recurring");
                $recurring = search($recurring, $search, $aliase);
            }
            // for relation ship sorting
            if ($sortBy == 'modified_by') {
                $recurring = $recurring->leftjoin("user as u", "u.id", "invoice_recurring.$sortBy");
                $sortBy = 'userfullname';
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $recurring = $recurring->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $recurring->get()->count();

                $recurring = $recurring->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $recurring = $recurring->get();

                $filteredRecords = count($recurring);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Invoice Recurring list.", ['data' => $recurring], $pager);
       } catch (\Exception $e) {
            app('log')->error("Invoice Recurring listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Invoice Recurring", ['error' => 'Server error.']);
        }
    }

    /**
     * Store invoice Recurring details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        //try {
            $id = ($id == 0) ? '' : $id;
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'rec_type' => 'required:in:1,2',
                'entity_id' => 'required',
                'recurring_name' => 'required|unique:invoice_recurring,recurring_name,' . $id,
                'service_id' => 'required|numeric',
                'frequency_id' => 'required|numeric',
                'fixed_fee' => 'required|numeric',
                'next_due' => 'required|date',
                'inv_logic' => 'required|in:+,-',
                'inv_days' => 'required|numeric',
                'repetition_type' => 'in:1,2,3',
                'repeat_indefinitely' => 'required_if:repetition_type,1',
                'repeat_date' => 'required_if:repetition_type,2',
                'times' => 'required_if:repetition_type,3',
                'search' => 'json',
                'confirm' => 'in:0,1'
                    ], ['rec_name.required' => 'Recurring name field is required.',
                'service_id.numeric' => 'The Service name field is numeric.',
                'frequency_id.required' => 'Frequency field is required.',
                'frequency_id.numeric' => 'Frequency field is numeric.',
                'fixed_fee.numeric' => 'Fixed fee field is numeric.',
                'next_due.numeric' => 'The task field is numeric.',
                'repeat_date.required_if' => 'Repete type Required for Repetition type until.',
                'times.required_if' => 'No of times require for Repetition no of times.'
            ]);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $confirm = $request->has('confirm') ? $request->get('confirm') : '0';

            $recurringData = array();
            $eID = $request->input('entity_id');
            $recurringData = $this->generateRecurring($request);
            $entityName = \App\Models\Backend\Entity::select(DB::raw("GROUP_CONCAT(DISTINCT billing_name) as entity_name"))
                            ->whereRaw("id IN ($eID)")->first();

            if ($confirm == 1) {
                return $recurring = $this->addUpdateRecurring($request, $recurringData, $id);
            } else {
                $re = $request->all();
                $re['id'] = $id;
                return createResponse(config('httpResponse.SUCCESS'), 'Recurring Preview', ['data' => $recurringData, 'selectedData' => $re, 'entityName' => $entityName->entity_name]);
            }
        /*} catch (\Exception $e) {
            app('log')->error("Recurring creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add recurring', ['error' => 'Could not add recurring']);
        }*/
    }

    /**
     * Add Recurring
     * @param array $request
     * @param array $data
     * @return array
     */
    public function addUpdateRecurring($request, $data, $id) {

        //get days
        $frequencyList = \App\Models\Backend\Frequency::find($request->input('frequency_id'));
        $frequencyDay = $frequencyList->days;

        $entity_id = explode(",", $request->get('entity_id'));
        $repetition_type = $request->input('repetition_type');
        if ($repetition_type == 1) {
            $lastDate = date('Y-m-d', strtotime('+3 years', strtotime($startDate)));
        } else if ($repetition_type == 2) {
            $lastDate = date('Y-m-d', strtotime($request->input('repeat_date')));
        } else if ($repetition_type == 3) {
            $lastDate = date('Y-m-d', strtotime("+" . ($request->input('times') * $frequencyDay - 1) . " days", strtotime($request->input('next_due'))));
            $repetition = $request->input('times');
        }
        if ($id == '') {
            $invRecurring = InvoiceRecurring::create([
                        'recurring_name' => $request->input('recurring_name'),
                        'rec_type' => $request->input('rec_type'),
                        'notes' => $request->input('notes'),
                        'entity_id' => $request->input('entity_id'),
                        'service_id' => $request->input('service_id'),
                        'fixed_fee' => $request->input('fixed_fee'),
                        'frequency_id' => $request->input('frequency_id'),
                        'next_due' => date("Y-m-d", strtotime($request->input('next_due'))),
                        'last_due' => $lastDate,
                        'inv_logic' => $request->input('inv_logic'),
                        'inv_days' => $request->input('inv_days'),
                        'inv_weekday' => $request->input('inv_weekday'),
                        'repetition_type' => $request->input('repetition_type'),
                        'repetition' => ($repetition_type == '3') ? $repetition : '0',
                        'is_active' => 1,
                        "created_by" => loginUser(),
                        "created_on" => date('Y-m-d')]);

            // add value in recurring detail
            foreach ($data as $key => $value) {
                $invoiceRecurring['recurring_id'] = $invRecurring->id;
                $invoiceRecurring['start_date'] = $value['startDate'];
                $invoiceRecurring['end_date'] = $value['endDate'];
                $invoiceRecurring['invoice_date'] = $value['InvoiceDate'];
                $invoiceRecurring['created_by'] = loginUser();
                $invoiceRecurring['created_on'] = date('Y-m-d H:i:s');
                $invoiceRecurringData[] = $invoiceRecurring;
            }
            $invoiceRecurringDetail = \App\Models\Backend\InvoiceRecurringDetail::insert($invoiceRecurringData);

            $this->updateRecurringInBilling($request->input('service_id'), $request->input('entity_id'), $invRecurring->id);

            return createResponse(config('httpResponse.SUCCESS'), 'Recurring Add successfully', ['data' => $invRecurring]);
        } else {
            $recurring = InvoiceRecurring::find($id);

            if (!$recurring)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Recurring does not exist', ['error' => 'The Recurring does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated

            $updateData = filterFields(['recurring_name', 'rec_type', 'rec_name', 'entity_id', 'service_id', 'fixed_fee', 'frequency_id',
                'inv_logic', 'inv_days', 'inv_weekday', 'repetition_type', 'invoice_date', 'repetition_count'], $request);
            $updateData['last_due'] = $lastDate;
            $updateData['repetition'] = ($repetition_type == '3') ? $repetition : '0';
            $updateData['next_due'] = date("Y-m-d", strtotime($request->input('next_due')));
            $updateData['modified_on'] = date("Y-m-d H:i:s");
            $updateData['modified_by'] = loginUser();
            $recurring->update($updateData);
            // check value in recurring detail
            $recurringDetail = \App\Models\Backend\InvoiceRecurringDetail::where("recurring_id", $id)->get();
            $count = $recurringDetail->count();

            if ($count == count($data)) {//if bath count match then value update else recurring detail value delete and then inser again
                $i = 0;
                foreach ($data as $key => $value) {
                    $invoiceRecurringData = [
                        'recurring_id' => $id,
                        'start_date' => $value['startDate'],
                        'end_date' => $value['endDate'],
                        'invoice_date' => $value['InvoiceDate'],
                        'created_by' => loginUser(),
                        'created_on' => date('Y-m-d H:i:s')];

                    \App\Models\Backend\InvoiceRecurringDetail::where("id", $recurringDetail[$i]->id)->update($invoiceRecurringData);
                    $i++;
                }
            } else {
                \App\Models\Backend\InvoiceRecurringDetail::where("recurring_id", $id)->delete();
                foreach ($data as $key => $value) {
                    $invoiceRecurring['recurring_id'] = $id;
                    $invoiceRecurring['start_date'] = $value['startDate'];
                    $invoiceRecurring['end_date'] = $value['endDate'];
                    $invoiceRecurring['invoice_date'] = $value['InvoiceDate'];
                    $invoiceRecurring['created_by'] = loginUser();
                    $invoiceRecurring['created_on'] = date('Y-m-d H:i:s');
                    $invoiceRecurringData[] = $invoiceRecurring;
                }
                $invoiceRecurringDetail = \App\Models\Backend\InvoiceRecurringDetail::insert($invoiceRecurringData);
            }
            $this->updateRecurringInBilling($request->input('service_id'), $request->input('entity_id'), $id);
            return createResponse(config('httpResponse.SUCCESS'), 'Recurring Update successfully', ['data' => $recurring]);
        }
    }

    public function updateRecurringInBilling($serviceId, $entityId, $recurringId) {
        //check all entity recurring    
        //echo $recurringId;exit;
        \App\Models\Backend\BillingServices::where("service_id", $serviceId)
                ->where("is_latest", "1")
                ->where("recurring_id", $recurringId)->update(["recurring_id" => '']);
        //update recurring

        $billing = \App\Models\Backend\BillingServices::where("service_id", $serviceId)->where("is_latest", "1")
                ->whereRaw(DB::raw("entity_id IN ($entityId)"))
                ->update(["recurring_id" => $recurringId]);
    }

    /**
     * Created by: Pankaj
     * Created on: 22-08-2018
     * @param date $request
     */
    public function generateRecurring($request) {

        $startDate = $request->get('next_due');
        $repetition_type = $request->get('repetition_type');
        $invoiceday = ($request->has('inv_weekday')) ? $request->get('inv_weekday') : '';
        $invLogic = $request->get('inv_logic');
        $invDays = $request->get('inv_days');
        $frequency = $request->get('frequency_id');

        $invDateLogic = ' ' . $invLogic . $invDays . ' days';
        //get frequency
        $frequencyList = \App\Models\Backend\Frequency::find($frequency);
        $frequencyDay = $frequencyList->days;

        if ($repetition_type == 1) {
            $endDate = date('d-m-Y', strtotime('+3 years', strtotime($startDate)));
        } else if ($repetition_type == 2) {
            $endDate = date('d-m-Y', strtotime($request->get('repeat_date')));
        } else if ($repetition_type == 3) {
            $endDate = date('d-m-Y', strtotime("+" . ($request->get('times') * $frequencyDay - 1) . " days", strtotime($startDate)));
        }
        // showArray($endDate);exit;
        $firstDate = strtotime($startDate);
        $lastDate = strtotime($endDate);

        $generateInvoice = array();
        $i = $days = 0;
        $countEnddate = '';
        $temp = true;

        switch ($frequency) {
            case 1:// For Weekly frequency               
            case 2:// For Fortnightly
            case 5:// For Yearly   
                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);

                    if ($firstDate <= $lastDate) {
                        $endDate = date('Y-m-d', strtotime("+" . ($frequencyDay-1) . " days", $firstDate));
                        $firstDate = strtotime("+" . ($frequencyDay) . " days", $firstDate);
                        $invoiceDate = date('Y-m-d', strtotime($endDate . $invDateLogic));
                        $invoiceDate = date('Y-m-d', strtotime($invoiceDate . $invoiceday));
                        $generateInvoice[$i]['frequency'] = $frequencyList->frequency_name;
                        $generateInvoice[$i]['startDate'] = $startDate;
                        $generateInvoice[$i]['endDate'] = $endDate;
                        $generateInvoice[$i]['InvoiceDate'] = $invoiceDate;
                    }
                    $i++;
                }
                break;
            case 3:// For monthly frequency
                while ($firstDate <= $lastDate) {
                    // invoice start date
                    $startDate = date('Y-m-d', $firstDate);
                    //invoice end date
                    $days = date("t", $firstDate);
                    if ($firstDate <= $lastDate) {
                        $endDate = date('Y-m-d', strtotime("+" . ($days - 1) . " days", $firstDate));
                        $firstDate = strtotime("+" . ($days) . " days", $firstDate);
                        $invoiceDate = date('Y-m-d', strtotime($endDate . $invDateLogic));
                        $invoiceDate = date('Y-m-d', strtotime($invoiceDate . $invoiceday));
                        $generateInvoice[$i]['frequency'] = $frequencyList->frequency_name;
                        $generateInvoice[$i]['startDate'] = $startDate;
                        $generateInvoice[$i]['endDate'] = $endDate;
                        $generateInvoice[$i]['InvoiceDate'] = $invoiceDate;
                    }
                    $i++;
                }
                break;
            case 4:// For quartely frequency
                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);


                    if ($firstDate <= $lastDate) {
                        $countEnddate = strtotime("+ 3 month", $firstDate);
                        $endDate = date('Y-m-d', strtotime("- 1 days", $countEnddate));
                        $firstDate = $countEnddate;
                        $invoiceDate = date('Y-m-d', strtotime($endDate . $invDateLogic));
                        $invoiceDate = date('Y-m-d', strtotime($invoiceDate . $invoiceday));
                        $generateInvoice[$i]['frequency'] = $frequencyList->frequency_name;
                        $generateInvoice[$i]['startDate'] = $startDate;
                        $generateInvoice[$i]['endDate'] = $endDate;
                        $generateInvoice[$i]['InvoiceDate'] = $invoiceDate;
                    }
                    $i++;
                }
                break;

            case 6:// For half monthly frequency
                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);

                    if ($firstDate <= $lastDate) {
                        $days = date("t", $firstDate);
                        if ($days % 2 == 0) {
                            $startDate = date('Y-m-d', $firstDate);
                            $countEnddate = $TaskEndDate = strtotime("+" . ($days / 2) . " days", $firstDate);
                            $firstDate = strtotime("+" . ($days - $days / 2) . " days", $firstDate);
                        } else {
                            if ($temp) {
                                $startDate = date('Y-m-d', $firstDate);
                                $countEnddate = $TaskEndDate = strtotime("+" . $frequencyDay . " days", $firstDate);
                                $firstDate = strtotime("+" . ($days - $frequencyDay) . " days", $firstDate);
                                $temp = false;
                            } else {
                                $startDate = date('Y-m-d', $firstDate - 1);
                                $countEnddate = $TaskEndDate = strtotime("+" . ($days - $frequencyDay - 1) . " days", $firstDate);
                                $firstDate = strtotime("+" . ($days - $frequencyDay - 1) . " days", $firstDate);
                                $temp = true;
                            }
                        }

                        $endDate = date('Y-m-d', strtotime("-1 days", $countEnddate));
                        $invoiceDate = date('Y-m-d', strtotime($endDate . $invDateLogic));
                        $invoiceDate = date('Y-m-d', strtotime($invoiceDate . $invoiceday));
                        $generateInvoice[$i]['frequency'] = $frequencyList->frequency_name;
                        $generateInvoice[$i]['startDate'] = $startDate;
                        $generateInvoice[$i]['endDate'] = $endDate;
                        $generateInvoice[$i]['InvoiceDate'] = $invoiceDate;
                    }
                    $i++;
                }
                break;
        }
        return $generateInvoice;
    }

    public static function getService(Request $request, $entityId) {
        try {
            $services = InvoiceRecurring::getServices($request->input("recurring_id"),$entityId);
            if ($services->get()->count() == 0)
                return createResponse(config('httpResponse.SUCCESS'), 'Services does not exist for recurring', ['data' => array()]);

            return createResponse(config('httpResponse.SUCCESS'), 'Service List', ['data' => $services->get()]);
        } catch (\Exception $e) {
            app('log')->error("Client wise services failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get service for this client', ['error' => 'Could not get service for this client']);
        }
    }

    public static function getEntity(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'service_id' => 'required|numeric',
                'frequency_id' => 'required|numeric',
                'fixedfee' => 'required|numeric',
                'recurring_id' => 'required|numeric'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $entity = InvoiceRecurring::getEntity($request->input('service_id'), $request->input('fixedfee'), $request->input('frequency_id'), $request->input('recurring_id'));


            if (count($entity) == 0)
                return createResponse(config('httpResponse.SUCCESS'), 'Entity does not exist for recurring', ['data' => array()]);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity List', ['data' => $entity]);
        } catch (\Exception $e) {
            app('log')->error("Entity failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get entity for this criteria', ['error' => 'Could not get entity for this criteria']);
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
                'is_active' => 'required|in:0,1',
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $recurring = InvoiceRecurring::find($id);

            if (!$recurring)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Recurring does not exist', ['error' => 'The Recurring does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['is_active'], $request);
            //update the details
            $recurring->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Recurring has been updated successfully', ['message' => 'Recurring has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Recurring updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update recurring details.', ['error' => 'Could not update recurring details.']);
        }
    }

    /**
     * get particular recurring details
     *
     * @param  int  $id   //bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $recurring = InvoiceRecurring::invoiceRecurringData()->find($id);

            if (!isset($recurring))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Recurring does not exist', ['error' => 'The recurring does not exist']);

            //send recurring
            return createResponse(config('httpResponse.SUCCESS'), 'Recurring information data', ['data' => $recurring]);
        } catch (\Exception $e) {
            app('log')->error("Recurring information api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get recurring information.', ['error' => 'Could not get recurring information.']);
        }
    }

    /**
     * get particular recurring details
     *
     * @param  int  $id   //bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function previewShow($id) {
        try {
            $recurring = InvoiceRecurring::find($id);
            $entityName = \App\Models\Backend\Entity::select(DB::raw("GROUP_CONCAT(DISTINCT billing_name) as entity_name"))
                            ->whereRaw("id IN ($recurring->entity_id)")->first();
            $recurringDetail = \App\Models\Backend\InvoiceRecurringDetail::
                            leftjoin("invoice_recurring as ir", "ir.id", "invoice_recurring_detail.recurring_id")
                            ->leftjoin("frequency as f", "f.id", "ir.frequency_id")
                            ->select("f.frequency_name", "invoice_recurring_detail.start_date", "invoice_recurring_detail.end_date", "invoice_recurring_detail.invoice_date")
                            ->where("invoice_recurring_detail.recurring_id", $id)->get();

            if (!isset($recurringDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Recurring Detail does not exist', ['error' => 'The recurring detail does not exist']);

            //send recurring
            return createResponse(config('httpResponse.SUCCESS'), 'Recurring Detail information data', ['data' => $recurringDetail, 'entityName' => $entityName->entity_name]);
        } catch (\Exception $e) {
            app('log')->error("Recurring Detail information api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get recurring detail information.', ['error' => 'Could not get recurring detail information.']);
        }
    }

    public static function saveHistory($model, $col_name) {
        $yesNo = array('fixed_fee', 'is_active');
        $Dropdown = array('rec_type', 'entity_id', 'service_id', 'frequency_id', 'repetition_type');
        if (!empty($model->getDirty())) {
            $diff_col_val = array();
            foreach ($model->getDirty() as $key => $newValue) {
                if($key =='modified_on' || $key =='modified_by'){
                    continue;
                }
                $oldValue = $model->getOriginal($key);
                $colname = isset($col_name[$key]) ? $col_name[$key] : $key;
                $displayName = ucfirst($colname);
                if (in_array($key, $yesNo)) {
                    $constant = config('constant.yesNo');
                    if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                        $oldValue = $constant[$oldValue];
                    else
                        $oldValue = $defaultText;

                    $newValue = $constant[$newValue];
                }
                else if (in_array($key, $Dropdown)) {
                    if ($key == 'rec_type') {
                        $constant = config('constant.recType');
                        if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                            $oldValue = $constant[$oldValue];
                        else
                            $oldValue = $defaultText;

                        $newValue = $constant[$newValue];
                    }
                    if ($key == 'repetition_type') {
                        $constant = config('constant.recurringRepetition');
                        if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                            $oldValue = $constant[$oldValue];
                        else
                            $oldValue = $defaultText;

                        $newValue = $constant[$newValue];
                    } else if ($key == 'frequency_id') {
                        $frequency = \App\Models\Backend\Frequency::get()->pluck("frequency_name", "id")->toArray();
                        $oldValue = ($oldValue != '') ? $frequency[$oldValue] : '';
                        $newValue = ($newValue != '') ? $frequency[$newValue] : '';
                    } else if ($key == 'service_id') {
                        $service = \App\Models\Backend\Services::get()->pluck("service_name", "id")->toArray();
                        $oldValue = ($oldValue != '') ? $service[$oldValue] : '';
                        $newValue = ($newValue != '') ? $service[$newValue] : '';
                    } elseif ($key == 'entity_id') {
                        if ($oldValue != $newValue) {
                            $old = explode(",", $oldValue);
                            $new = explode(",", $newValue);
                            if($oldValue == ''){
                                $oldResult = array();
                                $newResult = $new;
                            }else{                           
                            $oldResult = array_diff($old, $new);
                            $newResult = array_diff($new, $old);
                            }
                            $removeName = $name = '';
                            if (!empty($oldResult)) {
                                $oldResult = implode(",", $oldResult);
                                $entityNames = \App\Models\Backend\Entity::select(DB::raw("GROUP_CONCAT(billing_name) as entity_name"))->whereRaw("id IN($oldResult)")->first();
                                $removeName = $entityNames->entity_name . " has been remove from recurring";
                            }
                            if (!empty($newResult)) {
                                $newResult = implode(",", $newResult);
                                $entityNames = \App\Models\Backend\Entity::select(DB::raw("GROUP_CONCAT(billing_name) as entity_name"))->whereRaw("id IN($newResult)")->first();
                                $name = $entityNames->entity_name . " has been added from recurring";
                            }
                            $newValue = $removeName . "," . $name;
                        }
                    }
                } else {
                    $displayName = ucfirst($colname);
                }

                if (isset($displayName) && $displayName != '' && isset($oldValue) && $oldValue != '' && isset($newValue) && $newValue != '')
                    $diff_col_val[$key] = ['display_name' => $displayName, 'old_value' => $oldValue, 'new_value' => $newValue];
            }
        }
        return $diff_col_val;
    }

    /**
     * get particular recurring history
     *
     * @param  int  $id   //entity id
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

            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'modified_on';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $history = \App\Models\Backend\InvoiceRecurringAudit::with('modifiedBy:userfullname,id')->where("recurring_id", $id);

            if (!isset($history))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The recurring does not exist', ['error' => 'The recurring does not exist']);

            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $history = search($history, $search);
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $history = $history->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $history->count();

                $history = $history->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $history = $history->get();

                $filteredRecords = count($history);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Recurring history data', ['data' => $history], $pager);
        } catch (\Exception $e) {
            app('log')->error("Recurring history api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get recurring history.', ['error' => 'Could not get recurring history.']);
        }
    }

}

?>