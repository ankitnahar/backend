<?php

namespace App\Http\Controllers\Backend\Worksheet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\WorksheetTaskChecklist;

class TaskChecklistController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: Aug 28, 2018
     * Purpose   : Fetch worksheet task checklist data
     */

    public function index(Request $request, $id) {
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

        // define soring parameters
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        $worksheetDetails = \App\Models\Backend\Worksheet::select('entity_id', 'status_id', 'task_id', 'master_activity_id')->find($id);
        $entityId = $worksheetDetails->entity_id;
        $worksheettaskchecklist = WorksheetTaskChecklist::select('worksheet_task_checklist.id', 'worksheet_task_checklist.question_id', 'worksheet_task_checklist.group_id', 'worksheet_task_checklist.question_name', 'worksheet_task_checklist.help_text', 'worksheet_task_checklist.team_member_action', 'worksheet_task_checklist.reviewer_action', 'worksheet_task_checklist.technical_head_action')->where('worksheet_id', $id)
                ->where('is_revision_history', 0);

        $type = $request->get('type');
        $attentionQuestion = $technicalHeadAttentionQuestion = array();
        if ($type == 'P') {
            $attentionQuestion = \App\Models\Backend\WorksheetAttentionQuestion::
                            select('id', 'question_name', 'comment', 'is_processing_staff_checked', 'is_reviewer_checked', 'review_tag_id')
                            ->with('topicId:id,traning_name')
                            ->where('question_raised_worksheet_id', '!=', $id)
                            ->where('entity_id', $entityId)
                            ->where('is_processing_staff_checked', 1)
                            ->where('is_processing_staff_completed', 0)
                            ->get()->toArray();
        } else if ($type == 'R') {
            $attentionQuestion = \App\Models\Backend\WorksheetAttentionQuestion::
                            select('id', 'question_id', 'question_name', 'comment', 'is_processing_staff_checked', 'is_reviewer_checked', 'review_tag_id')
                            ->with('topicId:id,traning_name')
                            ->where('question_raised_worksheet_id', '!=', $id)
                            ->where('entity_id', $entityId)
                            ->where('is_reviewer_checked', 1)
                            ->where('is_reviewer_completed', 0)
                            ->where('action', array(2, 3))
                            ->get()->toArray();
        } else {
            
        }

        $worksheet_comment = \App\Models\Backend\WorksheetTaskChecklistComment::with('createdBy:id,userfullname')->where('worksheet_id', $id)->get();
        $worksheetCommentData = array();
        foreach ($worksheet_comment as $keyComment => $valueComment) {
            $worksheetCommentData[$valueComment->question_id][] = $valueComment->toArray();
        }

        if ($worksheettaskchecklist->count() == 0) {
            $masterChecklist = \App\Models\Backend\MasterChecklist::select('id')
                            ->where('master_activity_id', $worksheetDetails->master_activity_id)
                            ->where('task_id', $worksheetDetails->task_id)->first();

            app('db')->select('SET @defaultaction = 0;');
            $worksheettaskchecklist = \App\Models\Backend\EntityChecklistQuestion::select('entity_checklist_question.id as question_id', 'entity_checklist_question.checklist_group_id as group_id', 'entity_checklist_question.question_name', 'entity_checklist_question.question_name', 'entity_checklist_question.help_text', app('db')->raw('@defaultaction AS team_member_action'), app('db')->raw('@defaultcomment = NULL AS team_member_comment'), app('db')->raw('@defaultaction AS reviwer_action'), app('db')->raw('@defaultcomment AS reviewer_comment'), app('db')->raw('@defaultaction AS technical_head_action'), app('db')->raw('@defaultcomment AS technical_head_comment'))
                    ->join('entity_checklist AS ec', 'ec.id', '=', 'entity_checklist_question.entity_checklist_id')
                    ->where('ec.entity_id', $entityId)
                    ->where('ec.master_checklist_id', $masterChecklist->id)
                    ->where('ec.is_applicable', 1)
                    ->where('entity_checklist_question.is_applicable', 1);
        }

        $worksheettaskchecklist = $worksheettaskchecklist->get()->toArray();

        $taskChecklist = $group_id = array();
        foreach ($worksheettaskchecklist as $key => $value) {
            $taskChecklist[$value['group_id']][] = $value;
            if (!in_array($value['group_id'], $group_id))
                $group_id[] = $value['group_id'];
        }
        //$taskChecklist = $worksheettaskchecklist;
        $groupData = \App\Models\Backend\Checklistgroup::select('master_checklist_group.id', 'master_checklist_group.name', 'wcgc.is_checked', 'subactivity_id', 'is_require_timesheet')
                        ->leftjoin('worksheet_checklist_group_checked AS wcgc', function ($join) use($id) {
                            $join->on('wcgc.group_id', '=', 'master_checklist_group.id');
                            $join->where('wcgc.worksheet_id', '=', $id);
                        })->with('subactivity_id:id,subactivity_code')
                        ->whereIn('master_checklist_group.id', $group_id)->get()->toArray();
//        $groupData = array();
//
//        foreach ($group as $keyGroup => $valueGroup) {
//            $groupData[$valueGroup['id']] = $valueGroup;
//        }

        return createResponse(config('httpResponse.SUCCESS'), "Worksheet checklist list.", ['data' => $taskChecklist, 'group' => $groupData, 'attentionQuestion' => $attentionQuestion, 'worksheetCommentData' => $worksheetCommentData], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Worksheet checklist listing failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while worksheet checklist listing ", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July  18, 2018
     * Purpose   : Store worksheet training data
     */

    public function store(Request $request) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'worksheet_id' => 'required|numeric',
            'type' => 'required',
            'checklist' => 'required|json',
            'is_draft' => 'required|in:0,1',
            'attentionQuestion' => 'json'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $message = 'Worksheet task checklist saved successfully';
        $worksheet_id = $request->get('worksheet_id');
        $worksheet = \App\Models\Backend\Worksheet::find($worksheet_id);
        $type = $request->get('type');
        $status_id = $request->get('status_id');
        // $ratting = $request->get('user_rating');
        $historyRevision = WorksheetTaskChecklist::select(app('db')->raw('MAX(is_revision_history) AS is_revision_history'))->where('worksheet_id', $worksheet_id)->get();
        $historyRevision = $historyRevision[0]->is_revision_history + 1;

        //WorksheetTaskChecklist::where('is_revision_history', 0)->update(['is_revision_history' => $historyRevision]);
        /* Pankaj
         * Date - 14-11-2019
         */
        WorksheetTaskChecklist::where('is_revision_history', 0)->where('worksheet_id', $worksheet_id)->update(['is_revision_history' => $historyRevision]);
        $checklist = \GuzzleHttp\json_decode($request->get('checklist'));
        $group = \GuzzleHttp\json_decode($request->get('group'), true);

        $attentionQuestionComplete = 1;
        $is_draft = 0;
        WorksheetTaskChecklist::where('is_draft', 1)->where('worksheet_id', $worksheet_id)->delete();
        //WorksheetTaskChecklist::where('worksheet_id', $worksheet_id)->delete();
        \App\Models\Backend\WorksheetTaskChecklistComment::where('is_draft', 1)->where('staff_type', $type)->where('worksheet_id', $worksheet_id)->delete();
        \App\Models\Backend\WorksheetChecklistGroupChecked::where('is_draft', 1)->where('worksheet_id', $worksheet_id)->delete();
        \App\Models\Backend\WorksheetAttentionQuestion::where('is_draft', 1)->where('question_raised_worksheet_id', $worksheet_id)->delete();
        $alreadyCheckedGroup = \App\Models\Backend\WorksheetChecklistGroupChecked::where('worksheet_id', $worksheet_id)->where('is_draft', '!=', 1)->pluck('group_id', 'id')->toArray();

        //if ($worksheet->status_id == $status_id) {
        if ($request->get('is_draft') == 1) {
            $is_draft = 1;
            $attentionQuestionComplete = 0;
        }

        $worksheetChecklistData = $worksheetChecklistCommentData = $insertAttentionQuestion = $groupData = $tempSaving = array();
        foreach ($checklist as $key => $value) {
            $attentionQuestionData = array();
            $worksheetChecklist['worksheet_id'] = $worksheet_id;
            $worksheetChecklist['question_id'] = $value->question_id;
            $worksheetChecklist['group_id'] = $value->group_id;
            $worksheetChecklist['question_name'] = trim($value->question_name);
            $worksheetChecklist['help_text'] = $value->help_text;
            $worksheetChecklist['status_id'] = $status_id;
            $worksheetChecklist['is_revision_history'] = 0;
            $worksheetChecklist['is_draft'] = $is_draft;
            $worksheetChecklist['created_by'] = app('auth')->guard()->id();
            $worksheetChecklist['created_on'] = date('Y-m-d H:i:s');
            $worksheetChecklist['team_member_action'] = isset($value->team_member_action) ? $value->team_member_action : '';
            $worksheetChecklist['reviewer_action'] = isset($value->reviewer_action) ? $value->reviewer_action : '';
            $worksheetChecklist['technical_head_action'] = isset($value->technical_head_action) ? $value->technical_head_action : '';

            $comment = '';
            if (isset($value->team_member_comment) && $value->team_member_comment != '')
                $comment = trim($value->team_member_comment);

            if (isset($value->reviewer_comment) && $value->reviewer_comment != '')
                $comment = trim($value->reviewer_comment);

            if (isset($value->technical_head_comment) && $value->technical_head_comment != '')
                $comment = trim($value->technical_head_comment);

            if ($comment != '') {
                $worksheetChecklistComment['worksheet_id'] = $worksheet_id;
                $worksheetChecklistComment['task_id'] = $worksheet->task_id;
                $worksheetChecklistComment['question_id'] = $value->question_id;
                $worksheetChecklistComment['comment'] = $comment;
                $worksheetChecklistComment['staff_type'] = $type;

                $worksheetChecklistComment['review_tag_id'] = '';
                if (isset($value->review_tag_id) && $value->review_tag_id != '')
                    $worksheetChecklistComment['review_tag_id'] = $value->review_tag_id;

                $trainingdetail = '';
                if (isset($value->training_id) && !empty($value->training_id))
                    $trainingdetail = implode(',', $value->training_id);

                $worksheetChecklistComment['training_id'] = $trainingdetail;
                $worksheetChecklistComment['is_draft'] = $is_draft;
                $worksheetChecklistComment['created_by'] = app('auth')->guard()->id();
                $worksheetChecklistComment['created_on'] = date('Y-m-d H:i:s');
                $worksheetChecklistCommentData[] = $worksheetChecklistComment;
            }

            if ((isset($value->reviewer_action) && $value->reviewer_action == 3) || (isset($value->technical_head_action) && ($value->technical_head_action == 3 || $value->technical_head_action == 2))) {
                $attentionQuestionData['question_raised_worksheet_id'] = $worksheet_id;
                $attentionQuestionData['entity_id'] = $worksheet->entity_id;
                $attentionQuestionData['attention_by'] = $type;
                $attentionQuestionData['question_id'] = $value->question_id;
                $attentionQuestionData['question_name'] = $value->question_name;

                if ($type == 'R') {
                    $attentionQuestionData['action'] = $value->reviewer_action;
                    $attentionQuestionData['comment'] = $value->reviewer_comment;
                    $worksheetChecklistComment['review_tag_id'] = '';
                    if (isset($value->review_tag_id) && $value->review_tag_id != '')
                        $worksheetChecklistComment['review_tag_id'] = $value->review_tag_id;

                    $trainingdetail = '';
                    if (isset($value->training_id) && !empty($value->training_id))
                        $trainingdetail = implode(',', $value->training_id);

                    $worksheetChecklistComment['training_id'] = $trainingdetail;
                }
                if ($type == 'T') {
                    $attentionQuestionData['action'] = $value->technical_head_action;
                    $attentionQuestionData['comment'] = $value->technical_head_comment;
                }

                $attentionQuestionData['is_draft'] = $is_draft;
                $attentionQuestionData['created_by'] = app('auth')->guard()->id();
                $attentionQuestionData['created_on'] = date('Y-m-d H:i:s');
                $insertAttentionQuestion[] = $attentionQuestionData;
            }

            if ($type == 'R' && $value->review_tag_id == 1) {
                $worksheet->neglience_count = 1;
            }

            if (isset($group[$value->group_id]['is_checked']) && !in_array($value->group_id, $alreadyCheckedGroup)) {
                $groups = array();
                if (!in_array($group[$value->group_id]['id'], $tempSaving)) {
                    $groups['worksheet_id'] = $worksheet_id;
                    $groups['group_id'] = $group[$value->group_id]['id'];
                    $groups['is_checked'] = $group[$value->group_id]['is_checked'];
                    $groups['is_draft'] = $is_draft;
                    $groups['created_by'] = app('auth')->guard()->id();
                    $groups['created_on'] = date('Y-m-d H:i:s');
                    $tempSaving[] = $group[$value->group_id]['id'];
                    $groupData[] = $groups;
                }
            }
            $worksheetChecklistData[] = $worksheetChecklist;
        }

        if (!empty($worksheetChecklistData))
            app('db')->table('worksheet_task_checklist')->insert($worksheetChecklistData);

        if (!empty($worksheetChecklistComment))
            app('db')->table('worksheet_task_checklist_comment')->insert($worksheetChecklistCommentData);

        if (!empty($insertAttentionQuestion))
            app('db')->table('worksheet_attention_question')->insert($insertAttentionQuestion);

        if (!empty($groupData))
            app('db')->table('worksheet_checklist_group_checked')->insert($groupData);

        // Update reviewer attention question
        if ($request->has('attentionQuestion') && $type != 'TH') {
            $attentionQuestion = \GuzzleHttp\json_decode($request->get('attentionQuestion'));
            foreach ($attentionQuestion as $keyAttentionQuestion => $valueAttentionQuestion) {
                $objAttentionQuestion = \App\Models\Backend\WorksheetAttentionQuestion::find($valueAttentionQuestion->id);
                $objAttentionQuestion->attend_question_worksheet_id = $worksheet_id;
                if ($type == 'P') {
                    $objAttentionQuestion->is_processing_staff_checked = $valueAttentionQuestion->is_processing_staff_checked;
                    $objAttentionQuestion->is_processing_staff_completed = $attentionQuestionComplete;
                }

                if ($type == 'R') {
                    $objAttentionQuestion->is_reviewer_checked = $valueAttentionQuestion->is_reviewer_checked;
                    $objAttentionQuestion->is_reviewer_completed = $attentionQuestionComplete;
                }
                $objAttentionQuestion->save();
            }
        }

        //
//        if ($status_id == 15 || $status_id == 2) {
//            $outCome = config('constant.outcome');
//            \App\Models\Backend\WorksheetTaskchecklistOutcome::create(['outcome' => $outCome[$type],
//                'status_id' => $status_id,
//                'created_by' => app()->guard()->id(),
//                'created_on' => date('Y-m-d H:i:s')]);
//        }
        // Worksheet knockback by reviewer and but not in save as draft
        if ($type == 'R' && $is_draft == 0 && $status_id == 9) {
            $worksheet->knockback_count = 1;
            $this->changeWorksheetStatus($worksheet, $status_id);
            $worksheet->user_rating = isset($ratting) ? $ratting : 0;
            $message = 'Worksheet task checklist mark as knockback successfully';
        }

        if ($type == 'TH' && $is_draft == 0 && $status_id == 23) {
            $message = 'Worksheet task checklist peer review completed successfully';
        }

        if ($is_draft == 0) {
            $worksheet->status_id = $status_id;
            $worksheet->modified_by = app('auth')->guard()->id();
            $worksheet->modified_on = date('Y-m-d H:i:s');
            $worksheet->save();

            \App\Models\Backend\WorksheetLog::create(['worksheet_id' => $worksheet_id,
                'status_id' => $status_id,
                'created_by' => app('auth')->guard()->id(),
                'created_on' => date('Y-m-d H:i:s')]);
        }

        return createResponse(config('httpResponse.SUCCESS'), $message, ['message' => $message]);
//        } catch (\Exception $e) {
//            app('log')->error("Worksheet checklist store failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while worksheet checklist store ", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 18, 2018
     * Purpose   : Show worksheet training data
     */

    public function show(Request $request, $id) {
        try {
            die("hi");
        } catch (\Exception $e) {
            die("hi");
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Aug 23, 2018
     * Purpose   : Store worksheet notes data
     */

    public function storenote(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'worksheet_id' => 'required|numeric',
                'type' => 'required|in:P,R',
                'notes' => 'required',
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store worksheet training  details
            $notes = \App\Models\Backend\WorksheetNotes::create(['worksheet_id' => $request->get('worksheet_id'),
                        'notes' => $request->get('notes'),
                        'type' => $request->get('type'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);
            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet training  has been added successfully', ['data' => $notes]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet training  creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add worksheet training ', ['error' => 'Could not add worksheet training ']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Aug 23, 2018
     * Purpose   : Fetch worksheet notes
     */

    public function fetchnote(Request $request, $id) {
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
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $worksheetNote = \App\Models\Backend\WorksheetNotes::with('createdBy:id,userfullname')->where('worksheet_id', $id);

            if ($request->has('search')) {
                $search = $request->get('search');
                $worksheetNote = search($worksheetNote, $search);
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $worksheetNote = $worksheetNote->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $worksheetNote->count();

                $worksheetNote = $worksheetNote->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $worksheetNote = $worksheetNote->get(['worksheet_notes.*']);
                $filteredRecords = count($worksheetNote);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            $worksheetNote = \App\Models\Backend\WorksheetNotes::arrangeData($worksheetNote);
            return createResponse(config('httpResponse.SUCCESS'), "Worksheet training list.", ['data' => $worksheetNote], $pager);
        } catch (\Exception $e) {
            app('log')->error("Worksheet notes listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Worksheet notes", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Sept 03, 2018
     * Purpose   : To fetch entity task checklist email view
     */

    public function checklistEmailpreview(Request $request, $id) {

        $worksheet = new WorksheetController();
        $worksheetData = $worksheet->show($request, $id);
        $worksheetDetail = $worksheetData->original['payload']['data'];
        $entity_id = $worksheetDetail->entity_id;
        $service_id = $worksheetDetail->service_id;
        $task_id = $worksheetDetail->task_id;
        $end_date = $worksheetDetail->end_date;
        $fromEmail = ($request->has('from')) ? $request->get('from') : '';
        $allcationData = entityAllocationdata($entity_id, $service_id);
        $entity = \App\Models\Backend\Entity::select('*', app('db')->raw('JSON_EXTRACT(dynamic_json, "$.4.43") AS dynamic_json'))->where('id', $entity_id)->first();

        $entity->dynamic_json = str_replace('"', '', $entity->dynamic_json);
        $payment_type = '';
        if (isset($entity->dynamic_json) && $entity->dynamic_json == 'Yes- Direct Send to client')
            $payment_type .= 'We have setup the payments on the bank. Please check and authorize <br/>';

        if (isset($entity->dynamic_json) && $entity->dynamic_json == 'Yes-Require prior Approval')
            $payment_type .= 'Please check payroll reports and approve so we can setup payment on bank for you to authorize. <br/>';


        $worksheetGroup = \App\Models\Backend\WorksheetChecklistGroupChecked::select(app('db')->raw('mcg.email_content'))->leftjoin('master_checklist_group AS mcg', 'mcg.id', '=', 'group_id')->where('worksheet_id', $id)->where('is_checked', 1)->get();

        foreach ($worksheetGroup as $key => $value) {

            $payment_type .= $value->email_content . '<br/>';
        }

        $decodeAllocation = \GuzzleHttp\json_decode($allcationData->allocation_json, true);
        $from = array();
        $tam = '';
        if (isset($decodeAllocation[9]) && $decodeAllocation[9] != '')
            $tam = $decodeAllocation[9];

        //$from = \App\Models\User::where('id', $decodeAllocation[9])->pluck('userfullname', 'email');

        $entityContact = \App\Models\Backend\Contact::where('entity_id', $entity_id)->whereRaw(app('db')->raw('FIND_IN_SET(' . $service_id . ', service_id)'))->where('is_archived', 0);
        //if ($service_id == 1) {
        $entityContact->select(app('db')->raw('GROUP_CONCAT(DISTINCT `to`) AS `to`'), app('db')->raw('GROUP_CONCAT(cc) AS cc'), 'contact_person', 'first_name', 'from_email', 'from_name', 'bcc');
        $entityContact->where('is_display_bk_checklist', 1);
        //}
        $entityContact = $entityContact->limit(1)->get()->toArray();


        switch ($worksheetDetail->task_id) {
            case '57':
                $code = "PAYROLLREPORTEMAIL";
                break;
            case '52':
                $code = "PAYGPAMENTSUMMARYEMAIL";
                break;
            case '51':
                $code = "SUPERANNUATIONREPORTEMAIL";
                break;
            case '50':
                $code = "PREPARESUPERANNUATIONREPORTEMAIL";
                break;
            case '54':
                $code = "PAYROLLTAXEMAIL";
                break;
            case '1':
                $code = "IASEMAIL";
                break;
            case '5':
                $code = "BKCHECKLISTMAIL";
                break;
            case '23':
                $code = "BKCHECKLISTMAIL";
                break;
            case '389':
                $code = "STPREPORT";
                break;
            default:
                $code = "PAYROLLDEFAULT";
                break;
        }

        $emailTemplates = \App\Models\Backend\WorksheetTaskchecklistEmailForClient::where('worksheet_id', $id)->orderBy('id', 'desc')->limit(1)->first();
        if (empty($emailTemplates))
            $emailTemplates = \App\Models\Backend\EmailTemplate::getTemplate($code);

        $bcc = '';
        $signature = \App\Models\Backend\EmailSignature::where('user_id', $tam)->where('is_deleted', 0)->first();
        if ($service_id == 1) {
            //$signature = \App\Models\Backend\EmailSignature::where('bk_user_id', $tam)->where('is_deleted', 0)->get();
            $from = array();
            $fromE = explode(",", $entityContact[0]['from_email']);
            for ($i = 0; $i < count($fromE); $i++) {
                $from[] = array('email' => trim($fromE[$i]), 'name' => 'Befree');
            }
            $signature = signatureTemplate(3, $entity_id);
            $bcc = trim($entityContact[0]['bcc']);
        } else {
            if ($service_id == 2) {
                // for maxtax client no need signature and from email also different
                $billing = \App\Models\Backend\Billing::where("entity_id", $entity_id)->first();
                if ($billing->entity_grouptype_id == 8 || $billing->entity_grouptype_id == 9) {
                    $signature = \App\Models\Backend\EmailSignature::where('id', '36')->where('is_deleted', "0")->first();
                    $from[] = array('email' => $signature->email, 'name' => 'Payroll Maxtax');
                    $bcc = $signature->bcc;
                    $signature = isset($signature->signature) ? $signature->signature : '';
                } else {
                    //$signature = \App\Models\Backend\EmailSignature::whereRaw("user_id = $tam")->where('is_deleted', 0);
                    $from = array();
                    $fromE = explode(",", $entityContact[0]['from_email']);
                    for ($i = 0; $i < count($fromE); $i++) {
                        $from[] = array('email' => trim($fromE[$i]), 'name' => 'Befree');
                    }
                    $signature = signatureTemplate(3, $entity_id, 2);
                    $bcc = trim($entityContact[0]['bcc']);
                }
            }
        }

        $clientName = $entity->trading_name != '' ? $entity->trading_name : $entity->name;
        $mmmyy = date('M y', strtotime($end_date));
        $month = date('F', strtotime($end_date));
        $year = date('Y', strtotime($end_date));
        $yyyy_yy = date("Y", strtotime('-1 year')) . '-' . date("Y");
        $freq = $worksheetDetail->frequency_name;
        $ddmmyyyy = dateFormat($end_date);
        $dd_mm_yyyy = date("14-08-Y");
        $contact_person = '';
        $additional = '';

        $cc = '';
        if (isset($entityContact[0]['contact_person']) && $entityContact[0]['contact_person'] != '') {
            $contact_person = $entityContact[0]['contact_person'];
            $cc = rtrim($entityContact[0]['cc'], ',');
        }

        $statusName = $worksheetDetail->status_name;
        $statusName = 'Ready for review';

        if ($code == "PAYGPAMENTSUMMARYEMAIL") {
            $ddmmyyyy = date("14-07-Y");
            $emaildata['subject'] = str_replace(array('CLIENTNAME', 'MMM YY'), array($clientName, $mmmyy), $emailTemplates->subject);
        } else if ($code == 'BKCHECKLISTMAIL') {
            $emaildata['subject'] = str_replace(array('CLIENTNAME', 'DD-MM-YYYY', 'MONTH YEAR'), array($clientName, $dd_mm_yyyy, $mmmyy), $emailTemplates->subject);
            $clientName = $contact_person;
        } else {
            //$emaildata['subject'] = str_replace(array('CLIENTNAME', 'DD-MM-YYYY'), array($clientName, $dd_mm_yyyy), $emailTemplates->subject);
            $emaildata['subject'] = str_replace(array('CLIENTNAME', 'DD-MM-YYYY', 'FREQUENCY', 'MMM YY', 'FREQ', 'STATUSNAME'), array($clientName, $ddmmyyyy, $freq, $mmmyy, $freq, $statusName), $emailTemplates->subject);
        }

        $client_name = $entity->trading_name != '' ? $entity->trading_name : $entity->name;
        $nextmmmyy = date('M Y', strtotime('+1 month', strtotime(date('Y-m', strtotime($end_date)))));
        //showArray($signature);

        $serch = array('CLIENTNAME', 'MMM YY', 'MONTH', 'YEAR', 'YYYY-YY', 'FREQ', 'DD.MM.YYYY', 'STATUSNAME', 'CLIENT_NAME', 'NEXTMMMYY', 'DD-MM-YYYY', 'CONTACT_PERSON', 'FREQUENCY', 'ADDITIONAL', 'PAYMENT_TYPE', 'SIGNATURE');
        $replace = array($clientName, $mmmyy, $month, $year, $yyyy_yy, $freq, $ddmmyyyy, $statusName, $client_name, $nextmmmyy, $dd_mm_yyyy, $contact_person, $freq, $additional, $payment_type, $signature);

        $emaildata['to'] = isset($entityContact[0]['to']) ? $entityContact[0]['to'] : '';
        $emaildata['from'] = $from;
        $emaildata['cc'] = $cc;
        $emaildata['bcc'] = $bcc;
        $emaildata['content'] = str_replace($serch, $replace, $emailTemplates->content);
        //$emaildata['signature'] = $signature->signature;

        return createResponse(config('httpResponse.SUCCESS'), 'Worksheet detail', ['data' => $worksheetData, 'emailDetail' => $emaildata, 'paymentOfWages' => $entity->dynamic_json]);
    }

    public function checklistEmail(Request $request, $id) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'to' => 'required',
            'from' => 'required',
            'fromname' => 'required',
            'subject' => 'required',
            'content' => 'required',
            'status_id' => 'required|numeric',
            'old_status_id' => 'required|numeric',
            'type' => 'required'], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $message = '';
        $is_email_sent_to_client = 0;
        $status_id = $request->get('status_id');
        $to = $request->get('to');
        $from = $request->get('from');
        $fromName = $request->get('fromname');
        $type = $request->get('type');
        $cc = $request->get('cc');
        $bcc = $request->get('bcc');
        $subject = $request->get('subject');
        $content = $request->get('content');
        //$signature = $request->get('signature');
        $peerReview = $request->get('is_peer_review');
        $oldStatus = $request->get('old_status_id');
        $outCome = config('constant.outcome');
        $url = config('constant.url.base');

        $attechment = \App\Models\Backend\WorksheetDocument::where('worksheet_id', $id)->where('is_sent', 1)->where('is_deleted', 0)->pluck('id', 'id')->toArray();
//            $attechment = array();
//            if ($request->has('attechment') && $request->get('attechment') != '')
//                $attechment = \GuzzleHttp\json_decode($request->get('attechment'));


        if (in_array($status_id, array(13, 20)))
            $is_email_sent_to_client = 1;

        \App\Models\Backend\WorksheetTaskchecklistEmailForClient::insert([
            'worksheet_id' => $id,
            'from' => $from,
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $subject,
            'content' => $content,
            'is_email_sent_to_client' => $is_email_sent_to_client,
            // 'signature' => $signature,
            'is_send_attachments' => implode(',', $attechment),
            'created_by' => app('auth')->guard()->id(),
            'created_on' => date('Y-m-d H:i:s'),
        ]);

        $worksheet = \App\Models\Backend\Worksheet::find($id);
        $entity = entityAllocationdata($worksheet->entity_id, $worksheet->service_id);

//            if (!empty($attechment))
//                \App\Models\Backend\WorksheetDocument::whereIn('id', $attechment)->update(['is_sent' => 1]);
        $billing = \App\Models\Backend\Billing::where("entity_id", $worksheet->entity_id)->first();
        if ($billing->entity_grouptype_id == 8 || $billing->entity_grouptype_id == 9) {
            $data['withoutheaderfooter'] = 1;
        }
        if ($status_id == 2) {
            $reviewerLead = 0;
            if (isset($entity->allocation_json) && $entity->allocation_json != '') {
                $entity = \GuzzleHttp\json_decode($entity->allocation_json, true);
                $reviewerLead = isset($entity[70]) ? $entity[70] : 0;
            }

            if ($entity[10] == app('auth')->guard()->id()) {
                $worksheet->worksheet_actual_teammember = app('auth')->guard()->id();
            }

            if ($oldStatus == 18) {
                $notes = \App\Models\Backend\WorksheetNotes::create(['worksheet_id' => $id,
                            'notes' => $request->get('tam_comment'),
                            'type' => 'TAM',
                            'created_by' => app('auth')->guard()->id(),
                            'created_on' => date('Y-m-d H:i:s')]);
            }

            if ($oldStatus == 9) {
                \App\Models\Backend\WorksheetTaskchecklistOutcome::create(['outcome' => $outCome[$type],
                    'status_id' => $status_id,
                    'created_by' => app('auth')->guard()->id(),
                    'created_on' => date('Y-m-d H:i:s')]);
            }

            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('WSR');


            if ($reviewerLead != 0 && !empty($emailTemplate)) {
                $reviewerLeadDetail = \App\Models\User::find($reviewerLead);
                $entityDetail = \App\Models\Backend\Entity::find($worksheet->entity_id);

                $queryString = \GuzzleHttp\json_encode(array('entity_id' => $worksheet->entity_id, 'type' => 1));
                $link = '<a href="' . $url . 'workflow/worksheet-module"><b>Click here</b></a>';
                $period = 'period - ' . dateFormat($worksheet->start_date) . ' To ' . dateFormat($worksheet->end_date);
                $search = array('STAFFNAME', 'CLIENT_NAME', 'PERIOD_TASK', 'UPDATEDBY', 'HERELINK');
                $replace = array($reviewerLeadDetail->userfullname, $entityDetail->trading_name, $period, app('auth')->guard()->user()->userfullname, $link);
                $data['to'] = $reviewerLeadDetail->email;
                $data['cc'] = $emailTemplate->cc;
                $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                $data['content'] = str_replace($search, $replace, $emailTemplate->content);
                storeMail('', $data);
            }
            $message = 'Task checklist has been updated & status mark as Ready for review.';
        } else if ($status_id == 18) {
            $technicalAccountManager = 0;
            if (isset($entity->allocation_json) && $entity->allocation_json != '') {
                $entity = \GuzzleHttp\json_decode($entity->allocation_json, true);
                $technicalAccountManager = isset($entity[9]) ? $entity[9] : 0;
                $tl = isset($entity[60]) ? $entity[60] : 0;
            }

            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('WSPFTLR');
            if ($technicalAccountManager != 0 && !empty($emailTemplate)) {
                $technicalAccountManager = \App\Models\User::find($technicalAccountManager);
                $email = $technicalAccountManager->email;
                if (!empty($tl) && $tl > 0) {
                    $tl = \App\Models\User::find($tl);
                    $email = $technicalAccountManager->email . ',' . $tl->email;
                }
                $entityDetail = \App\Models\Backend\Entity::find($worksheet->entity_id);
                $queryString = \GuzzleHttp\json_encode(array('entity_id' => $worksheet->entity_id, 'type' => 1));
                $queryString = \GuzzleHttp\json_encode(array('id' => $worksheet->id, 'type' => 1));
                $link = '<a href="' . $url . 'workflow/worksheet-module"><b>Click here</b></a>';
                $period = 'period - ' . dateFormat($worksheet->start_date) . ' To ' . dateFormat($worksheet->end_date);
                $search = array('CLIENT_NAME', 'PERIOD_TASK', 'UPDATEDBY', 'HERELINK');
                $replace = array($entityDetail->trading_name, $period, app('auth')->guard()->user()->userfullname, $link);
                $data['to'] = $email;
                $data['cc'] = $emailTemplate->cc;
                $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                $data['content'] = str_replace($search, $replace, $emailTemplate->content);
                storeMail('', $data);
            }
            $message = 'Task checklist has been updated & status mark as Ready to review for TAM.';
        } else if ($status_id == 19) {
            $technicalAccountManager = 0;
            if (isset($entity->allocation_json) && $entity->allocation_json != '') {
                $entity = \GuzzleHttp\json_decode($entity->allocation_json, true);
                $technicalAccountManager = isset($entity[9]) ? $entity[9] : 0;
            }

            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('READYTOSENTTOCLIENT');
            if ($technicalAccountManager != 0 && !empty($emailTemplate)) {
                $technicalAccountManager = \App\Models\User::find($technicalAccountManager);
                $entityDetail = \App\Models\Backend\Entity::find($worksheet->entity_id);
                //$queryString = \GuzzleHttp\json_encode(array('id' => $worksheet->id, 'type' => 1));
                $link = '<a href="' . $url . 'workflow/worksheet-module"><b>Click here</b></a>';

                $period = 'period - ' . dateFormat($worksheet->start_date) . ' To ' . dateFormat($worksheet->end_date);
                $search = array('STAFFNAME', 'CLIENT_NAME', 'PERIOD_TASK', 'UPDATEDBY', 'HERELINK');
                $replace = array($technicalAccountManager->userfullname, $entityDetail->trading_name, $period, app('auth')->guard()->user()->userfullname, $link);
                $data['to'] = $technicalAccountManager->email;
                $data['cc'] = $emailTemplate->cc;
                $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                $data['content'] = str_replace($search, $replace, $emailTemplate->content);
                storeMail('', $data);
            }
            $message = 'Task checklist has been updated & status mark as Ready for Sent to Client.';
        } else if (in_array($status_id, array(13, 15, 20))) {
            $entityDetail = \App\Models\Backend\Entity::find($worksheet->entity_id);
            // send mail to client if status report send and client approval
            if ($status_id == 13 || $status_id == 20) {
                //check attachment 
                $attachmentDetail = \App\Models\Backend\WorksheetDocument::where('worksheet_id', $id)->where('is_sent', 1)->where('is_deleted', 0);
                $attachmentClient = array();
                if ($attachmentDetail->count() > 0) {
                    // if yes then add attachment                    
                    foreach ($attachmentDetail->get() as $attachments) {
                        if ($attachments->is_drive == 1) {
                            $fileData = \App\Models\Backend\DirectoryEntityFile::where('file_id', $attachments->document_name)->where("move_to_trash", "0")->first();
                            $rawData = \Storage::disk('google')->get($fileData->file_id);
                            $commanFolder = '/uploads/documents/';
                            $uploadPath = storageEfs() . $commanFolder;
                            if (date("m") >= 7) {
                                $dir = date("Y") . "-" . date('Y', strtotime('+1 years'));
                                if (!is_dir($uploadPath . $dir)) {
                                    mkdir($uploadPath . $dir, 0777, true);
                                }
                            } else if (date("m") <= 6) {
                                $dir = date('Y', strtotime('-1 years')) . "-" . date("Y");
                                if (!is_dir($uploadPath . $dir)) {
                                    mkdir($uploadPath . $dir, 0777, true);
                                }
                            }

                            $mainFolder = $entityDetail->code;
                            if (!is_dir($uploadPath . $dir . '/' . $mainFolder))
                                mkdir($uploadPath . $dir . '/' . $mainFolder, 0777, true);

                            $location = 'Worksheet';
                            $document_path = $uploadPath . $dir . '/' . $mainFolder . '/' . $location;
                            $fPath = $commanFolder . $dir . '/' . $mainFolder . '/' . $location;
                            if (!is_dir($document_path))
                                mkdir($document_path, 0777, true);
                            
                            $path = $document_path .'/'. $fileData->file_name;
                            $emailPath = $fPath .'/'. $fileData->file_name;
                            $fi = file_put_contents($path, $rawData);
                            chmod($path, 0777);
                            $attachmentClient[] = array('path' => $emailPath, 'filename' => $fileData->file_name);
                        } else {
                            $attachmentClient[] = array('path' => $attachments->document_path . $attachments->document_name, 'filename' => $attachments->document_title);
                        }
                    }
                }

                $data['to'] = $to;
                $data['from'] = $from;
                $data['from_name'] = $fromName;
                $data['cc'] = $cc;
                $data['excludeHeader'] = "1";
                $data['bcc'] = $bcc;
                $data['subject'] = $subject;
                $data['content'] = $content;
                if (!empty($attachmentClient)) {
                    $data['attachment'] = $attachmentClient;
                }
                //showArray($data);exit; 
                storeMail('', $data);
            }
            // exit;
            // End Code 22-04-2019

            if ($status_id != 20) {
                $isKnockback = \App\Models\Backend\WorksheetLog::where('worksheet_id', $id)->where('status_id', 9)->count();
                if ($isKnockback == 1) {
                    \App\Models\Backend\WorksheetTaskchecklistOutcome::create(['outcome' => $outCome[$type],
                        'status_id' => $status_id,
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);
                }
            }

            if ($status_id == 13 && $peerReview == 1) {
                $worksheet->is_peer_review = 1;
                $status_id = 22;
                $entityDetail = \App\Models\Backend\Entity::find($worksheet->entity_id);
                $link = '<a href="' . $url . 'workflow/worksheet-module/peer-review-worksheet-listing"><b>Click here</b></a>';
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('PEERREVIEWREQUIRED');
                $technicalHead = \App\Models\User::find(48);
                $search = array('STAFFNAME', 'CLIENT_NAME', 'CLICKHERE');
                $replace = array($technicalHead->userfullname, $entityDetail->trading_name, $link);

                $data['to'] = $technicalHead->email;
                $data['cc'] = $emailTemplate->cc;
                $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                $data['content'] = str_replace($search, $replace, $emailTemplate->content);
                storeMail('', $data);
                $message = 'Task has been updated & status mark as report sent to peer review.';
            } else if ($status_id == 15) {
                if ($peerReview == 1) {
                    $worksheet->is_peer_review = 1;
                }

                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('FINALSENTTOAM');
                $technicalAccountManager = 0;
                if (isset($entity->allocation_json) && $entity->allocation_json != '') {
                    $entity = \GuzzleHttp\json_decode($entity->allocation_json, true);
                    $technicalAccountManager = isset($entity[9]) ? $entity[9] : 0;
                    $tl = isset($entity[60]) ? $entity[60] : 0;
                }

                if ($technicalAccountManager != 0 && !empty($emailTemplate)) {
                    $technicalAccountManager = \App\Models\User::find($technicalAccountManager);
                    $email = $technicalAccountManager->email;
                    if (!empty($tl) && $tl > 0) {
                        $tl = \App\Models\User::find($tl);
                        $email = $technicalAccountManager->email . ',' . $tl->email;
                    }
                    $entityDetail = \App\Models\Backend\Entity::find($worksheet->entity_id);
                    //$queryString = \GuzzleHttp\json_encode(array('id' => $worksheet->id, 'type' => 1));
                    $link = '<a href="' . $url . 'workflow/worksheet-module"><b>Click here</b></a>';
                    $period = 'period - ' . dateFormat($worksheet->start_date) . ' To ' . dateFormat($worksheet->end_date);
                    $search = array('CLIENT_NAME', 'PERIOD_TASK', 'UPDATEDBY', 'HERELINK');
                    $replace = array($entityDetail->trading_name, $period, app('auth')->guard()->user()->userfullname, $link);
                    $data['to'] = $email;
                    $data['cc'] = $emailTemplate->cc;
                    $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                    $data['content'] = str_replace($search, $replace, $emailTemplate->content);
                    storeMail('', $data);
                    $message = 'Task has been updated & status mark as final report sent to TAM/TL.';
                }
            } else {
                $message = 'Report sent successfully.';
            }
        } else if ($status_id == 21) {
            $technicalAccountManager = 0;
            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('READYTOPAYMENTSETUP');
            if (isset($entity->allocation_json) && $entity->allocation_json != '') {
                $entity = \GuzzleHttp\json_decode($entity->allocation_json, true);
                $technicalAccountManager = isset($entity[9]) ? $entity[9] : 0;
            }

            if ($technicalAccountManager != 0 && !empty($emailTemplate)) {
                $technicalAccountManager = \App\Models\User::find($technicalAccountManager);
                $entityDetail = \App\Models\Backend\Entity::find($worksheet->entity_id);
                $teamJson = \GuzzleHttp\json_decode($worksheet->team_json, true);
                $teamMember = '';
                if ($worksheet->worksheet_actual_teammember != 0) {
                    $teamMember = \App\Models\User::find($worksheet->worksheet_actual_teammember);
                } else {
                    if ($teamJson[10] != '')
                        $teamMember = \App\Models\User::find($teamJson[10]);
                }

                $queryString = \GuzzleHttp\json_encode(array('id' => $worksheet->id, 'type' => 1));
                $link = '<a href="' . $url . 'workflow/worksheet-module"><b>Click here</b></a>';
                $period = 'period - ' . dateFormat($worksheet->start_date) . ' To ' . dateFormat($worksheet->end_date);
                $search = array('STAFFNAME', 'CLIENT_NAME', 'PERIOD_TASK', 'UPDATEDBY', 'HERELINK');
                $replace = array($technicalAccountManager->userfullname, $entityDetail->trading_name, $period, app('auth')->guard()->user()->userfullname, $link);

                $reviewerName = app('auth')->guard()->user()->userfullname;
                if (!empty($teamMember)) {
                    $replace = array($teamMember->userfullname, $entityDetail->trading_name, $period, $reviewerName, $link);
                    $data['to'] = $teamMember->email;
                    $data['cc'] = $emailTemplate->cc;
                    $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                    $data['content'] = str_replace($search, $replace, $emailTemplate->content);
                    storeMail('', $data);
                    $message = 'Task has been updated & status change to ready for payment setup';
                }
            }
        }


        if ($status_id == 13)
            $worksheet->reportsent_count = 1;

        $worksheet->user_rating = $request->get('user_rating');
        $worksheet->status_id = $status_id;
        $worksheet->modified_by = app('auth')->guard()->id();
        $worksheet->modified_on = date('Y-m-d H:i:s');
        if ($worksheet->save()) {
            $worksheetLog = \App\Models\Backend\WorksheetLog::create(['worksheet_id' => $id,
                        'status_id' => $request->get('status_id'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);

            \App\Models\Backend\WorksheetTaskChecklist::where('is_draft', 1)->where('worksheet_id', $id)->update(['is_draft' => 0, 'is_revision_history' => 0]);
            \App\Models\Backend\WorksheetTaskChecklistComment::where('is_draft', 1)->where('worksheet_id', $id)->update(['is_draft' => 0]);
            \App\Models\Backend\WorksheetChecklistGroupChecked::where('is_draft', 1)->where('worksheet_id', $id)->update(['is_draft' => 0]);

            if ($type == 'P')
                \App\Models\Backend\WorksheetAttentionQuestion::where('is_draft', 1)->where('attend_question_worksheet_id', $id)->update(['is_draft' => 0, 'is_processing_staff_completed' => 1]);

            if ($type == 'R')
                \App\Models\Backend\WorksheetAttentionQuestion::where('is_draft', 1)->where('attend_question_worksheet_id', $id)->update(['is_draft' => 0, 'is_reviewer_completed' => 1]);
        }

        return createResponse(config('httpResponse.SUCCESS'), $message, ['data' => $message]);
//        } catch (\Exception $e) {
//            app('log')->error("Entity creation failed " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity', ['error' => 'Could not add entity']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate user input
     */

    public static function changeWorksheetStatus($worksheetData, $status) {
        $entityDetail = \App\Models\Backend\Entity::select('trading_name')->find($worksheetData->entity_id);
        $worksheet_id = $worksheetData->id;
        $user[] = $worksheetData->worksheet_additional_assignee != 0 ? $worksheetData->worksheet_additional_assignee : $worksheetData->worksheet_actual_teammember;
        $period = 'period - ' . dateFormat($worksheetData->start_date) . ' To ' . dateFormat($worksheetData->end_date);
        $search = array('STAFFNAME', 'CLIENT_NAME', 'PERIOD_TASK', 'UPDATEDBY', 'HERELINK');
        $url = config('constant.url.base');

        $queryString = \GuzzleHttp\json_encode(array('entity_id' => $worksheetData->entity_id));
        $link = '<a href="' . $url . 'workflow/worksheet-module/review-or-knock-back-worksheet?' . base64_encode($queryString) . '"><b>Click here</b></a>';


        $knockBackCount = \App\Models\Backend\WorksheetLog::where('worksheet_id', $worksheet_id)->where('status_id', 9)->count();
        // Intimate to division head
        if ($knockBackCount > 1) {
            $email_cc[] = '';
        }
        $entity = entityAllocationdata($worksheetData->entity_id, $worksheetData->service_id);
        $technicalAccountManager = '';
        $tl = '';
        if (isset($entity->allocation_json) && $entity->allocation_json != '') {
            $entity = \GuzzleHttp\json_decode($entity->allocation_json, true);
            if (isset($entity[9])) {
                $tamDetail = \App\Models\User::select('id', 'userfullname', 'email')->where('id', $entity[9])->first();
                $technicalAccountManager = $tamDetail->email;
            }
            if (isset($entity[60])) {
                $tlDetail = \App\Models\User::select('id', 'userfullname', 'email')->where('id', $entity[60])->first();
                $tl = $tlDetail->email;
            }
        }

        $usersDetail = \App\Models\User::select('id', 'userfullname', 'email')->whereIn('id', $user)->get()->toArray();
        $user = $email_cc = array();
        foreach ($usersDetail as $key => $value) {
            $user[$value['id']] = $value;
            if ($value['id'] != $worksheetData->worksheet_actual_teammember)
                $email_cc[] = $value['email'];
        }

        $code = '';
        switch ($status) {
            case 9:
                $code = 'WKNB';
                break;
            case 19:
                $code = 'READYTOPAYMENTSETUP';
                break;
            case 21:
                $code = 'READYTOSENTTOCLIENT';
                break;
            case 15:
        }
        if ($code != 'WKNB') {
            $technicalAccountManager = '';
        }
        $cc = '';
        if ($technicalAccountManager != '') {
            $cc = $technicalAccountManager;
        }
        $cctl = '';
        if ($tl != '') {
            $cctl = $tl;
        }

        if ($cc != '' && (count($email_cc) > 0)) {
            $cc = $cc . "," . implode(',', $email_cc);
        }
        if ($cctl != '') {
            $cc = $cc . ',' . $cctl;
        }

        $usrid = $worksheetData->worksheet_additional_assignee != 0 ? $worksheetData->worksheet_additional_assignee : $worksheetData->worksheet_actual_teammember;
        $emailTemplate = \App\Models\Backend\EmailTemplate::where('code', $code)->get()->toArray();
        $reviewerName = app('auth')->guard()->user()->userfullname;
        $replace = array($user[$usrid]['userfullname'], $entityDetail->trading_name, $period, $reviewerName, $link);
        $data['to'] = $user[$usrid]['email'];
        $data['cc'] = $cc;
        $data['subject'] = str_replace($search, $replace, $emailTemplate[0]['subject']);
        $data['content'] = str_replace($search, $replace, $emailTemplate[0]['content']);
        return storeMail('', $data);
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate user input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'traning_name' => 'required',
            'is_active' => 'required|in:0,1'
                ], ['is_active.required' => 'The status field is required',
            'is_active.in' => 'The selected is status is invalid']);
        return $validator;
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Feb 15, 2019
     * Purpose   : Preview email
     */

    public static function emailPreview(Request $request) {
        $header = config('mail.common.header');
        $footer = config('mail.common.footer');
        $content = $request->get('content');
        $content = $header . $content . $footer;
        return createResponse(config('httpResponse.SUCCESS'), '', ['data' => $content]);
    }

}
