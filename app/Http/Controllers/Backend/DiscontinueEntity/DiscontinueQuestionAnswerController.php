<?php

namespace App\Http\Controllers\Backend\DiscontinueEntity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DiscontinueQuestionAnswerController extends Controller {
    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 13, 2018
     * Purpose: Get discontinue detail
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

            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'discontinue_entity.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $discontinueEntity = \App\Models\Backend\DiscontinueEntity::select('e.code', 'e.trading_name', 'e.contract_signed_date', 'discontinue_entity.*', app('db')->raw('GROUP_CONCAT(DISTINCT CONCAT(stage_id, "=", is_completed)) as stage'), app('db')->raw('(SELECT
     SUM(i.paid_amount)
   FROM invoice AS i
   WHERE i.entity_id = discontinue_entity.entity_id
       AND i.status_id != 5) AS totalRevenue'), app('db')->raw('(SELECT
     SUM(i.paid_amount)
   FROM invoice AS i
   WHERE i.created_on BETWEEN CASE WHEN MONTH(discontinue_entity.discontinue_on) >= 7 THEN DATE_FORMAT(CONCAT(YEAR(discontinue_entity.discontinue_on) - 1,"-07-01"), "%Y-%m-%d")ELSE DATE_FORMAT(CONCAT(YEAR(discontinue_entity.discontinue_on) - 2, "-07-01"), "%Y-%m-%d")END
       AND CASE WHEN MONTH(discontinue_entity.discontinue_on) >= 7 THEN DATE_FORMAT(CONCAT(YEAR(discontinue_entity.discontinue_on),"-06-30"), "%Y-%m-%d")ELSE DATE_FORMAT(CONCAT(YEAR(discontinue_entity.discontinue_on) - 1, "-06-30"), "%Y-%m-%d")END
       AND i.entity_id = discontinue_entity.entity_id
       AND i.status_id != 5) AS lastfyRevanue'), app('db')->raw('FORMAT(threeinvoice(discontinue_entity.entity_id, discontinue_entity.discontinue_on), 2) AS lastthreeinvoiceRevanue'), app('db')->raw('GROUP_CONCAT(DISTINCT u.userfullname) AS technical_account_manager'))->leftjoin('entity AS e', 'e.id', '=', 'discontinue_entity.entity_id')->leftjoin('entity_allocation AS ea', 'ea.entity_id', '=', 'discontinue_entity.entity_id')->leftjoin('USER AS u', function ($query) {
                        $query->whereRaw('u.id = json_extract(ea.allocation_json, "$.9")');
                    })->leftjoin("discontinue_entity_stage AS des", "des.discontinue_id", "=", "discontinue_entity.id")->with('discontinueBy:id,userfullname')->with('status:id,status');

            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array('id' => 'discontinue_entity', 'entity_id' => 'discontinue_entity', 'stage_id' => 'des', 'is_completed');
                $discontinueEntity = search($discontinueEntity, $search, $alias);
            }

            $discontinueEntity = $discontinueEntity->groupBy('ea.entity_id');
