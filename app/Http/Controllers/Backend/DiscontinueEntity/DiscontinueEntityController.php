<?php

namespace App\Http\Controllers\Backend\DiscontinueEntity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DiscontinueEntityController extends Controller {
    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 05, 2018
     * Purpose: Get discontinue detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function index(Request $request) {
        //try {
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

        $discontinueEntity = \App\Models\Backend\DiscontinueEntity::getDiscontinueData();

        $userDetail = getLoginUserHierarchy();

        if ($userDetail->designation_id != 7) {
            $discontinueEntity = $discontinueEntity->leftjoin("entity_allocation_other AS eoa", "eoa.entity_id", "=", "discontinue_entity.entity_id");
            $discontinueEntity = $discontinueEntity->whereRaw("(Json_extract(ea.allocation_json, '$." . $userDetail->designation_id . "') = " . $userDetail->user_id . " OR FIND_IN_SET(" . $userDetail->user_id . ", eoa.other))");
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array('id' => 'discontinue_entity', 'entity_id' => 'discontinue_entity', 'stage_id' => 'des', 'is_completed',"parent_id" => "e");
            $discontinueEntity = search($discontinueEntity, $search, $alias);
        }

        //$discontinueEntity = $discontinueEntity->groupBy('ea.entity_id');
        $discontinueEntity = $discontinueEntity->groupBy('discontinue_entity.entity_id');
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
            $data = $discontinueEntity->toArray();

            $column = array();
            $column[0][] = 'Sr.No';
            $column[0][] = 'Parent Trading name';
            $column[0][] = 'Client code';
            $column[0][] = 'Trading name';
            $column[0][] = 'Contract date';
            $column[0][] = 'Technical account manager';
            $column[0][] = 'TL';
            $column[0][] = 'ATL';
            $column[0][] = 'Monthly FF(Inc GST)';
            $column[0][] = 'Total Rev.(Inc GST)';
            $column[0][] = 'Last FY Rev.(Inc GST)';
            $column[0][] = 'Last 3 Inv.(Inc GST)';
            $column[0][] = 'Operation Team Comment';
            $column[0][] = 'QC comment';
            $column[0][] = 'Sales comment';
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
                            if ($valueStage['status'] == 0)
                                $pendingStage[] = $valueStage['name'];
                            else
                                $completedStage[] = $valueStage['name'];
                        }
                    }

                    $columnData[] = $i;
                    $columnData[] = $value['parent_name'];
                    $columnData[] = $value['code'];
                    $columnData[] = $value['trading_name'];
                    $columnData[] = dateFormat($value['contract_signed_date']);
                    $columnData[] = $value['technical_account_manager'];
                    $columnData[] = $value['tl'];
                    $columnData[] = $value['atl'];
                    $columnData[] = $value['ff_amount'];
                    $columnData[] = $value['totalRevenue'];
                    $columnData[] = $value['lastfyRevanue'];
                    $columnData[] = $value['lastthreeinvoiceRevanue'];
                    $columnData[] = isset($value['discontinue_comment']) ? $value['discontinue_comment'] : '-';
                    $columnData[] = isset($value['discontinue_comment_by_qc']) ? $value['discontinue_comment_by_qc'] : '-';
                    $columnData[] = isset($value['discontinue_comment_by_sales']) ? $value['discontinue_comment_by_sales'] : '-';
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
            return exportExcelsheet($column, 'Discontinue client', 'xlsx', 'A1:T1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Discontinue entity list.", ['data' => $discontinueEntity], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Discontinue reason listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing discontinue", ['error' => 'Server error.']);
//        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 05, 2018
     * Purpose: Store discontinue details
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric',
                'problem_our_side' => 'required|in:0,1',
                'discontinue_comment' => 'required',
                'ticket' => 'numeric'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $entity_id = $request->get('entity_id');

            $agreedBillingService = \App\Models\Backend\BillingServices::select('billing_services.id', 'billing_services.service_id', 'bb.full_time_resource')
                            ->leftjoin('billing_basic as bb', 'bb.entity_id', '=', 'billing_services.entity_id')
                            ->where('billing_services.is_latest', 1)
                            ->where('billing_services.is_active', 1)
                            ->where('billing_services.entity_id', $entity_id)
                            ->whereIn('billing_services.service_id', [1, 2, 6 , 4])->get()->toArray();

            if (empty($agreedBillingService))
                return createResponse(config('httpResponse.SUCCESS'), config('message.VALIDATION'), ['message' => 'No service agreed']);
            
            $pendingStage = [4, 5, 7];
            if ($agreedBillingService[0]['full_time_resource'] == 0)
                $pendingStage[] = 6;

            $services = array();
            foreach ($agreedBillingService as $key => $value) {
                $pendingStage[] = $value['service_id'];
                if ($value['service_id'] == 6){
                    $pendingStage[] = 3;
                }
                else if ($value['service_id'] == 4){
                    $pendingStage = [4];
                }

                $services[] = $value['service_id'];
            }
            asort($pendingStage);
            $insertPendingStage = array();

            $problemByOurSide = $request->get('problem_our_side');
            $discontinueComment = $request->get('discontinue_comment');
            //$ticket = ($request->has('ticket') && $request->get('ticket') != '') ? $request->get('ticket') : '0';
            $ticket = \App\Models\Backend\Ticket::select('ticket.*', 'utam.userfullname as technicalaccountmanager', 'e.code', 'e.trading_name', 'ucreatedby.userfullname as created_by_name')
                    ->leftjoin('user as ucreatedby', 'ucreatedby.id', '=', 'ticket.created_by')
                    ->leftjoin('user as utam', 'utam.id', '=', 'ticket.technical_account_manager')
                    ->leftjoin('entity as e', 'e.id', '=', 'ticket.entity_id')
                    //->leftjoin('ticket_status as ts', 'ts.id', '=', 'ticket.status_id')
                    ->whereIn('ticket.type_id', [1, 2, 3])
                    ->where('entity_id', $entity_id)
                    ->where('problem_our_side','1')->count();
            $ffAmount = self::countFFamount($entity_id);
            $discontinueEntity = \App\Models\Backend\DiscontinueEntity::create(["entity_id" => $entity_id,
                        "problem_our_side" => $problemByOurSide,
                        "discontinue_comment" => $discontinueComment,
                        "ff_amount" => $ffAmount,
                        "status" => 1,
                        "discontinue_by" => app('auth')->guard()->id(),
                        "discontinue_on" => date('Y-m-d H:i:s')]);

            foreach ($pendingStage as $key => $value) {
                $data = array();
                $data['discontinue_entity_id'] = $discontinueEntity->id;
                $data['stage_id'] = $value;
                $data['created_by'] = app('auth')->guard()->id();
                $data['created_on'] = date('Y-m-d H:i:s');
                $insertPendingStage[] = $data;
            }

            \App\Models\Backend\DiscontinueEntityStage::insert($insertPendingStage);
            $entityData = \App\Models\Backend\Entity::find($entity_id);
            app('db')->table('entity')->where('id', $entity_id)->update(['discontinue_stage' => 1]);
            //app('db')->statement("UPDATE entity SET discontinue_stage = 1 WHERE id = " . $entity_id);

            $discontinueEntityAudit = array('discontinue_entity_id' => $discontinueEntity->id, 'values' => 1, 'log_type' => 1, 'modified_by' => app('auth')->guard()->id(), 'modified_on' => date('Y-m-d H:i:s'));
            \App\Models\Backend\DiscontinueEntityAudit::insert($discontinueEntityAudit);

            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('DISCONTINUECLIENT');
            if ($emailTemplate->is_active == 1) {
                $yesNo = config('constant.yesNo');
                $find = ['ENTITYNAME', 'OURPROBLEM', 'REASON', 'TICKET', 'DISCONTINUEBY'];
                $replace = [$entityData->trading_name, $yesNo[$problemByOurSide], $discontinueComment, $ticket, app('auth')->guard()->user()->userfullname];
                $entityAllocation = entityAllocationdata($entity_id, $services);
                $userId = array();
                $designation = [9, 10,60,61, 14, 15];
                foreach ($entityAllocation as $key => $value) {
                    $tempData = json_decode($value['allocation_json']);
                    foreach ($tempData as $keyUser => $valueUser) {
                        if (in_array($keyUser, $designation))
                            if (!in_array($valueUser, $userId))
                                $userId[] = $valueUser;
                    }
                }

                $to = '';
                if (!empty($userId)) {
                    $user = \App\Models\User::select(app('db')->raw('GROUP_CONCAT(email) AS email'))->whereIn('id', $userId)->where("is_active","1")->where("send_email","1")->get()->toArray();
                    $to = $user[0]['email'];
                } else {
                    $user = \App\Models\User::select(app('db')->raw('GROUP_CONCAT(email) AS email'))->whereIn('id', [39])->where("is_active","1")->where("send_email","1")->get()->toArray();
                    $to = $user[0]['email'];
                }

                $data['content'] = str_replace($find, $replace, $emailTemplate->content);
                $data['subject'] = $emailTemplate->subject;
                $data['cc'] = $emailTemplate->cc;
                $data['bcc'] = $emailTemplate->bcc;
                $data['to'] = $to;
                if ($to == '')
                    return createResponse(config('httpResponse.SUCCESS'), 'Client has been discontinue successfully with sent mail to staff', ['data' => $discontinueEntity]);
                storeMail($request, $data);
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Client has been discontinue successfully', ['data' => $discontinueEntity]);
        } catch (\Exception $e) {
            app('log')->error("Discontinue reason creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add discontinue', ['error' => 'Could not add discontinue']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 05, 2018
     * Purpose: Get particular discontinue details
     * @param  int  $id   //timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function show($id) {
        try {
            $discontinueEntityDetail = \App\Models\Backend\DiscontinueQuestion::with('parentId:id,name', 'createdBy:id,userfullname', 'modifiedBy:id,userfullname')->where('id', $id)->get();

            if (empty($discontinueEntityDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Discontinue reason does not exist', ['error' => 'Discontinue reason does not exist']);

            return createResponse(config('httpResponse.SUCCESS'), 'Discontinue reason detail successfully load.', ['data' => $discontinueEntityDetail]);
        } catch (\Exception $e) {
            app('log')->error("Discontinue reason details api failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get discontinue detail.', ['error' => 'Could not get discontinue detail.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 05, 2018
     * Purpose: Get particular discontinue details
     * @param  int  $id   //timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function showDiscontinueDetail($id) {
        try {
            $discontinueEntityDetail = \App\Models\Backend\DiscontinueEntity::with('entityId:id,trading_name', 'discontinueBy:id,userfullname', 'modifiedBy:id,userfullname')->where('id', $id)->get();

            if (empty($discontinueEntityDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Discontinue reason does not exist', ['error' => 'Discontinue reason does not exist']);

            return createResponse(config('httpResponse.SUCCESS'), 'Discontinue detail successfully load.', ['data' => $discontinueEntityDetail]);
        } catch (\Exception $e) {
            app('log')->error("Discontinue reason details api failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get discontinue detail.', ['error' => 'Could not get discontinue detail.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Dec 05, 2018 
     * Purpose: update discontinue details
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function update(Request $request, $id) {
        try {
            $discontinueEntity = \App\Models\Backend\DiscontinueQuestion::find($id);
            $validator = app('validator')->make($request->all(), [
                'parent_id' => 'numeric',
                'name' => 'required',
                'who_fillup' => 'required|numeric',
                'type' => 'required|in:0,1',
                'is_active' => 'required|in:0,1'], []);

            if (!$discontinueEntity)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Reason does not exist', ['error' => 'Reason does not exist']);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $updateData = array();
            // Filter the fields which need to be updated            
            $updateData = filterFields(['parent_id', 'name', 'who_fillup', 'type', 'is_active'], $request);

            $discontinueEntity['modified_by'] = app('auth')->guard()->id();
            $discontinueEntity['modified_on'] = date('Y-m-d H:i:s');
            $discontinueEntity->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Reason has been updated successfully', ['message' => 'Reason has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Timesheet updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update discontinue details.', ['error' => 'Could not update discontinue details.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Dec 09, 2018 
     * Purpose: fetch discontinue comment listing
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function comment(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $discontinueComment = \App\Models\Backend\DiscontinueComment::where('discontinue_entity_id', $id)->with('createdBy:id,userfullname');

            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array();
                $discontinueComment = search($discontinueComment, $search, $alias);
            }

            if ($request->has('records') && $request->get('records') == 'all') {
                $discontinueComment = $discontinueComment->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $discontinueComment->count();

                $discontinueEntity = $discontinueComment->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $discontinueComment = $discontinueComment->get();

                $filteredRecords = count($discontinueComment);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Discontinue comment list.", ['data' => $discontinueComment], $pager);
        } catch (\Exception $e) {
            app('log')->error("Discontinue comment failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add discontinue comment.', ['error' => 'Could not add discontinue comment.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 05, 2018
     * Purpose: Store discontinue details
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function commentStore(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                //'entity_id' => 'required|numeric',
                'discontinue_entity_id' => 'required|numeric',
                'comment' => 'required',
                'is_emailsent' => 'in:0,1',
                'to' => 'required_if:is_emailsent,1|email'], ['to.required_if' => 'The To field is required when notification send checked']);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $discontinueId = $request->get('discontinue_entity_id');
            $discontinueDetail = \App\Models\Backend\DiscontinueEntity::find($discontinueId);
            $is_emailsent = $request->has('is_emailsent') ? $request->get('is_emailsent') : 0;
            $comment = $request->get('comment');
            $cc = ($request->has('cc') && $request->get('cc') != '') ? implode(',', $request->get('cc')) : '';
            $discontinueEntity = \App\Models\Backend\DiscontinueComment::create(["discontinue_entity_id" => $discontinueId,
                        "comment" => $comment,
                        "is_emailsent" => $is_emailsent,
                        "to" => $request->get('to'),
                        "cc" => $cc,
                        "created_by" => app('auth')->guard()->id(),
                        "created_on" => date('Y-m-d H:i:s')]);

            if ($is_emailsent == 1) {
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('DISCONTINUECOMMENT');
                if ($emailTemplate->is_active == 1) {
                    $entity = \App\Models\Backend\Entity::find($discontinueDetail->entity_id);
                    $find = ['ENTITYNAME', 'COMMENT', 'USERNAME'];
                    $replace = [$entity->trading_name, $comment, app('auth')->guard()->user()->userfullname];
                    $to = $request->get('to');
                    $data['content'] = str_replace($find, $replace, $emailTemplate->content);
                    $data['subject'] = str_replace($find, $replace, $emailTemplate->subject);
                    $data['cc'] = $cc;
                    $data['to'] = $to;
                    storeMail($request, $data);
                }
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Discontinue client comment has been added successfully', ['data' => $discontinueEntity]);
        } catch (\Exception $e) {
            app('log')->error("Discontinue client comment addition failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add discontinue comment', ['error' => 'Could not add discontinue comment']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 17, 2018
     * Purpose: Restore discontinue client as normals
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function restore(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric'], []);

            $entity_id = $request->get('entity_id');
            $updateEntityData['entity_discontinue'] = '0000-00-00';
            $updateEntityData['discontinue_stage'] = 0;
            \App\Models\Backend\Entity::where("id", $entity_id)->update($updateEntityData);

            \App\Models\Backend\DiscontinueEntity::where('id', $id)->delete();
            \App\Models\Backend\DiscontinueComment::where('discontinue_entity_id', $id)->delete();
            \App\Models\Backend\DiscontinueEntityAudit::where('discontinue_entity_id', $id)->delete();
            \App\Models\Backend\DiscontinueEntityStage::where('discontinue_entity_id', $id)->delete();
            \App\Models\Backend\DiscontinueQuestionAnswer::where('discontinue_entity_id', $id)->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Discontinue client has been restore successfully', ['message' => 'Discontinue client has been restore successfully']);
        } catch (\Exception $e) {
            app('log')->error("Discontinue client ticket deatail faild " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get discontinue client detail', ['error' => 'Could not get discontinue client details']);
        }
    }

    public function countFFamount($id) {
        $includeInFF = \App\Models\Backend\BillingServices::select('service_id', 'frequency_id', 'fixed_total_amount', 'fixed_fee')->where('entity_id', $id)->where('inc_in_ff', 1)->where('is_latest', 1)->where('is_active', 1)->whereIn('service_id', [1, 2])->get();
        $ffAmount = 0;
        foreach ($includeInFF as $key => $value) {
            $coreAmount = $value->fixed_total_amount;
            if ($value->service_id == 2)
                $coreAmount = $value->fixed_fee;

            switch ($value['frequency_id']) {
                case 1: // Weekly
                    $ffAmount += ($coreAmount * 52) / 12;
                    break;
                case 2: // Fortnightly
                    $ffAmount += ($coreAmount * 26) / 12;
                    break;
                case 4: // Quarterly
                    $ffAmount += ($coreAmount * 4) / 12;
                    break;
                case 5: // Yearly
                    $ffAmount += ($coreAmount * 1) / 12;
                    break;
                case 6: // Half monthly
                    $ffAmount += ($coreAmount * 24) / 12;
                    break;
                default:
                    $ffAmount += $coreAmount;
                    break;
            }
        }
        $ffAmount = ($ffAmount * 1.1);        
        return $ffAmount;
    }

    public function updateReason(Request $request, $id){
        try{
             $validator = app('validator')->make($request->all(), [
                'discontinue_comment' => 'required']);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            \App\Models\Backend\DiscontinueEntity::where("id",$id)
                    ->update(["discontinue_comment"=>$request->input('discontinue_comment')]);
            
        } catch (Exception $ex) {

        }
        
    }
}
