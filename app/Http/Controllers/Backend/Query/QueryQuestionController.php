<?php
namespace App\Http\Controllers\Backend\Query;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class QueryQuestionController extends Controller {

    /**
     * Get Query detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        try {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'query_question.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $query_question = \App\Models\Backend\QueryQuestion::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id');
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $query_question = search($query_question, $search);
            }
            // for relation ship sorting
            if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $query_question =$query_question->leftjoin("user as u","u.id","query_question.$sortBy");
                $sortBy = 'userfullname';
            }
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $query_question = $query_question->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $query_question->count();

                $query_question = $query_question->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $query_question = $query_question->get(['query_question.*']);

                $filteredRecords = count($query_question);

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
                $data = $query_question->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Query name', 'Created on', 'Created By', 'Modified On', 'Modified By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['question_name'];
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['created_by']['created_by'];
                        $columnData[] = $data['modified_on'];
                        $columnData[] = $data['modified_by']['modified_by'];                        
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'QueryList', 'xlsx', 'A1:F1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Query list.", ['data' => $query_question], $pager);
        } catch (\Exception $e) {
            app('log')->error("Query listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Query", ['error' => 'Server error.']);
        }
    }
    /**
     * Store query_question details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        //try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'question_name' => 'required|unique:query_question',
                'is_active' => 'required|in:0,1',
                    ], ['query_question_name.unique' => "Query Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store query_question details
            $loginUser = loginUser();
            $query_question = \App\Models\Backend\QueryQuestion::create([
                        'question_name' => $request->input('question_name'),
                        'is_active' => $request->input('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Query has been added successfully', ['data' => $query_question]);
      /*} catch (\Exception $e) {
            app('log')->error("Query creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add query question', ['error' => 'Could not add query question']);
        }*/
            
    }

    /**
     * update Query details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // query_question id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'question_name' => 'unique:query_question,question_name,'.$id,
                'is_active' =>  'in:0,1',
                    ], ['question_name.unique' => "Query Name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $query_question = \App\Models\Backend\QueryQuestion::find($id);

            if (!$query_question)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Query does not exist', ['error' => 'The Query does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['question_name', 'is_active'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $query_question->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Query has been updated successfully', ['message' => 'Query has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Query updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update query_question details.', ['error' => 'Could not update query_question details.']);
        }
    }
   /**
     * get particular query_question details
     *
     * @param  int  $id   //query_question id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $query_question = \App\Models\Backend\QueryQuestion::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id')->find($id);

            if (!isset($query_question))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The query_question does not exist', ['error' => 'The query_question does not exist']);

            //send query_question information
            return createResponse(config('httpResponse.SUCCESS'), 'Query data', ['data' => $query_question]);
        } catch (\Exception $e) {
            app('log')->error("Query details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get query_question.', ['error' => 'Could not get query_question.']);
        }
    }   
      

}