//        echo $discontinueEntity->toSql();
//        die;
            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $discontinueEntity = $discontinueEntity->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = count($discontinueEntity->get());

                $discontinueEntity = $discontinueEntity->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $discontinueEntity = $discontinueEntity->get();

                $filteredRecords = count($discontinueEntity);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            $discontinueEntityStage = \App\Models\Backend\DiscontinueEntity::arrageData($discontinueEntity);
            if ($request->has('excel') && $request->get('excel') == 1) {
                $discontinueStage = \App\Models\Backend\DiscontinueStage::pluck('stage', 'id')->toArray();
                $data = $discontinueEntity->toArray();
//            showArray($data);
//            die;
                $column = array();
                $column[0][] = 'Sr.No';
                $column[0][] = 'Client code';
                $column[0][] = 'Trading name';
                $column[0][] = 'Contract date';
                $column[0][] = 'Technical account manager';
                $column[0][] = 'Total revenue';
                $column[0][] = 'Last FY revenue';
                $column[0][] = 'Last 3 invoice revenue';
                $column[0][] = 'Discontinue reason';
                $column[0][] = 'Discontinue QC comment';
                $column[0][] = 'Discontinue sale comment';
                $column[0][] = 'Pending stage';
                $column[0][] = 'Completed stage';
                $column[0][] = 'Status';
                $column[0][] = 'Discontinue by';
                $column[0][] = 'Discontinue date';

                if (!empty($data)) {
                    $columnData = array();
                    $type = config('constant.questionType');
                    $i = 1;
                    foreach ($data as $value) {
                        $completedStage = array();
                        $pendingStage = array();
                        if (isset($value['stage']) && is_array($value['stage'])) {
                            foreach ($value['stage'] as $keyStage => $valueStage) {
                                if ($valueStage == 0)
                                    $pendingStage[] = $discontinueStage[$keyStage];
                                else
                                    $completedStage[] = $discontinueStage[$keyStage];
                            }
                        }

                        $columnData[] = $i;
                        $columnData[] = $value['code'];
                        $columnData[] = $value['trading_name'];
                        $columnData[] = dateFormat($value['contract_signed_date']);
                        $columnData[] = $value['technical_account_manager'];
                        $columnData[] = $value['totalRevenue'];
                        $columnData[] = $value['lastfyRevanue'];
                        $columnData[] = $value['lastthreeinvoiceRevanue'];
                        $columnData[] = isset($value['discontinue_comment']) ? $value['discontinue_comment'] : '-';
                        $columnData[] = isset($value['discontinue_comment_by_qc']) ? $value['discontinue_comment_by_qc'] : '-';
                        $columnData[] = isset($value['discontinue_comment_sale']) ? $value['discontinue_comment_sale'] : '-';
                        $columnData[] = !empty($pendingStage) ? implode(", ", $pendingStage) : '-';
                        $columnData[] = !empty($completedStage) ? implode(", ", $completedStage) : '-';
                        $columnData[] = $value['status']['status'];
                        $columnData[] = isset($value['discontinue_by']['userfullname']) ? $value['discontinue_by']['userfullname'] : '-';
                        $columnData[] = dateFormat($value['discontinue_on']);
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Discontinue client', 'xlsx', 'A1:P1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Discontinue entity list.", ['data' => $discontinueEntity], $pager);
        } catch (\Exception $e) {
            app('log')->error("Discontinue reason listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing discontinue", ['error' => 'Server error.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 13, 2018
     * Purpose: Get particular discontinue details
     * @param  int  $id   //timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function show(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'is_draft' => 'in:0,1',
            'stage_id' => 'numeric'], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $user = getLoginUserHierarchy();
        $pass = 0;
        if ($user->designation_id == config('constant.SUPERADMIN') || checkButtonRights(129, 7) == TRUE)
            $pass = 1;

        $whoFillup[] = checkButtonRights(129, 7) == TRUE || $pass == 1 ? 7 : 0;
        $whoFillup[] = checkButtonRights(129, 6) == TRUE || $pass == 1 ? 6 : 0;
        $whoFillup[] = checkButtonRights(129, 5) == TRUE || $pass == 1 ? 5 : 0;
        $whoFillup[] = checkButtonRights(129, 4) == TRUE || $pass == 1 ? 4 : 0;
        $whoFillup[] = checkButtonRights(129, 3) == TRUE || $pass == 1 ? 3 : 0;
        $whoFillup[] = checkButtonRights(129, 2) == TRUE || $pass == 1 ? 2 : 0;
        $whoFillup[] = checkButtonRights(129, 1) == TRUE || $pass == 1 ? 1 : 0;
        $whoFillup = array_filter($whoFillup);

        $stageId = $request->get('stage_id');
        if ($stageId != 7) {
            $whoFillup = array();
            $whoFillup[] = $stageId;
        }

        if (empty($whoFillup))
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.UNAUTHORIZEDBUTTONACCESS'), ['error' => 'You have no enough right to access']);

        if ($pass == 1 && $stageId == 7)
            $whoFillup = \App\Models\Backend\DiscontinueEntityStage::where('discontinue_entity_id', $id)->pluck('stage_id', 'stage_id')->toArray();

        $discontinueEntityDetail = \App\Models\Backend\DiscontinueQuestion::select('discontinue_question.id', 'discontinue_question.who_fillup', 'discontinue_question.parent_id', 'discontinue_question.name', 'dqa.is_checked', 'discontinue_question.type', 'dqa.notes', app('db')->raw('IF(dqa.is_draft = 0, 0, 1) AS draft'), 'dqa.created_by', 'dqa.created_on')->leftjoin('discontinue_question_answer AS dqa', function($query)use($id) {
                    $query->whereRaw('dqa.discontinue_question_id = discontinue_question.id')->where('dqa.discontinue_entity_id', $id);
                })->with('createdBy:id,userfullname')->where('discontinue_question.is_active', 1)->whereIn('discontinue_question.who_fillup', $whoFillup);

//            if ($request->get('is_draft') == 1)
//                $discontinueEntityDetail->whereRaw('dqa.is_draft IS NULL');
//            else
//                $discontinueEntityDetail->whereRaw('dqa.is_draft = 0');

        $who_fillup = array(1 => 'Bookkeeping', 2 => 'Payroll', 3 => 'Taxation', 4 => 'Billing', 5 => 'Quality control', 6 => 'Sales', 7 => 'Division head');
        $discontinueEntityDetail = $discontinueEntityDetail->get()->toArray();
        $newObject = array();
        foreach ($discontinueEntityDetail as $key => $value) {
            if ($value['parent_id'] != 0) {
                $value['who_fillup_name'] = $who_fillup[$value['who_fillup']];
                $newObject[$value['parent_id']][] = $value;
            }
        }

        $finalObject = array();
        $i = 0;
        foreach ($discontinueEntityDetail as $finalKey => $finalValue) {
            if ($finalValue['parent_id'] == 0) {
                $finalValue['who_fillup_name'] = $who_fillup[$finalValue['who_fillup']];
                $finalObject[$who_fillup[$finalValue['who_fillup']]][$i] = $finalValue;
                if (isset($newObject[$finalValue['id']])) {
                    $finalObject[$who_fillup[$finalValue['who_fillup']]][$i]['child'] = $newObject[$finalValue['id']];
                } else {
                    $finalObject[$who_fillup[$finalValue['who_fillup']]][$i]['child'] = array();
                }

                $i++;
            }
        }

        if (empty($finalObject))
            return createResponse(config('httpResponse.NOT_FOUND'), 'Discontinue reason does not exist', ['error' => 'Discontinue reason does not exist']);

        return createResponse(config('httpResponse.SUCCESS'), 'Discontinue question detail successfully loaded.', ['data' => $finalObject]);
//        } catch (\Exception $e) {
//            app('log')->error("Discontinue reason details api failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get discontinue detail.', ['error' => 'Could not get discontinue detail.']);
//        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Dec 13, 2018 
     * Purpose: update discontinue details
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function update(Request $request, $id) {
        //try {
        $stage = \App\Models\Backend\DiscontinueStage::pluck('stage', 'id')->toArray();
        $stageIds = array_keys($stage);
        $validator = app('validator')->make($request->all(), [
            //'question_detail' => 'required|json',
            'stage' => 'required|in:' . implode(',', $stageIds),
            'is_draft' => 'required|in:0,1'], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
        
        $stageId = $request->get('stage');
        $isDraft = $request->get('is_draft');
        $entityID = $request->get('entity_id');
        
        //if (!in_array($stageId, [6])) {
        $discontinueQuestion = $request->get('question_json');
        $arrangeData = $discontinueQuestionId = array();
        if (!empty($discontinueQuestion)) {
            foreach ($discontinueQuestion as $key => $value) {
                $data = array();
                $data['discontinue_entity_id'] = $id;
                $data['discontinue_question_id'] = $value['discontinue_question_id'];
                $data['notes'] = isset($value['notes']) && $value['notes'] != '' ? $value['notes'] : '';
                $data['is_checked'] = $value['is_checked'] == 1 ? $value['is_checked'] : 0;
                $data['is_draft'] = $isDraft;
                $data['created_by'] = app('auth')->guard()->id();
                $data['created_on'] = date('Y-m-d H:i:s');
                $arrangeData[] = $data;
                $discontinueQuestionId[] = $value['discontinue_question_id'];
            }

            \App\Models\Backend\DiscontinueQuestionAnswer::where('discontinue_entity_id', $id)->whereIn('discontinue_question_id', $discontinueQuestionId)->delete();
            \App\Models\Backend\DiscontinueQuestionAnswer::insert($arrangeData);
        }
        $flagComplete = 0;
        if($stageId == 4){
            $checkEntityStage = \App\Models\Backend\DiscontinueEntityStage::where('discontinue_entity_id', $id);
            if($checkEntityStage->count() == 1){
               $flagComplete =1;
            }
        }
        // $checkdisEntityStage = \App\Models\Backend\DiscontinueEntityStage::where('discontinue_entity_id', $id)->where();
        // If stage is division head and discontinue form not in draft
        if (($stageId == 7 && $isDraft == 0)|| $flagComplete==1) {
            \App\Models\Backend\DiscontinueEntityStage::where('is_completed', 0)->where('discontinue_entity_id', $id)->where('stage_id', $stageId)->update(['is_completed' => 1]);

            $discontinueEntity = \App\Models\Backend\DiscontinueEntity::find($id);
            $updateData['status'] = 4;
            $discontinueEntity->update($updateData);

            $entityData = \App\Models\Backend\Entity::find($discontinueEntity->entity_id);
            $updateEntityData['entity_discontinue'] = date('Y-m-d');
            $updateEntityData['discontinue_stage'] = 2;
            $entityData->update($updateEntityData);
            // update fixed fee prposal 
            \App\Models\Backend\FFProposal::where("entity_id",$discontinueEntity->entity_id)->whereIn("status_id",[1,2,3,4,5])
                    ->update(["status_id" => "7"]);
            

            // update 0 on parent entity if client discountinue
            /*
             * Pankaj
             * Date - 08-02-2019
             */

            \App\Models\Backend\Billing::where("entity_id", $discontinueEntity->entity_id)->update(["parent_id" => 0]);
            // End
            \App\Models\Backend\SubClient::where("entity_id",$discontinueEntity->entity_id)->update(["is_active"=>"0",'modified_by' => app('auth')->guard()->id(), 'modified_on' => date('Y-m-d H:i:s')]);
            $discontinueEntityAudit[] = array('discontinue_entity_id' => $id, 'values' => $stageId, 'log_type' => '0', 'modified_by' => app('auth')->guard()->id(), 'modified_on' => date('Y-m-d H:i:s'));
            $discontinueEntityAudit[] = array('discontinue_entity_id' => $id, 'values' => 4, 'log_type' => 1, 'modified_by' => app('auth')->guard()->id(), 'modified_on' => date('Y-m-d H:i:s'));
            \App\Models\Backend\DiscontinueEntityAudit::insert($discontinueEntityAudit);

            if($flagComplete == 0){
            $arrgumentData['entity_name'] = $entityData->trading_name;
            $arrgumentData['entity_id'] = $entityData->id;
            $arrgumentData['problem_our_side'] = $discontinueEntity->problem_our_side;
            $arrgumentData['discontinue_comment'] = $discontinueEntity->discontinue_comment;
            $arrgumentData['discontinue_entity_id'] = $discontinueEntity->id;
            $this->sendFinalDiscontinueMailNotification($request, 'DISCONTINUECLIENTCOMPLETED', $arrgumentData);
            }
            //goto end;
        }
        // } 

        if (in_array($stageId, [5, 6])) {
            $updateEntityData = array();
             $updateEntityData['status'] = 2;
            if ($stageId == 5)
                $updateEntityData['discontinue_comment_by_qc'] = $request->get('discontinue_comment_by_qc');

            if ($stageId == 6)
                $updateEntityData['discontinue_comment_by_sales'] = $request->get('discontinue_comment_by_sales');


            if (!empty($updateEntityData))
                \App\Models\Backend\DiscontinueEntity::where('id', $id)->update($updateEntityData);
        }

        if ($isDraft == 0 && $stageId != 7) {
            \App\Models\Backend\DiscontinueEntityStage::where('is_completed', 0)->where('discontinue_entity_id', $id)->where('stage_id', $stageId)->update(['is_completed' => 1]);

            $discontinueEntityAudit = array();
            if (\App\Models\Backend\DiscontinueEntityAudit::where('discontinue_entity_id', $id)->where('values', 2)->where('log_type', 1)->count() == 0) {
                $discontinueEntityAudit[] = array('discontinue_entity_id' => $id, 'values' => 2, 'log_type' => 1, 'modified_by' => app('auth')->guard()->id(), 'modified_on' => date('Y-m-d H:i:s'));
            }

            $discontinueEntityAudit[] = array('discontinue_entity_id' => $id, 'values' => $stageId, 'log_type' => '0', 'modified_by' => app('auth')->guard()->id(), 'modified_on' => date('Y-m-d H:i:s'));
            \App\Models\Backend\DiscontinueEntityAudit::insert($discontinueEntityAudit);
            \App\Models\Backend\DiscontinueEntity::where('id', $id)->update(['status' => 2]);
            $checkAllStage = \App\Models\Backend\DiscontinueEntityStage::where('discontinue_entity_id', $id)->where("stage_id","!=","7")->where("is_completed","0");
            if($checkAllStage->count() == 0){
                 \App\Models\Backend\DiscontinueEntity::where('id', $id)->update(['status' => 3]);
            }
        }
        $completed = 0;
        //app('db')->table('discontinue_entity')->where('id', $id)->update(['status' => 2, 'modified_by' => app('auth')->guard()->id(), 'modified_on' => date('Y-m-d H:i:s')]);
        if ($isDraft != 0 && $stageId != 7) {
            //if all stage done only  division head remaning then entity status also change to division head pending stage
            
            $allStage = \App\Models\Backend\DiscontinueEntityStage::where('discontinue_entity_id', $id)->get();
            foreach ($allStage as $stg) {
                if ($stg->stage_id != 7) {
                    $completed = $stg->is_completed;
                }
            }
        }
            //\App\Models\Backend\DiscontinueEntity::where('id', $id)->update(['status' => 3]);
            if ($completed == 1) {
                \App\Models\Backend\DiscontinueEntity::where('id', $id)->update(['status' => 3]);
                $discontinueEntityAudit[] = array('discontinue_entity_id' => $id, 'values' => 3, 'log_type' => 1, 'modified_by' => app('auth')->guard()->id(), 'modified_on' => date('Y-m-d H:i:s'));
                \App\Models\Backend\DiscontinueEntityAudit::insert($discontinueEntityAudit);
            }
        
        
        //end;
        return createResponse(config('httpResponse.SUCCESS'), 'Discontinue details has been updated successfully', ['message' => 'Discontinue details has been updated successfully']);
//        } catch (\Exception $e) {
//            app('log')->error("Discontinue detail updation failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update discontinue details.', ['error' => 'Could not update discontinue details.']);
//        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Dec 13, 2018 
     * Purpose: discontinue entity history listing
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // timesheet id
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

            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'discontinue_entity_audit.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $discontinueEntityAudit = \App\Models\Backend\DiscontinueEntityAudit::with('modifiedBy:id,userfullname')->where('discontinue_entity_id', $id);

            if ($request->has('search')) {
                $search = $request->get('search');
                $discontinueEntityAudit = search($discontinueEntityAudit, $search);
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $discontinueEntityAudit = $discontinueEntityAudit->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $discontinueEntityAudit->count();

                $discontinueEntityAudit = $discontinueEntityAudit->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $discontinueEntityAudit = $discontinueEntityAudit->get();

                $filteredRecords = count($discontinueEntityAudit);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            $discontinueEntityAudit = \App\Models\Backend\DiscontinueEntityAudit::arrangeData($discontinueEntityAudit);
            return createResponse(config('httpResponse.SUCCESS'), "Discontinue entity history loaded successfully", ['data' => $discontinueEntityAudit], $pager);
        } catch (\Exception $e) {
            app('log')->error("Discontinue history loaded failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while loaded discontinue history", ['error' => 'Server error.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Dec 14, 2018 
     * Purpose: Send notification email to staff
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function sendFinalDiscontinueMailNotification($request, $code, $requestData = null) {
        $template = \App\Models\Backend\EmailTemplate::getTemplate($code);
        $request->merge(['search' => '{"compare":{"equal":{"problem_our_side":1}}}']);
        $ticketPer = new \App\Http\Controllers\Backend\Ticket\TicketController();
        $entityId = $requestData['entity_id'];
        $ticketReport = $ticketPer->ticketCount($request, $entityId);
        $ticketCount = 0;
        $ticketAttechment = '';
        if (isset($ticketReport->original['payload']['data'])) {
            $ticketCount = $ticketReport->original['payload']['data']->count();
            if ($ticketCount > 0) {
                $data = $columnData = array();
                $code = '';
                $i = 1;
                $data[0][] = 'Sr.No';
                $data[0][] = 'Code';
                $data[0][] = 'Type';
                $data[0][] = 'Status';
                $data[0][] = 'Subject';
                $data[0][] = 'Client name';
                $data[0][] = 'Technical account manager';
                $data[0][] = 'Description';
                $data[0][] = 'Explain why this has occurred';
                $data[0][] = 'Resolution / Comments';
                $data[0][] = 'Created by';
                $data[0][] = 'Created on';

                foreach ($ticketReport->original['payload']['data'] as $value) {
                    $columnData[] = $i;
                    $columnData[] = $value['code'];
                    $columnData[] = $value['type'];
                    $columnData[] = $value['status'];
                    $columnData[] = $value['subject'];
                    $columnData[] = $value['trading_name'];
                    $columnData[] = $value['technicalaccountmanager'];
                    $columnData[] = $value['issue_detail'];
                    $columnData[] = $value['reason_why_this_has_occurred'];
                    $columnData[] = isset($value['resolution']) ? $value['resolution'] : '-';
                    $columnData[] = isset($value['created_by_name']) ? $value['created_by_name'] : '-';
                    $columnData[] = dateFormat($value['created_by']);
                    $data[] = $columnData;
                    $columnData = array();
                    $i++;
                    $code = $value['code'];
                }
                app('excel')->create('Ticket report ' . $code, function($excel) use ($data) {
                    $excel->sheet('Discontinue question', function($sheet) use ($data) {
                        $sheet->cell('A1:L1', function($cell) {
                            $cell->setFontColor('#ffffff');
                            $cell->setBackground('#0c436c');
                        });

                        $sheet->getAllowedStyles();
                        $sheet->fromArray($data, null, 'A1', false, false);
                    });
                })->store('xlsx', storageEfs('/templocation/discontinue'));
                $ticketAttechment = '/templocation/discontinue/Ticket report ' . $code . '.xlsx';
            }
        }

        $request->merge(['exceldownload' => '0']);
        $discontinueQuestion = $this->discontinueQuestionDetail($request, $requestData['discontinue_entity_id']);
        $discontinueQuestionAttechment = $discontinueQuestion->original['payload']['data'];
        $attachment = array();
        if ($ticketAttechment != '')
            $attachment[] = $ticketAttechment;

        if ($discontinueQuestionAttechment != '')
            $attachment[] = $discontinueQuestionAttechment;

        $entityAllocation = \App\Models\Backend\EntityAllocation::select(app('db')->raw("GROUP_CONCAT(u.email) as useremail"))->where('entity_id', $entityId)->leftjoin('user AS u', function($query) {
                    $query->whereRaw("u.id = json_extract(allocation_json, '$.9')");
                })->get();

        $emailData['to'] = $template->cc;
        $emailData['cc'] = isset($entityAllocation[0]->useremail) && $entityAllocation[0]->useremail != '' ? $entityAllocation[0]->useremail : '';
        $emailData['subject'] = $template->subject;
        $find = array('ENTITYNAME', 'OURPROBLEM', 'REASON', 'TICKET', 'UPDATEDBY');
        $problem_our_side = $requestData['problem_our_side'] == 1 ? "Yes" : "No";
        $replace = array($requestData['entity_name'], $problem_our_side, $requestData['discontinue_comment'], $ticketCount, app('auth')->guard()->user()->userfullname);
        $emailData['content'] = str_replace($find, $replace, $template->content);
        $emailData['attachment'] = $attachment;
        storeMail($request, $emailData);
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Dec 13, 2018 
     * Purpose: get discontinue detail 
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function discontinueQuestionDetail(Request $request, $id) {
        try {
            $entityDetail = \App\Models\Backend\Entity::find($id);
            $discontinueQuestionAnswer = \App\Models\Backend\DiscontinueQuestionAnswer::select('discontinue_question_answer.*', 'dq.name', 'dq.parent_id', 'dq.who_fillup')->where('discontinue_entity_id', $id)
                            ->leftjoin('discontinue_question as dq', 'dq.id', '=', 'discontinue_question_answer.discontinue_question_id')->with('createdBy:id,userfullname')->get();

            $discontinueQuestionAnswer = \App\Models\Backend\DiscontinueQuestionAnswer::arrangeData($discontinueQuestionAnswer);
            $filename = 'Discontinue report - ' . $entityDetail->code;

            if ($request->has('exceldownload')) {
                $excelSheet = app('excel')->create($filename, function($excel) use ($discontinueQuestionAnswer) {
                    $excel->sheet('Discontinue question', function($sheet) use ($discontinueQuestionAnswer) {
                        $sheet->row(1, array('Step', 'Is it done?', 'Notes', 'Created by', 'Created on'));
                        $sheet->cell('A1:E1', function($cell) {
                            $cell->setFontColor('#ffffff');
                            $cell->setBackground('#0c436c');
                        });

                        $i = 2;
                        foreach ($discontinueQuestionAnswer as $key => $value) {
                            $sheet->cell('A' . $i . ':E' . $i, function($color) {
                                $color->setFontColor('#ffffff');
                                $color->setBackground('#006699');
                            });
                            $sheet->row($i, array($key, '', ''));
                            $j = $i + 1;
                            foreach ($value as $keyCell => $valueCell) {
                                $checked = $valueCell['is_checked'] == 1 ? 'Yes' : 'No';
                                $sheet->row($j, array($valueCell['name'], $checked, $valueCell['notes'], isset($valueCell->createdBy->userfullname) ? $valueCell->createdBy->userfullname : '-', dateFormat($valueCell['created_on'])));
                                $j++;
                            }
                            $i = $j;
                            $i++;
                        }
                        $sheet->getAllowedStyles();
                    });
                });

                if ($request->has('exceldownload') && $request->get('exceldownload') == 1)
                    $excelSheet->export('xlsx', ['Access-Control-Allow-Origin' => '*']);
                else if ($request->has('exceldownload') && $request->get('exceldownload') == 0)
                    $excelSheet->store('xlsx', storageEfs('/templocation/discontinue'));

                $discontinueQuestion = '/templocation/discontinue/' . $filename . '.xlsx';
                return createResponse(config('httpResponse.SUCCESS'), 'Discontinue question detail successfully loaded.', ['data' => $discontinueQuestion]);
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Discontinue question detail successfully loaded.', ['data' => $discontinueQuestionAnswer]);
        } catch (\Exception $e) {
            app('log')->error("Discontinue details api failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get discontinue detail.', ['error' => 'Could not get discontinue detail.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 13, 2018
     * Purpose: Get particular discontinue details
     * @param  int  $id   //timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function showQuestionDetail($id) {
        //try {
        $discontinueEntityDetail = \App\Models\Backend\DiscontinueQuestion::select('discontinue_question.id', 'discontinue_question.who_fillup', 'discontinue_question.parent_id', 'discontinue_question.name', 'dqa.is_checked', 'dqa.notes', app('db')->raw('IF(dqa.is_draft = 0, 0, 1) AS draft'), 'dqa.created_by', 'dqa.created_on')->leftjoin('discontinue_question_answer AS dqa', function($query)use($id) {
                    $query->whereRaw('dqa.discontinue_question_id = discontinue_question.id')->where('dqa.discontinue_entity_id', $id);
                })->with('createdBy:id,userfullname')->where('discontinue_question.is_active', 1);

        $discontinueEntityDetail = $discontinueEntityDetail->get()->toArray();
        $newObject = array();
        foreach ($discontinueEntityDetail as $key => $value) {
            if ($value['parent_id'] != 0)
                $newObject[$value['parent_id']][] = $value;
        }

        $finalObject = array();
        $i = 0;
        $who_fillup = array(1 => 'Bookkeeping', 2 => 'Payroll', 3 => 'Taxation', 4 => 'Billing', 5 => 'Quality control', 6 => 'Sales', 7 => 'Division head');

        foreach ($discontinueEntityDetail as $finalKey => $finalValue) {
            if ($finalValue['parent_id'] == 0) {
                $finalObject[$who_fillup[$finalValue['who_fillup']]][] = $finalValue;
                if (isset($newObject[$finalValue['id']])) {
                    $finalObject[$who_fillup[$finalValue['who_fillup']]][count($finalObject[$who_fillup[$finalValue['who_fillup']]]) - 1]['child'] = $newObject[$finalValue['id']];
                }
            }
        }

        $arrangGroupdataFinal = $data = array();
        foreach ($finalObject as $finalKey => $finalValue) {
            $data['name'] = $finalKey;
            $data['data'] = $finalValue;
            $arrangGroupdataFinal[] = $data;
        }

        if (empty($arrangGroupdataFinal))
            return createResponse(config('httpResponse.NOT_FOUND'), 'Discontinue reason does not exist', ['error' => 'Discontinue reason does not exist']);

        return createResponse(config('httpResponse.SUCCESS'), 'Discontinue question detail successfully loaded.', ['data' => $arrangGroupdataFinal]);
//        } catch (\Exception $e) {
//            app('log')->error("Discontinue reason details api failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get discontinue detail.', ['error' => 'Could not get discontinue detail.']);
//        }
    }

}
