<?php

namespace App\Http\Controllers\Backend\DebtorsManagament;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DebtorsCommentController extends Controller {

    /**
     * Get quality comment detail
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
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $comment = \App\Models\Backend\DMComment::with('createdBy:id,userfullname')
                    ->leftjoin("entity as e", "e.id", "dm_comments.entity_id")
                    ->select("e.name as entity_name", "dm_comments.*")
                    ->where('entity_id', $id);
            if ($request->has('search')) {
                $search = $request->get('search');
                $comment = search($comment, $search);
            }

            if ($sortBy == 'created_by') {
                $comment = $comment->leftjoin("user as u", "u.id", "dm_comments.$sortBy");
                $sortBy = 'userfullname';
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $comment = $comment->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $comment->count();

                $comment = $comment->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $comment = $comment->get();

                $filteredRecords = count($comment);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), "DM Comment list.", ['data' => $comment], $pager);
        } catch (\Exception $e) {
            app('log')->error("DM Comment listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while client not on ddr comment listing", ['error' => 'Server error.']);
        }
    }

    /**
     * Store quality comment details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        try {
            // $right = checkButtonRights(38, 'dmcomment');
            // if ($right == false) {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sent_notification' => 'required|in:1,0',
                'to_mail' => 'email_array',
                'cc_mail' => 'email_array',
                'comment' => 'required'], []);


            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store client details
            $comment = \App\Models\Backend\DMComment::create([
                        'entity_id' => $id,
                        'sent_notification' => $request->input('sent_notification'),
                        'to_mail' => $request->input('to_mail'),
                        'cc_mail' => $request->input('cc_mail'),
                        'comment' => $request->input('comment'),
                        'created_by' => loginUser(),
                        'created_on' => date('Y-m-d H:i:s')]
            );
            if ($request->input('sent_notification') == 1) {
                $entity = \App\Models\Backend\Entity::find($id);
                $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", 'DMCOMMENT')->first();
                if ($emailTemplate->is_active) {
                    $userId = loginUser();
                    $user = \App\Models\User::find($userId);
                    $data['to'] = $request->input('to_mail');
                    $data['cc'] = $request->input('cc_mail');
                    $data['subject'] = str_replace(array("CLIENTNAME"), array($entity->billing_name), $emailTemplate->subject);

                    $content = html_entity_decode(str_replace(array('[ENTITYNAME]', '[USERNAME]', '[COMMENT]'),
                            array($entity->billing_name, $user->userfullname, $request->input('comment')),$emailTemplate->content));

                    $data['content'] = $content;
                    $store = storeMail($request, $data);
                }
            }

            return createResponse(config('httpResponse.SUCCESS'), 'DM Comment has been added successfully', ['data' => $comment]);
            /*  } else {
              return createResponse(config('httpResponse.SUCCESS'), "No Record Found", ['message' => 'No Record Found']);
              } */
        } catch (\Exception $e) {
            app('log')->error("DM Comment creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add client not on ddr comment', ['error' => 'Could not add client not on ddr comment']);
        }
    }

}
