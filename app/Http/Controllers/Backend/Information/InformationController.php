<?php

namespace App\Http\Controllers\Backend\Information;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class InformationController extends Controller {

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
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'information.created_on';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];

        $information = \App\Models\Backend\Information::informationData();

        //check client allocation
        $entity_ids = checkUserClientAllocation(loginUser());
        if (is_array($entity_ids)) {
            $information = $information->whereRaw("e.id IN (" . implode(",", $entity_ids) . ")");
        }

        if (!empty($id)) {
            if ($id != 9) {
                $information = $information->where("information.stage_id", $id);
            }
        } else {
            $information = $information->where("information.stage_id", 1);
        }
        $information = $information->groupBy("information.id");

        //for search
        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array("entity_id" => "information", "start_period" => "information", "created_by" => "information","parent_id" => "e");
            $information = search($information, $search, $alias);
        }

        if ($request->has('technical_account_manager')) {
            $tam = $request->get('technical_account_manager');
            $information = $information->whereRaw("JSON_EXTRACT(information.team_json, '$.9') = '" . $tam . "'");
        }

        if ($request->has('team_leader')) {
            $tl = $request->get('team_leader');
            $information = $information->whereRaw("JSON_EXTRACT(information.team_json, '$.60') = '" . $tl . "'");
        }
        if ($request->has('associate_team_lead')) {
            $atl = $request->get('associate_team_lead');
            $information = $information->whereRaw("JSON_EXTRACT(information.team_json, '$.61') = '" . $atl . "'");
        }

        if ($request->has('team_member')) {
            $tm = $request->get('team_member');
            $information = $information->whereRaw("JSON_EXTRACT(information.team_json, '$.10') = '" . $tm . "'");
        }

        // for relation ship sorting
        if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
            $information = $information->leftjoin("user as u", "u.id", "information.$sortBy");
            $sortBy = 'userfullname';
        }

        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            if ($sortBy != '') {
                $information = $information->orderBy($sortBy, $sortOrder)->get();
            } else {
                $information = $information->orderByRaw("information.id desc")->get();
            }
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $information->get()->count();
            if ($sortBy != '') {
                $information = $information->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
            } else {
                $information = $information->orderByRaw("information.id desc")
                        ->skip($skip)
                        ->take($take);
            }
            $information = $information->get();

            $filteredRecords = count($information);

            $pager = ['sortBy' => $sortByName,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        $information = \App\Models\Backend\Information::arrangeData($information);

        if ($request->has('excel') && $request->get('excel') == 1) {


            //format data in array
            $datas = $information;
            $column = array();

            $column[] = ['Sr.No','Parent Trading Name', 'Client Name', 'Subject Line','Status', 'Total Information','Received','Internal Resolved','Partial','Pending', 'TAM', 'ATL', 'TL', 'Staff', 'Created On', 'Created By', 'Modified By', 'Modified On'];

            if (!empty($datas)) {
                $columnData = array();
                $i = 1;
                foreach ($datas as $data) {
                    $columnData[] = $i;
                    $columnData[] = $data['parent_name'];
                    $columnData[] = $data['billing_name'];
                    $columnData[] = $data['subject'];
                    $columnData[] = $data['stageId']['stage_name'];
                    $columnData[] = $data['totalInformation'];
                    $columnData[] = ($data['received_count'] > 0) ? $data['received_count'] : '0';
                    $columnData[] = ($data['resolved_count'] > 0) ? $data['resolved_count'] : '0';
                    $columnData[] = ($data['partial_count'] > 0) ? $data['partial_count'] : '0';  
                    $columnData[] = ($data['pending_count'] > 0) ? $data['pending_count'] : '0';  
                    $columnData[] = $data['tam_name'];
                    $columnData[] = $data['atl_name'];
                    $columnData[] = $data['tl_name'];
                    $columnData[] = $data['team_member'];
                    $columnData[] = $data['created_on'];
                    $columnData[] = $data['createdBy']['created_by'];
                    $columnData[] = $data['modified_on'];
                    $columnData[] = $data['modifiedBy']['modified_by'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'InformationList', 'xlsx', 'A1:R1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "Information list.", ['data' => $information], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Information listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Information", ['error' => 'Server error.']);
          } */
    }

    /**
     * Send Back to TL details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function sendBack(Request $request, $id) {
        // try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'stage_id' => 'required',
            'type' => 'required|in:1,2,3',
            'send_back_reason' => 'required'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $send_back_reason = $request->get('send_back_reason');
        $type = $request->input('type');
        $information = \App\Models\Backend\Information::find($id);
        if (!$information)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Information does not exist', ['error' => 'The Information does not exist']);

        if ($information->stage_id == $request->input('stage_id'))
            return createResponse(config('httpResponse.NOT_FOUND'), 'Can not change stage, Already is in Same stage', ['error' => 'Can not change stage, Already is in Same stage']);

        $reason = 'sendback_reason_tl';
        $by = 'TAM';
        if ($type == '1') {
            $reason = 'sendback_reason_tm';
            $by = 'ATL';
            $informationLog = \App\Models\Backend\InformationLog::addLog($id, 21);
        } else if ($type == '2') {
            $reason = 'sendback_reason_atl';
            $by = 'TL';
            $informationLog = \App\Models\Backend\InformationLog::addLog($id, 22);
        } else {
            $informationLog = \App\Models\Backend\InformationLog::addLog($id, 23);
        }

        $entityAllocation = \App\Models\Backend\EntityAllocation::where("entity_id", $information->entity_id)
                        ->where("service_id", 1)->first();
        $decodeAllocation = \GuzzleHttp\json_decode($entityAllocation->allocation_json, true);
        //$atl = '';
        /* if (isset($decodeAllocation[61]) && $decodeAllocation[61] != '')
          $atl = $decodeAllocation[61]; */
        if (isset($decodeAllocation[60]) && $decodeAllocation[60] != '')
            $tl = $decodeAllocation[60];
        if (isset($decodeAllocation[10]) && $decodeAllocation[10] != '')
            $staff = $decodeAllocation[10];

        $user = \App\Models\User::select(DB::raw("GROUP_CONCAT(email) as email"))
                ->whereIn("id", [$tl, $staff])
                ->first();
        \App\Models\Backend\Information::where('id', $id)->update(["stage_id" => $request->input('stage_id'), $reason => $send_back_reason]);
        // add log    
        $entityDetail = \App\Models\Backend\Entity::where('id', $information->entity_id)->first();
        //\App\Models\Backend\Entity::where
        $data['to'] = $user->email;
        $data['cc'] = str_replace(' ', '', $request->input('cc'));
        $data['content'] = 'Hi <br/><br/> Please check below reason <br/> Reason: ' . $send_back_reason;
        $data['subject'] = $entityDetail->trading_name . ' send back by ' . $by;

        //send mail to the client
        storeMail('', $data);
        $informationLog = \App\Models\Backend\InformationLog::addLog($id, $request->input('stage_id'));

        return createResponse(config('httpResponse.SUCCESS'), 'Information is send back to ' . $by . ' successfully', ['message' => 'Information is assigned to ' . $type . ' successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Information is send back failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not send back Information', ['error' => 'Could not send back Information']);
          } */
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
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'stage_id' => 'required|numeric',
            'reminder' => 'numeric',
            'infoDetail' => 'json'
                ], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        // Filter the fields which need to be updated
        $loginUser = loginUser();
        $information = \App\Models\Backend\Information::find($id);

        $infoDetail = \GuzzleHttp\json_decode($request->input('infoDetail'));

        if ($request->has('final_submit') && $request->input('final_submit') == 1) {
            $stage_id = 8;
            foreach ($infoDetail as $row) {

                if ($row->status_id == 2 || $row->status_id == 1) {
                    $stage_id = 7;
                }
                $infoData['status_id'] = !empty($row->status_id) ? $row->status_id : 0;
                $infoData['modified_on'] = date('Y-m-d H:i:s');
                $infoData['modified_by'] = $loginUser;
                \App\Models\Backend\InformationDetail::where('id', $row->id)->update($infoData);
            }
            $updateData['stage_id'] = $stage_id;
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;

            $information->update($updateData);
            \App\Models\Backend\InformationLog::addLog($id, $stage_id, loginUser());

            if ($stage_id == 7) {
                // for Partial information crete again 
                $infomartionData = \App\Models\Backend\Information::where("entity_id", $information->entity_id)->where("stage_id", "1");
                if ($infomartionData->count() > 0) {
                    $partialDetail = \App\Models\Backend\InformationDetail::where("information_id", $id)->where('status_id', "2")->get();
                    foreach ($partialDetail as $value) {
                        $value['information_id'] = $infomartionData->id;
                        \App\Models\Backend\InformationDetail::create($value);
                    }
                    \App\Models\Backend\Information::where("id", $infomartionData->id)->update(["is_merge" => 1]);
                } else {
                    $infoCopy = \App\Models\Backend\Information::create([
                                'entity_id' => $information->entity_id,
                                'stage_id' => 1,
                                'subject' => $information->subject,
                                'start_period' => $information->start_period,
                                'end_period' => $information->end_period,
                                'frequency_id' => $information->frequency_id,
                                'team_json' => $information->team_json,
                                'is_partial' => 1,
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => 1]);
                    $partialDetailValue = \App\Models\Backend\InformationDetail::where("information_id", $id)->where("status_id", "=", "2")->get();

                    foreach ($partialDetailValue as $p) {
                        if (!empty($partialDetailValue)) {
                            $informationDetail = \App\Models\Backend\InformationDetail::create([
                                        "information_id" => $infoCopy->id,
                                        "start_period" => $p->start_period,
                                        "end_period" => $p->end_period,
                                        "bank_info_id" => $p->bank_info_id,
                                        "bank_other" => !empty($p->bank_other) ? $p->bank_other : '',
                                        "type_account" => !empty($p->type_account) ? $p->type_account : '',
                                        "account_no" => !empty($p->account_no) ? $p->account_no : '',
                                        "status_id" => !empty($p->status_id) ? $p->status_id : '',
                                        "befree_comment" => !empty($p->befree_comment) ? $p->befree_comment : '',
                                        "answer_type" => !empty($p->answer_type) ? $p->answer_type : '',
                                        "client_comment" => !empty($p->client_comment) ? $p->client_comment : '',
                                        "created_by" => loginUser(),
                                        "created_on" => date('Y-m-d H:i:s')]);
                        }
                    }
                }
            }
        } else {
            if (!empty($infoDetail)) {
                foreach ($infoDetail as $row) {
                    if ($request->input('stage_id') == 5 && $row->status_id == 2 && empty($row->befree_comment)) {

                        return createResponse(config('httpResponse.UNPROCESSED'), "Befree comment not blank", ['error' => "Befree comment not blank"]);
                    }
                    $infoData['befree_comment'] = !empty($row->befree_comment) ? $row->befree_comment : '';
                    $infoData['status_id'] = !empty($row->status_id) ? $row->status_id : 0;
                    $infoData['start_period'] = !empty($row->start_period) ? $row->start_period : '';
                    $infoData['end_period'] = !empty($row->end_period) ? $row->end_period : '';
                    $infoData['modified_on'] = date('Y-m-d H:i:s');
                    $infoData['modified_by'] = $loginUser;
                    \App\Models\Backend\InformationDetail::where('id', $row->id)->update($infoData);
                }
            }
            $checkALLREsolved = \App\Models\Backend\InformationDetail::where("information_id", $id)->whereRaw("status_id NOT IN (5,3)");
            $checkAllAditionalQuery = \App\Models\Backend\InformationAdditionalInfo::where("information_id", $id)->where("is_deleted", "=", "0");
            
            if ($checkALLREsolved->count() == 0 && $checkAllAditionalQuery->count() == 0) {
                \App\Models\Backend\Information::where("id", $id)->update(["stage_id" => 8]);
                \App\Models\Backend\InformationLog::addLog($id, 8, loginUser());
            } else {
                if ($request->input('stage_id') != 5) {
                    $updateData['reminder'] = $request->input('reminder');
                    $reminder_days = $request->input('reminder');
                    if ($reminder_days > 0) {
                        $nextremiderDate = date('Y-m-d', strtotime(date("Y-m-d") . "+" . $reminder_days . " days"));
                        $updateData['reminder_date'] = $nextremiderDate;
                    }
                }
                $updateData['subject'] = $request->input('subject');
                $updateData['stage_id'] = $request->input('stage_id');
                $updateData['modified_on'] = date('Y-m-d H:i:s');
                $updateData['modified_by'] = $loginUser;

                $information->update($updateData);
                \App\Models\Backend\InformationLog::addLog($id, $request->input('stage_id'), loginUser());
            }
        }

        return createResponse(config('httpResponse.SUCCESS'), 'Information has been updated successfully', ['message' => 'Information has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Information updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get information details.', ['error' => 'Could not get information log details.']);
          } */
    }

    /**
     * log particular Information
     *
     * @param  int  $id   //invoice id
     * @return Illuminate\Http\JsonResponse
     */
    public function log(Request $request, $id) {
       // try {
            $informationlog = \App\Models\Backend\InformationLog::with("statusId:id,status_name")
                    ->where("information_id", $id);
            if ($informationlog->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Information Log does not exist', ['error' => 'The Information Log does not exist']);
            
            $log= array();
            foreach($informationlog->get() as $q){  
             if ($q->status_id == 6) {
                $q->modified_by = array("id" =>$q->modified_by, "modified_by" => 'Sent by client');
            }else{
                $userName = \App\Models\User::where("id",$q->modified_by)->select("userfullname");
                if($userName->count() > 0){
                    $userName = $userName->first();
                 $q->modified_by = array("id" =>$q->modified_by,"modified_by" => $userName->userfullname);
                }else if($q->status_id == 5){
                 $q->modified_by = array("id" =>$q->modified_by,"modified_by" => "Pending from client");   
                }
            }
            $log[] = $q;
        }          
            //information log
            return createResponse(config('httpResponse.SUCCESS'), 'Information Log data', ['data' =>$log]);
        /*} catch (\Exception $e) {
            app('log')->error("Information log updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get information log details.', ['error' => 'Could not get information log details.']);
        }*/
    }

    public function reminderLog(Request $request, $id) {
        try {
            $informationlog = \App\Models\Backend\InformationReminderLog::with("createdBy:id,userfullname as created_by")
                    ->where("information_id", $id);
            if ($informationlog->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Information Reminder Log does not exist', ['error' => 'The Information Log does not exist']);

            //information log
            return createResponse(config('httpResponse.SUCCESS'), 'Information Reminder Log data', ['data' => $informationlog->get()]);
        } catch (\Exception $e) {
            app('log')->error("Information Reminder log updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get information Reminder log details.', ['error' => 'Could not get information Reminder log details.']);
        }
    }

    /**
     * Information Stages particular Information
     *
     * @param  int  $id   //Information id
     * @return Illuminate\Http\JsonResponse
     */
    public function informationStage() {
        try {
            $informationStage = \App\Models\Backend\InformationStage::where('is_active', '=', 1)
                            ->where('applicable', '=', 1)
                            ->orderBy("id")->get();
            //Information log
            return createResponse(config('httpResponse.SUCCESS'), 'Information Stages List', ['data' => $informationStage]);
        } catch (\Exception $e) {
            app('log')->error("Information stage detail failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Information stage details.', ['error' => 'Could not get Information stage details.']);
        }
    }

    /**
     * Information Move to TL/STtaff
     *
     * @param  int  $id   //information id
     * @return Illuminate\Http\JsonResponse
     */
    public function moveToTlTam(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'stage_id' => 'required',
            'type' => 'required|in:1,2,3'
                ], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $information = \App\Models\Backend\Information::find($id);

        if (!$information)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Information does not exist', ['error' => 'The Information does not exist']);

        $currentInfoStage = $information->stage_id;

        if ($request->has('type') == 1 && $information->stage_id != '2') {
            $type = 'ATL';
            $sId = 10;
        } else if ($request->has('type') == 2 && $information->stage_id != '3') {
            $type = 'TL';
            $sId = 11;
        } else if ($request->has('type') == 3 && $information->stage_id != '4') {
            $type = 'TAM';
            $sId = 12;
        }
        \App\Models\Backend\InformationLog::addLog($id, $sId);
        $information->update(["stage_id" => $request->input('stage_id')]);
        \App\Models\Backend\InformationLog::addLog($id, $request->input('stage_id'));

        return createResponse(config('httpResponse.SUCCESS'), 'Information is assigned to ' . $type . ' successfully', ['message' => 'Information is assigned to ' . $type . ' successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Information Stage not change " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update information stage', ['error' => 'Could not update information stage']);
          } */
    }

    /**
     * get particular Information List details
     *
     * @param  int  $id   //Information id
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id) {
        //try {
        $informationGroup = array();

        $information = \App\Models\Backend\Information::informationData()->find($id);
        if (!empty($information)) {
            $informationGroup['basic'] = $information;
        }

        $informationDetail = \App\Models\Backend\InformationDetail::with("createdBy:id,userfullname as created_by")
                ->select("information_detail.id", "information_detail.information_id", "information_detail.bank_other", "information_detail.start_period", "information_detail.end_period", "information_detail.type_account", "information_detail.account_no", "information_detail.status_id", "information_detail.befree_comment", "information_detail.status_comment", "information_detail.answer_type", "information_detail.client_comment", "information_detail.created_on", "information_detail.created_by")
                ->where('information_id', '=', $id)
                ->get();
        // if bank and other info added after 
        if ($information->stage_id <= 4 && $information->is_partial == 0) {
            $bank_otherId = \App\Models\Backend\InformationDetail::where("account_no", "=", 0)
                    ->where("information_id", $id);

            $bank_infoId = \App\Models\Backend\InformationDetail::where("account_no", "!=", 0)
                    ->where("information_id", $id);

            $bankInfo = \App\Models\Backend\EntityBankInfo::leftjoin("banks as b", "b.id", "entity_bank_info.bank_id")
                    ->leftjoin("bank_type as bt", "bt.id", "entity_bank_info.type_id")
                    ->select("b.bank_name", "bt.type_name", "entity_bank_info.account_no", "entity_bank_info.notes", "entity_bank_info.id")
                    ->where("entity_bank_info.entity_id", $information->entity_id)
                    ->where("entity_bank_info.viewing_rights", "0")
                    ->where("entity_bank_info.auto_feed_up", "0")
                    ->where("entity_bank_info.is_active", "1")
                    ->where("b.is_active", "1")
                    ->where("bt.is_active", "1");
            if ($bank_infoId->count() > 0) {
                $bank_infoId = $bank_infoId->get()->pluck("bank_info_id", "id")->toArray();
                $bank_infoId = implode(",", $bank_infoId);
                $bankInfo = $bankInfo->whereRaw("entity_bank_info.id NOT IN ($bank_infoId)");
            }


            $otherInfo = \App\Models\Backend\EntityOtherInfo::leftjoin("other_account as o", "o.id", "entity_other_info.otheraccount_id")
                    ->select("o.account_name", "entity_other_info.id", "entity_other_info.befree_comment")
                    ->where('entity_other_info.entity_id', $information->entity_id)
                    ->where("entity_other_info.view_access", "!=", "1")
                    ->where('entity_other_info.is_active', "1")
                    ->where('o.is_active', "1");
            if ($bank_otherId->count() > 0) {
                $bank_otherId = $bank_otherId->get()->pluck("bank_info_id", "id")->toArray();
                $bank_otherId = implode(",", $bank_otherId);
                $otherInfo = $otherInfo->whereRaw("entity_other_info.id NOT IN ($bank_otherId)");
            }

            $infoDetail = $infoOtherDetail = array();
            // ADD bank info where viewing rights no we will add directly on table
            // afetr add on this table then change on bank info we will not update in this table
            if ($bankInfo->count() == 0)
                foreach ($bankInfo->get() as $bn) {
                    $acc = substr($bn->account_no, -4);
                    $infoDetail[] = array(
                        "information_id" => $information->id,
                        "start_period" => $information->start_period,
                        "end_period" => $information->end_period,
                        "bank_info_id" => $bn->id,
                        "bank_other" => $bn->bank_name,
                        "type_account" => $bn->type_name,
                        "account_no" => $acc,
                        "befree_comment" => $bn->notes,
                        "status_id" => 0,
                        "created_on" => date('Y-m-d H:i:s'),
                        "created_by" => 1);
                }

            \App\Models\Backend\InformationDetail::insert($infoDetail);
            foreach ($otherInfo->get() as $on) {
                $infoOtherDetail[] = array(
                    "information_id" => $information->id,
                    "start_period" => $information->start_period,
                    "end_period" => $information->end_period,
                    "bank_info_id" => $on->id,
                    "bank_other" => $on->account_name,
                    "type_account" => '',
                    "account_no" => '',
                    "befree_comment" => $on->befree_comment,
                    "status_id" => 0,
                    "created_on" => date('Y-m-d H:i:s'),
                    "created_by" => 1);
            }

            \App\Models\Backend\InformationDetail::insert($infoOtherDetail);
        }
        $informationDetailAgain = \App\Models\Backend\InformationDetail::with("createdBy:id,userfullname as created_by")
                ->select("information_detail.id", "information_detail.information_id", "information_detail.bank_other", "information_detail.start_period", "information_detail.end_period", "information_detail.type_account", "information_detail.account_no", "information_detail.status_id", "information_detail.befree_comment", "information_detail.status_comment", "information_detail.answer_type", "information_detail.client_comment", "information_detail.created_on", "information_detail.created_by")
                ->where('information_id', '=', $id)
                ->get();
        if (!empty($informationDetailAgain)) {
            foreach ($informationDetailAgain as $infoDetail) {
                $infoDetail['document'] = array();
                $informationDocument = \App\Models\Backend\InformationDetailDocument::with('createdBy:id,userfullname,email')
                        ->leftJoin('directory_entity_file as df', function($query) {
                            $query->on('df.file_id', '=', 'information_detail_document.document_name');
                            $query->on('df.move_to_trash', '=', DB::raw("0"));
                        })
                        ->select("information_detail_document.id", "information_detail_document.is_drive", "information_detail_document.document_path", "information_detail_document.is_client", DB::raw("IF(information_detail_document.is_drive=0,information_detail_document.document_name,df.file_name) as document_name"), "df.file_id", "df.csv_excel_file_id", "df.mime_type", "df.size")
                        ->where('information_detail_id', $infoDetail->id);
                if ($informationDocument->count() > 0) {
                    $informationDocument = $informationDocument->get();
                    $infoDetail['document'] = $informationDocument;
                }
            }
        }

        if (!empty($informationDetailAgain)) {
            $informationGroup['detail'] = $informationDetailAgain;
        }
        $addInfoDetail = \App\Models\Backend\InformationAdditionalInfo::with('createdBy:id,userfullname as created_by')
                ->select("information_additional_info.id", "information_additional_info.comment")
                ->where('information_id', '=', $id)
                ->get();
        $informationAddInfo = array();
        if (!empty($addInfoDetail)) {
            foreach ($addInfoDetail as $addInfo) {
                $addInfo['document'] = array();
                $addInfoDocument = \App\Models\Backend\InformationAdditionalDocument::with('createdBy:id,userfullname,email')
                        ->leftJoin('directory_entity_file as df', function($query) {
                            $query->on('df.file_id', '=', 'information_additional_document.document_name');
                            $query->on('df.move_to_trash', '=', DB::raw("0"));
                        })
                        ->select("information_additional_document.id", "information_additional_document.document_path", "information_additional_document.is_drive", "information_additional_document.is_client", DB::raw("IF(information_additional_document.is_drive=0,information_additional_document.document_name,df.file_name) as document_name"), "df.file_id", "df.csv_excel_file_id", "df.mime_type", "df.size")
                        ->where('information_add_id', $addInfo->id);
                if ($addInfoDocument->count() > 0) {
                    $addInfoDocument = $addInfoDocument->get();
                    $addInfo['document'] = $addInfoDocument;
                }
                $informationAddInfo[] = $addInfo;
            }
        }
        if (!empty($informationAddInfo)) {
            $informationGroup['adddetail'] = $informationAddInfo;
        }

        if (!isset($information))
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Information List does not exist', ['error' => 'The Information does not exist']);


        //send information
        return createResponse(config('httpResponse.SUCCESS'), 'Information List data', ['data' => $informationGroup]);
        /* } catch (\Exception $e) {
          app('log')->error("Information List details api failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Information List.', ['error' => 'Could not get Information List.']);
          } */
    }

    /**
     * Created by: Vivek Parmar
     * Created on: March 28,  2020
     * Fetch information assignee user
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function infoAssignee(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'information_id' => 'required'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $informationId = $request->get('information_id');
            $updateData = array();
            if ($request->get('additional_tm') != '' || !empty($request->get('additional_tl'))) {
                $info = \App\Models\Backend\Information::find($informationId);
                $additionalTL = $request->get('additional_tl');
                $additionalATL = $request->get('additional_atl');
                $additionalTM = $request->get('additional_tm');
                if (isset($additionalTL)) {
                    $updateData['additional_tl'] = $additionalTL;
                }

                if (isset($additionalATL)) {
                    $updateData['additional_atl'] = $additionalATL;
                }

                if (isset($additionalTM)) {
                    $updateData['additional_tm'] = $additionalTM;
                }
                $info->update($updateData);
            }

            return createResponse(config('httpResponse.SUCCESS'), "Succesfully additional users assign", ['message' => 'Succesfully additional users assign']);
        } catch (\Exception $e) {
            app('log')->error("Dropdown listing fail : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch dropdown listing.', ['error' => 'Could not fetch dropdown listing.']);
        }
    }

    /**
     * Created by: Vivek Parmar
     * Created on: April 02,  2020
     * Fetch information assignee user
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function snoozeInfo(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'snooze' => 'required|numeric',
                'reminder' => 'required|numeric'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $information = \App\Models\Backend\Information::find($id);

            $updateData['reminder'] = !empty($request->input('reminder')) ? $request->input('reminder') : '';
            $updateData['snooze'] = !empty($request->input('snooze')) ? $request->input('snooze') : '';

            $information->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Information has been updated successfully', ['message' => 'Information has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Snooze info fail : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch snooze info.', ['error' => 'Could not fetch snooze info.']);
        }
    }

    /**
     * Created by: Vivek Parmar
     * Created on: April 03,  2020
     * Fetch snooz dropdown user
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function snnozDropdown(Request $request, $id) {
        try {

            $snoozReminder = array();
            // define soring parameters

            if (!empty($id)) {
                $info = \App\Models\Backend\Information::find($id);
                if (!empty($info)) {
                    $snoozReminder['snooze'] = $info->snooze;
                    $snoozReminder['reminder'] = $info->reminder;
                }
            }

            return createResponse(config('httpResponse.SUCCESS'), "Information reminder value list.", ['data' => array('reminder' => $snoozReminder)]);
        } catch (\Exception $e) {
            app('log')->error("Dropdown listing fail : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch dropdown listing.', ['error' => 'Could not fetch dropdown listing.']);
        }
    }

    /**
     * Partial New Create details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Information Id
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy($id) {
        // try {
        $informationDetail = \App\Models\Backend\Information::find($id);
        if (!$informationDetail)
            return createResponse(config('httpResponse.NOT_FOUND'), 'The information does not exist', ['error' => 'The Information_id does not exist']);

        \App\Models\Backend\InformationDetail::where("information_id", $id)->delete();
        //\App\Models\Backend\QueryDetailDocument::where("query_id",$id)->delete();
        \App\Models\Backend\InformationAdditionalInfo::where("information_id", $id)->delete();
        // \App\Models\Backend\QueryAdditionalDocument::where("query_id",$id)->delete();
        $informationDetail->delete();

        return createResponse(config('httpResponse.SUCCESS'), 'Information_id has been deleted successfully', ['message' => 'Information_id has been deleted successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Query download failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete Query.', ['error' => 'Could not delete Query.']);
          } */
    }

}

?>