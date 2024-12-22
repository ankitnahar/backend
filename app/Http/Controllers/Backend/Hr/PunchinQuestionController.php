<?php

namespace App\Http\Controllers\Backend\Hr;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PunchinQuestionController extends Controller {

    /**
     * Get Punchin question detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'punchin_question_answer.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            $punchinQuestion = \App\Models\Backend\PunchinQuestionAnswer::with('createdBy:id,userfullname')
                    ->leftjoin('punchin_question as pq',"pq.id","punchin_question_answer.question_id")
                    ->leftjoin('user as u',"u.id","punchin_question_answer.user_id")
                    ->select("u.userfullname","u.user_bio_id","pq.question","punchin_question_answer.*")
                    ->whereRaw("DATE(punchin_question_answer.created_on) BETWEEN '$startDate' and '$endDate'")
                    ->where("pq.is_active","1");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $punchinQuestion = search($punchinQuestion, $search);
            }
            // for relation ship sorting
            if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $punchinQuestion =$punchinQuestion->leftjoin("user as u","u.id","punchin questions.$sortBy");
                $sortBy = 'userfullname';
            }
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $punchinQuestion = $punchinQuestion->orderBy('punchin_question_answer.id', 'desc')->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $punchinQuestion->count();

                $punchinQuestion = $punchinQuestion->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $punchinQuestion = $punchinQuestion->get();

                $filteredRecords = count($punchinQuestion);

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
                $data = $punchinQuestion->toArray();
                $column = array();
                $column[] = ['Sr.No','User Bio Time','User Name', 'Question name','Answer', 'Created on', 'Created By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['user_bio_id'];
                        $columnData[] = $data['userfullname'];
                        $columnData[] = $data['question'];
                        $columnData[] = ($data['answer']==1) ? 'Yes' : 'No';
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['created_by']['userfullname'];                        
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Punchin questionList', 'xlsx', 'A1:G1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Punch in User Question list.", ['data' => $punchinQuestion], $pager);
        /*} catch (\Exception $e) {
            app('log')->error("Punchin question listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Punchin question", ['error' => 'Server error.']);
        }*/
    }
    /**
     * Store punchin question details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
       // try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'question_array' => 'required|json',
                    ], ['punchin question_name.unique' => "Punchin question Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store punchin question details
            $loginUser = loginUser();
            $questionArray = \GuzzleHttp\json_decode($request->input('question_array'), true);
            foreach($questionArray as $qs){               
                    if($qs['answer'] == 0){
                return createResponse(config('httpResponse.UNPROCESSED'), 'You need to give answer Yes only', ['error' => 'You need to give answer Yes only']);
                    }
            }
            foreach($questionArray as $ques){            
                   
            $punchinQuestion = \App\Models\Backend\PunchinQuestionAnswer::create([
                        'question_id' => $ques['question_id'],
                        'answer' => $ques['answer'],
                        'user_id' => $loginUser,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Punchin question has been added successfully', ['data' => $punchinQuestion]);
     /*  } catch (\Exception $e) {
            app('log')->error("Punchin question creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add punchin question', ['error' => 'Could not add punchin question']);
        }*/
    }
      

}
