<?php

namespace App\Http\Controllers\Backend\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class BillingServicesSubactivityController extends Controller {

    /**
     * get particular billing subactivity details
     *
     * @param  int  $id   //entity_id
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'service_id' => 'required|numeric',
                    ], []);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $serviceMasterIds = config('constant.serviceMasterIds');
            $billingId = \App\Models\Backend\BillingServices::where("entity_id", $id)
                            ->where("service_id", $request->input('service_id'))
                            ->where("is_latest", "1")->where("is_active", "1");

            if ($billingId->count() == 0) {
                return createResponse(config('httpResponse.NOT_FOUND'), 'Billing value does not exist', ['error' => 'The Billing  value does not exist']);
            }
            $billingId = $billingId->first();
            if ($request->input('service_id') == 1) {
                $masterId = array(6, 7, 22);
                if ($billingId->bk_in_ff == 1) {
                    $masterId[] = '5';
                }
                $billingBkRph = \App\Models\Backend\BillingBKRPH::where("billing_id", $billingId->id)->where("is_latest", "1")->where("inc_in_ff", "1");

                if ($billingBkRph->count() > 0) {
                    foreach ($billingBkRph->get() as $billingBk) {
                        $masterId[] = $serviceMasterIds[$billingBk->service_id];
                    }
                }
                $inc_in_ff = $billingId->inc_in_ff;
                $fixed_fee = $billingId->fixed_fee;
            } else {
                $masterId = array(1, 2);
                $inc_in_ff = $billingId->inc_in_ff;
                $fixed_fee = $billingId->fixed_fee;
            }
            $serviceId = $request->input('service_id');
            // showArray($masterId);exit;
            $masterIds = implode(",", $masterId);
            $subactivityList = \App\Models\Backend\SubActivity::
                    leftjoin("billing_subactivity as bs", function($join) use ($id, $serviceId, $masterIds) {
                        $join->on("bs.subactivity_code", "=", "subactivity.subactivity_code");
                        $join->on("bs.is_latest", "=", DB::raw('1'));
                        $join->on("bs.service_id", "=", DB::raw($serviceId));
                        $join->on("bs.entity_id", "=", DB::raw($id));
                    })
                    ->leftjoin("master_activity as m", "m.id", "subactivity.master_id")
                    ->select("m.name as masterName", "subactivity.subactivity_code", "subactivity.subactivity_full_name", "subactivity.is_no_of_employee", "subactivity.is_inc_in_ff", "subactivity.is_frequency", "subactivity.is_price", "subactivity.is_fixed_fee", "bs.frequency_id", "bs.inc_in_ff", "bs.fixed_fee", "bs.price", "bs.fixed_value", "bs.no_of_value", "bs.id")
                    ->where("subactivity.visible", "1")
                    ->whereRaw("subactivity.master_id IN (" . $masterIds . ")")
                    ->groupby("subactivity.subactivity_code")
                    ->orderby("m.id", "asc")
                    ->orderby("subactivity.subactivity_code", "asc")
                    ->get();

            //showArray($subactivityList);exit;
            $subactivityArray = array();
            foreach ($subactivityList as $row) {
                $fixedFee = $price = 0;
                if($row->subactivity_code ==2002){
                    $fixedFee = 52;
                }
                if($row->subactivity_code ==2102){
                    $fixedFee = 28;
                }
                if($row->subactivity_code ==9){
                    $price = 40;
                }
                if($row->subactivity_code ==148){
                    $price = 28;
                }
                if($row->subactivity_code ==1230){
                    $price = 45;
                }
                $subactivityArray[$row->masterName][] = array(
                    "id" => $row->id,
                    "subactivity_code" => $row->subactivity_code,
                    "subactivity" => $row->subactivity_full_name,
                    "frequency_id" => ($row->frequency_id == 0 || $row->frequency_id == null) ? 1 : $row->frequency_id,
                    "inc_in_ff" => ($row->inc_in_ff == null) ? 0 : $row->inc_in_ff,
                    "fixed_fee" => ($row->fixed_fee == null) ? $fixedFee : $row->fixed_fee,
                    "price" => ($row->price == null) ? $price : $row->price,
                    "fixed_value" => ($row->fixed_value == null) ? 0 : $row->fixed_value,
                    "no_of_value" => ($row->no_of_value == null) ? 0 : $row->no_of_value,
                    "is_inc_in_ff" => $row->is_inc_in_ff,
                    "is_frequency" => $row->is_frequency,
                    "is_price" => $row->is_price,
                    "is_fixed_fee" => $row->is_fixed_fee,
                    "is_no_of_employee" => $row->is_no_of_employee
                );
            }
            if (empty($subactivityArray))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Billing subactivity does not exist', ['error' => 'The Billing subactivity does not exist']);

            //send software information
            return createResponse(config('httpResponse.SUCCESS'), 'Billing subactivity data', ['data' => $subactivityArray, 'inc_in_ff' => $inc_in_ff, 'fixed_fee' => $fixed_fee]);
        } catch (\Exception $e) {
            app('log')->error("Billing subactivity details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Billing subactivity.', ['error' => 'Could not get Billing subactivity.']);
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
        //try {
            $validator = app('validator')->make($request->all(), [
                'service_id' => 'required|numeric',
                'subactivity' => 'required|array',
                    ], []);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $billingSubactivity = \App\Models\Backend\BillingServicesSubactivity::where("entity_id", $id)
                            ->where("service_id", $request->input('service_id'))->where("is_latest", "1");

            $billingServices = \App\Models\Backend\BillingServices::where("entity_id", $id)->where("service_id", $request->input('service_id'))->where("is_latest", "1")->first();

            if ($billingSubactivity->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Billing Subactivity does not exist', ['error' => 'The Billing Subactivity does not exist']);

            $billingSubactivity = $billingSubactivity->get();
            //showArray($billingSubactivity);exit;
            $invoicesCount = \App\Models\Backend\Invoice::where("billing_id", $billingServices->id)->count();
            $subactivity = $request->input('subactivity');
            if ($invoicesCount == 0) {
                foreach ($subactivity as $row) {
                    if ($row['id'] == 0 || $row['id'] == null) {
                        $subactivityArray = array(
                            'billing_id' => $billingServices->id,
                            'entity_id' => $id,
                            'service_id' => $request->input('service_id'),
                            'subactivity_code' => isset($row['subactivity_code']) ? $row['subactivity_code'] : '0',
                            'inc_in_ff' => isset($row['inc_in_ff']) ? $row['inc_in_ff'] : '0',
                            'frequency_id' => isset($row['frequency_id']) ? $row['frequency_id'] : '0',
                            'fixed_fee' => isset($row['fixed_fee']) ? $row['fixed_fee'] : '0.00',
                            'price' => isset($row['price']) ? $row['price'] : '0.00',
                            'no_of_value' => isset($row['no_of_value']) ? $row['no_of_value'] : '0',
                            'fixed_value' => isset($row['fixed_value']) ? $row['fixed_value'] : '0',
                            'created_on' => date("Y-m-d H:i:s"),
                            'created_by' => loginUser());
                        // showArray($subactivityArray);exit;
                        \App\Models\Backend\BillingServicesSubactivity::create($subactivityArray);
                    } else {
                        $billingServiceSubactivity = \App\Models\Backend\BillingServicesSubactivity::select('id', 'subactivity_code', 'inc_in_ff', 'frequency_id', 'fixed_fee', 'price', 'fixed_value', 'no_of_value')
                                ->find($row['id']);
                        $arrayDiff = array_diff_assoc($row, $billingServiceSubactivity->getOriginal());
                        $arrayDiffOld = array_diff_assoc($billingServiceSubactivity->getOriginal(), $row);
                        $diffVal = array_merge_recursive($arrayDiffOld, $arrayDiff);

                        $updateData['inc_in_ff'] = isset($row['inc_in_ff']) ? $row['inc_in_ff'] : '0';
                        $updateData['frequency_id'] = isset($row['frequency_id']) ? $row['frequency_id'] : '0';
                        $updateData['fixed_fee'] = isset($row['fixed_fee']) ? $row['fixed_fee'] : '0.00';
                        $updateData['price'] = isset($row['price']) ? $row['price'] : '0.00';
                        $updateData['no_of_value'] = isset($row['no_of_value']) ? $row['no_of_value'] : '0';
                        $updateData['fixed_value'] = isset($row['fixed_value']) ? $row['fixed_value'] : '0';

                        if (!empty($diffVal)) {
                            $historyarray = \App\Models\Backend\BillingServicesSubactivity::saveAudit($diffVal);
                            if (!empty($historyarray)) {
                                $subactivityHistoryArray[$row['subactivity_code']] = $historyarray;
                            }
                           \App\Models\Backend\BillingServicesSubactivity::where('id',$row['id'])->update($updateData);
                        }
                    }
                }
            } else {
                $sub = 0;
                foreach ($subactivity as $row) {
                    if ($row['id'] != null && $row['id'] != '') {
                        $billingServiceSubactivity = \App\Models\Backend\BillingServicesSubactivity::select('id', 'subactivity_code', 'inc_in_ff', 'frequency_id', 'fixed_fee', 'price', 'fixed_value', 'no_of_value')->find($row['id']);
                        $arrayDiff = array_diff_assoc($row, $billingServiceSubactivity->getOriginal());
                        $arrayDiffOld = array_diff_assoc($billingServiceSubactivity->getOriginal(), $row);
                        $diffVal = array_merge_recursive($arrayDiffOld, $arrayDiff);
                        if (!empty($diffVal)) {
                            $sub = 1;
                            $historyarray = \App\Models\Backend\BillingServicesSubactivity::saveAudit($diffVal);
                            if (!empty($historyarray)) {
                                $subactivityHistoryArray[$row['subactivity_code']] = $historyarray;
                            }
                        }
                    }
                }

                if ($sub == 1) {
                    $billingSubactivity = \App\Models\Backend\BillingServicesSubactivity::where("entity_id", $id)
                            ->where("service_id", $request->input('service_id'))->where("is_latest", "1")->update(["is_latest"=>0]);
                    foreach ($subactivity as $row) {
                        $subactivityArray = array(
                            'billing_id' => $billingServices->id,
                            'entity_id' => $id,
                            'service_id' => $request->input('service_id'),
                            'subactivity_code' => isset($row['subactivity_code']) ? $row['subactivity_code'] : '0',
                            'inc_in_ff' => isset($row['inc_in_ff']) ? $row['inc_in_ff'] : '0',
                            'frequency_id' => isset($row['frequency_id']) ? $row['frequency_id'] : '0',
                            'fixed_fee' => isset($row['fixed_fee']) ? $row['fixed_fee'] : '0.00',
                            'price' => isset($row['price']) ? $row['price'] : '0.00',
                            'no_of_value' => isset($row['no_of_value']) ? $row['no_of_value'] : '0',
                            'fixed_value' => isset($row['fixed_value']) ? $row['fixed_value'] : '0',
                            'is_latest' => 1,
                            'created_on' => date("Y-m-d H:i:s"),
                            'created_by' => loginUser());

                        if ($row['id'] != 0 && $row['id'] != null) {
                            \App\Models\Backend\BillingServicesSubactivity::where("id", $row['id'])->update(["is_latest" => "0"]);
                        }
                        \App\Models\Backend\BillingServicesSubactivity::create($subactivityArray);
                    }
                }
            }
            //showArray($subactivityHistoryArray);exit;
            // add history
            if (!empty($subactivityHistoryArray)) {
                \App\Models\Backend\BillingServicesSubactivity::saveHistory($subactivityHistoryArray, $id, $request->input('service_id'));
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Billing Subactivity Info has been updated successfully', ['message' => 'Billing Subactivity Info has been updated successfully']);
        /*} catch (\Exception $e) {
            app('log')->error("Bk Billing Info updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Bk Billing Subactivity.', ['error' => 'Could not update Bk Billing Subactivity.']);
        }*/
    }
    
    
     public static function updateBkSub($id,$subactivityData) {
        //try {
           /* $validator = app('validator')->make($request->all(), [
                'service_id' => 'required|numeric',
                'subactivity' => 'required|array',
                    ], []);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
*/
        // $request->input('service_id') = 1;
            $billingSubactivity = \App\Models\Backend\BillingServicesSubactivity::where("entity_id", $id)
                            ->where("service_id", 1)->where("is_latest", "1");

            $billingServices = \App\Models\Backend\BillingServices::where("entity_id", $id)->where("service_id", 1)->where("is_latest", "1")->first();

            if ($billingSubactivity->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Billing Subactivity does not exist', ['error' => 'The Billing Subactivity does not exist']);

            $billingSubactivity = $billingSubactivity->get();
            //showArray($billingSubactivity);exit;
            $invoicesCount = \App\Models\Backend\Invoice::where("billing_id", $billingServices->id)->count();
            $subactivity = json_decode($subactivityData,true);
            if ($invoicesCount == 0) {
                foreach ($subactivity as $row) {
                    if ($row['id'] == 0 || $row['id'] == null) {
                        $subactivityArray = array(
                            'billing_id' => $billingServices->id,
                            'entity_id' => $id,
                            'service_id' => 1,
                            'subactivity_code' => isset($row['subactivity_code']) ? $row['subactivity_code'] : '0',
                            'inc_in_ff' => isset($row['inc_in_ff']) ? $row['inc_in_ff'] : '0',
                            'frequency_id' => isset($row['frequency_id']) ? $row['frequency_id'] : '0',
                            'fixed_fee' => isset($row['fixed_fee']) ? $row['fixed_fee'] : '0.00',
                            'price' => isset($row['price']) ? $row['price'] : '0.00',
                            'no_of_value' => isset($row['no_of_value']) ? $row['no_of_value'] : '0',
                            'fixed_value' => isset($row['fixed_value']) ? $row['fixed_value'] : '0',
                            'created_on' => date("Y-m-d H:i:s"),
                            'created_by' => loginUser());
                        // showArray($subactivityArray);exit;
                        \App\Models\Backend\BillingServicesSubactivity::create($subactivityArray);
                    } else {
                        $billingServiceSubactivity = \App\Models\Backend\BillingServicesSubactivity::select('id', 'subactivity_code', 'inc_in_ff', 'frequency_id', 'fixed_fee', 'price', 'fixed_value', 'no_of_value')
                                ->find($row['id']);
                        $arrayDiff = array_diff_assoc($row, $billingServiceSubactivity->getOriginal());
                        $arrayDiffOld = array_diff_assoc($billingServiceSubactivity->getOriginal(), $row);
                        $diffVal = array_merge_recursive($arrayDiffOld, $arrayDiff);

                        $updateData['inc_in_ff'] = isset($row['inc_in_ff']) ? $row['inc_in_ff'] : '0';
                        $updateData['frequency_id'] = isset($row['frequency_id']) ? $row['frequency_id'] : '0';
                        $updateData['fixed_fee'] = isset($row['fixed_fee']) ? $row['fixed_fee'] : '0.00';
                        $updateData['price'] = isset($row['price']) ? $row['price'] : '0.00';
                        $updateData['no_of_value'] = isset($row['no_of_value']) ? $row['no_of_value'] : '0';
                        $updateData['fixed_value'] = isset($row['fixed_value']) ? $row['fixed_value'] : '0';

                        if (!empty($diffVal)) {
                            $historyarray = \App\Models\Backend\BillingServicesSubactivity::saveAudit($diffVal);
                            if (!empty($historyarray)) {
                                $subactivityHistoryArray[$row['subactivity_code']] = $historyarray;
                            }
                           \App\Models\Backend\BillingServicesSubactivity::where('id',$row['id'])->update($updateData);
                        }
                    }
                }
            } else {
                $sub = 0;
                foreach ($subactivity as $row) {
                    if ($row['id'] != null && $row['id'] != '') {
                        $billingServiceSubactivity = \App\Models\Backend\BillingServicesSubactivity::select('id', 'subactivity_code', 'inc_in_ff', 'frequency_id', 'fixed_fee', 'price', 'fixed_value', 'no_of_value')->find($row['id']);
                        $arrayDiff = array_diff_assoc($row, $billingServiceSubactivity->getOriginal());
                        $arrayDiffOld = array_diff_assoc($billingServiceSubactivity->getOriginal(), $row);
                        $diffVal = array_merge_recursive($arrayDiffOld, $arrayDiff);
                        if (!empty($diffVal)) {
                            $sub = 1;
                            $historyarray = \App\Models\Backend\BillingServicesSubactivity::saveAudit($diffVal);
                            if (!empty($historyarray)) {
                                $subactivityHistoryArray[$row['subactivity_code']] = $historyarray;
                            }
                        }
                    }
                }

                if ($sub == 1) {
                    $billingSubactivity = \App\Models\Backend\BillingServicesSubactivity::where("entity_id", $id)
                            ->where("service_id", 1)->where("is_latest", "1")->update(["is_latest"=>0]);
                    foreach ($subactivity as $row) {
                        $subactivityArray = array(
                            'billing_id' => $billingServices->id,
                            'entity_id' => $id,
                            'service_id' => 1,
                            'subactivity_code' => isset($row['subactivity_code']) ? $row['subactivity_code'] : '0',
                            'inc_in_ff' => isset($row['inc_in_ff']) ? $row['inc_in_ff'] : '0',
                            'frequency_id' => isset($row['frequency_id']) ? $row['frequency_id'] : '0',
                            'fixed_fee' => isset($row['fixed_fee']) ? $row['fixed_fee'] : '0.00',
                            'price' => isset($row['price']) ? $row['price'] : '0.00',
                            'no_of_value' => isset($row['no_of_value']) ? $row['no_of_value'] : '0',
                            'fixed_value' => isset($row['fixed_value']) ? $row['fixed_value'] : '0',
                            'is_latest' => 1,
                            'created_on' => date("Y-m-d H:i:s"),
                            'created_by' => loginUser());

                        if ($row['id'] != 0 && $row['id'] != null) {
                            \App\Models\Backend\BillingServicesSubactivity::where("id", $row['id'])->update(["is_latest" => "0"]);
                        }
                        \App\Models\Backend\BillingServicesSubactivity::create($subactivityArray);
                    }
                }
            }
            //showArray($subactivityHistoryArray);exit;
            // add history
            /*if (!empty($subactivityHistoryArray)) {
                \App\Models\Backend\BillingServicesSubactivity::saveHistory($subactivityHistoryArray, $id, 1);
            }*/
            return createResponse(config('httpResponse.SUCCESS'), 'Billing Subactivity Info has been updated successfully', ['message' => 'Billing Subactivity Info has been updated successfully']);
        /*} catch (\Exception $e) {
            app('log')->error("Bk Billing Info updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Bk Billing Subactivity.', ['error' => 'Could not update Bk Billing Subactivity.']);
        }*/
    }

    /**
     * update billing subactivity history details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $entityID
     * @return Illuminate\Http\JsonResponse
     */
    public function history(Request $request, $entityId) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'service_id' => 'required|numeric',
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'modified_on';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $history = \App\Models\Backend\BillingServicesSubactivityAudit::with("modifiedBy:id,userfullname")
                    ->where("entity_id", $entityId)
                    ->where("service_id", $request->get('service_id'));

            if (!isset($history))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The billing Subactivity history does not exist', ['error' => 'The billing Subactivity history does not exist']);

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
            return createResponse(config('httpResponse.SUCCESS'), 'Billing Subactivity history', ['data' => $history, 'format' => 3], $pager);
        } catch (\Exception $e) {
            app('log')->error("Could not load billing Subactivity history : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not load billing Subactivity history.', ['error' => 'Could not load billing  Subactivity history.']);
        }
    }

}

?>