<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\EntityChecklistQuestion;

/**
 * This is a entity class controller.
 * 
 */
class EntityChecklistQuestionController extends Controller {

    /**
     * Get entity detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id) {
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
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity_checklist_question.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $entityChecklistQuestion = EntityChecklistQuestion::select('mc.name as checklist_name', 'ma.name as masteractivity_name', 't.name as task_name', 'entity_checklist_question.question_name', 'entity_checklist_question.is_applicable')
                            ->leftJoin('entity_checklist as ec', 'ec.id', '=', 'entity_checklist_question.entity_checklist_id')
                            ->leftJoin('master_checklist as mc', 'mc.id', '=', 'ec.master_checklist_id')
                            ->leftJoin('master_activity as ma', 'ma.id', '=', 'mc.master_activity_id')
                            ->leftJoin('task as t', 't.id', '=', 'mc.task_id')->where('entity_checklist_question.entity_id', $id)->groupBy('entity_checklist_question.id');

            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array('master_activity_id' => 'mc', 'task_id' => 'mc', 'is_applicable' => 'entity_checklist_question');
                $entityChecklistQuestion = search($entityChecklistQuestion, $search, $alias);
            }

            // Checkout whethere client agreed service or not
            $isEntitychecklistquestion = EntityChecklistQuestion::where('entity_id', $id)->count();
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $entityChecklistQuestion = $entityChecklistQuestion->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = count($entityChecklistQuestion->get()->toArray());
                $entityChecklistQuestion = $entityChecklistQuestion->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $entityChecklistQuestion = $entityChecklistQuestion->get();

                $filteredRecords = count($entityChecklistQuestion);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Entity checklist.", ['data' => $entityChecklistQuestion, 'isClientchecklist' => $isEntitychecklistquestion], $pager);
        } catch (\Exception $e) {
            app('log')->error("Client listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while client checklist", ['error' => 'Server error.']);
        }
    }

    /**
     * update entity details
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_checklist_id' => 'required|numeric',
                'question' => 'json'], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);


            $addData = $entityChecklistQuestion = array();
            $checklistQuestion = \GuzzleHttp\json_decode($request->get('question'), true);
            $entityChecklist = \App\Models\Backend\EntityChecklist::find($request->get('entity_checklist_id'));
            foreach ($checklistQuestion as $key => $value) {
                foreach ($value as $questionKey => $questionValue) {
                    $addData['entity_id'] = $entityChecklist->entity_id;
                    $addData['checklist_group_id'] = $questionValue['checklist_group_id'];
                    $addData['entity_checklist_id'] = $request->get('entity_checklist_id');
                    $addData['question_name'] = $questionValue['question_name'];
                    $addData['help_text'] = $questionValue['help_text'];
                    $addData['is_applicable'] = $questionValue['is_applicable'];
                    $addData['created_by'] = app('auth')->guard()->id();
                    $addData['created_on'] = date('Y-m-d H:i:s');
                    $entityChecklistQuestion[] = $addData;
                }
            }

            Entitychecklistquestion::insert($entityChecklistQuestion);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity checklist has been added successfully', ['message' => 'Entity checklist has been added successfully.']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity checklist details', ['error' => 'Could not add entity checklist details.']);
        }
    }

    /**
     * update entity details
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'search' => 'json'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);


            $entityChecklist = \App\Models\Backend\EntityChecklist::select('entity_checklist.id', 'mc.name as checklistname', 'ma.name as masteractivityname', 't.name as taskname')
                            ->leftJoin('master_checklist as mc', 'mc.id', '=', 'entity_checklist.master_checklist_id')
                            ->leftJoin('master_activity as ma', 'ma.id', '=', 'mc.master_activity_id')
                            ->leftJoin('task as t', 't.id', '=', 'mc.task_id')
                            ->where('is_applicable', 1)->where('entity_id', $id)->get();

            $entityChecklistDetail = array();
            foreach ($entityChecklist as $key => $value) {
                $entityChecklistDetail[$value->id] = $value;
            }

            if (!isset($entityChecklist))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity checklist is not applicable', ['error' => 'Entity checklist is not applicable']);

            $entityChecklistQuestion = array();
            $entityChecklistId = \GuzzleHttp\json_decode($request->get('search'), true);
            $entityChecklistId = $entityChecklistId['compare']['equal']['entity_checklist_id'];

            $entityChecklistQuestion = EntityChecklistQuestion::where('entity_checklist_id', $entityChecklistId)->where("entity_id", $id);

            $newQuestoin = 0;
            $availableQuestion = $entityChecklistQuestion->count();
            if ($availableQuestion == 0) {
                $entityChecklistQuestion = \App\Models\Backend\EntityChecklist::select('mcq.*')
                        ->leftJoin('master_checklist_question as mcq', 'mcq.master_checklist_id', '=', 'entity_checklist.master_checklist_id')
                        ->where("entity_checklist.is_applicable", "1")
                        ->where('entity_checklist.entity_id', $id)
                        ->where('entity_checklist.id', $entityChecklistId);
                $newQuestoin = 1;
            }
            $entityChecklistQuestion = $entityChecklistQuestion->get();
            $entityChecklistQuestion = EntityChecklistQuestion::arrangeData($entityChecklistQuestion, $newQuestoin);
            return createResponse(config('httpResponse.SUCCESS'), 'Entity checklist question detail', ['data' => $entityChecklistQuestion['question'], 'group' => $entityChecklistQuestion['group'], 'checklist' => $entityChecklistDetail, 'availableQuestion' => $availableQuestion]);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity checklist details', ['error' => 'Could not add entity checklist details.']);
        }
    }

    /**
     * update entity details
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'entity_checklist_id' => 'required|numeric',
            'question' => 'json'], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);


        $entityChecklistId = $request->get('entity_checklist_id');
        $entityChecklistQuestionnew = \GuzzleHttp\json_decode($request->get('question'), true);
        // $existingQuestion = EntityChecklistQuestion::where('entity_checklist_id', $entityChecklistId)->get();
        $existingQuestion = EntityChecklistQuestion::where('entity_id', $id)->get();
        $entityChecklistQuestionold = array();
        foreach ($existingQuestion as $key => $value) {
            $entityChecklistQuestionold[$value->id] = $value;
        }

        $status = convertcamalecasetonormalcase(config('constant.entityCheckliststatus'));
        $entityChecklistQuestionchanges = $checklistUpdate = array();

        $is_change = array();
        $questionId = $questionContent = array();
        foreach ($entityChecklistQuestionnew as $key => $value) {
            foreach ($value as $newKey => $newValue) {
                if ($entityChecklistQuestionold[$newValue['id']]->is_applicable != $newValue['is_applicable']) {
                    $is_change[] = 1;
                }

                if ($entityChecklistQuestionold[$newValue['id']]->question_name != $newValue['question_name']) {
                    $is_change[] = 1;
                }

                if ($entityChecklistQuestionold[$newValue['id']]->help_text != $newValue['help_text']) {
                    $is_change[] = 1;
                }

                $entityChecklistQuestionStore = EntityChecklistQuestion::find($newValue['id']);
                $entityChecklistQuestionStore->entity_id = $id;
                $entityChecklistQuestionStore->question_name = $newValue['question_name'];
                $entityChecklistQuestionStore->help_text = $newValue['help_text'];
                $entityChecklistQuestionStore->is_applicable = $newValue['is_applicable'];
                $entityChecklistQuestionStore->save();
            }
        }

        $entityChecklist = \App\Models\Backend\EntityChecklist::select('mc.name as checklistname')
                        ->leftJoin('master_checklist as mc', 'mc.id', '=', 'entity_checklist.master_checklist_id')->where('entity_checklist.id', $entityChecklistId)->get();

        $entityChecklistQuestionchanges = array('checklist' => $entityChecklist[0]->checklistname);
        $entityChecklistQuestionhistory['entity_id'] = $id;
        $entityChecklistQuestionhistory['changes'] = json_encode($entityChecklistQuestionchanges);
        $entityChecklistQuestionhistory['modified_by'] = app('auth')->guard()->id();
        $entityChecklistQuestionhistory['modified_on'] = date('Y-m-d H:i:s');

        if (!empty($is_change) && in_array(1, $is_change))
            \App\Models\Backend\EntitychecklistquestionAudit::insert($entityChecklistQuestionhistory);

        return createResponse(config('httpResponse.SUCCESS'), 'Entity checklist has been updated successfully', ['message' => 'Entity checklist has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Client updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update entity checklist details.', ['error' => 'Could not update entity checklist details.']);
          } */
    }

    /**
     * update entity details
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Entity id
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

            // define soring parameters
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $entityChecklistQuestionhistory = \App\Models\Backend\EntitychecklistquestionAudit::with('modified_by:id,userfullname,email')->where('entity_id', $id);
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $entityChecklistQuestionhistory = $entityChecklistQuestionhistory->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $entityChecklistQuestionhistory->count();
                $entityChecklistQuestionhistory = $entityChecklistQuestionhistory->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $entityChecklistQuestionhistory = $entityChecklistQuestionhistory->get();

                $filteredRecords = count($entityChecklistQuestionhistory);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Entity checklist history detail', ['data' => $entityChecklistQuestionhistory], $pager);
        } catch (\Exception $e) {
            app('log')->error("Entity checklist history failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while entity checklist history", ['error' => 'Server error.']);
        }
    }

    /**
     * update get group details
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function getGroup($id) {
        try {
            //validate request parameters
            if ($id != '' && !is_numeric($id)) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => 'Checklist id not pass']);

            // define soring parameters            
            $entityChecklistDetail = \App\Models\Backend\MasterChecklist::select('ma.name as masteractivityname', 't.name as taskname')
                            ->leftJoin('master_activity as ma', 'ma.id', '=', 'master_checklist.master_activity_id')
                            ->leftJoin('task as t', 't.id', '=', 'master_checklist.task_id')
                            ->where('master_checklist.id', $id)->get()->toArray();

            $group = \App\Models\Backend\MasterChecklistGroup::select('name', 'id')->get()->pluck('name', 'id')->toArray();
            return createResponse(config('httpResponse.SUCCESS'), 'Entity checklist history detail', ['data' => $entityChecklistDetail, 'group' => $group]);
        } catch (\Exception $e) {
            app('log')->error("Entity checklist history failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while entity checklist history", ['error' => 'Server error.']);
        }
    }

    /**
     * add question detail
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function additionalQuestion(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_checklist_id' => 'required|numeric',
                'checklist_group_id' => 'required|numeric',
                'question_name' => 'required',
                'is_applicable' => 'required|in:0,1,2'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);
            $entityChecklist = \App\Models\Backend\EntityChecklist::find($request->get('entity_checklist_id'));
            $question = EntityChecklistQuestion::create([
                        'entity_id' => $entityChecklist->entity_id,
                        'entity_checklist_id' => $request->get('entity_checklist_id'),
                        'checklist_group_id' => $request->get('checklist_group_id'),
                        'question_name' => $request->get('question_name'),
                        'help_text' => $request->get('help_text'),
                        'is_applicable' => $request->get('is_applicable'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s'),
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Checklist question has been added successfully', ['data' => $question]);
        } catch (\Exception $e) {
            app('log')->error("Entity checklist question failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while entity checklist question faild", ['error' => 'Server error.']);
        }
    }

}
