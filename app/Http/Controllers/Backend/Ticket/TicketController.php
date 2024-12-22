<?php

namespace App\Http\Controllers\Backend\Ticket;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use DB;
use ZipArchive;

class TicketController extends Controller {

    /**
     * Get Ticket detail
     *
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

        // define soring parameters
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'ticket.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];

        $ticket = \App\Models\Backend\Ticket::getTickets();

        //check client allocation
        $entity_ids = checkUserClientAllocation(loginUser());
        $loginUser = loginUser();
        if (is_array($entity_ids)) {
            $entity_ids = implode(",", $entity_ids);
            $ticket = $ticket->whereRaw("(e.id IN ($entity_ids) OR ticket.created_by = $loginUser OR ta.ticket_assignee = $loginUser)");
        }
        //for search
        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array("code" => "ticket", "created_by" => "ticket", "created_on" => "ticket","parent_id" =>'e');
            $ticket = search($ticket, $search, $alias);
        }
        // for relation ship sorting
        if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
            $ticket = $ticket->leftjoin("user as u", "u.id", "ticket.$sortBy");
            $sortBy = 'userfullname';
        }
        $ticket = $ticket->groupBy("ticket.id");
        //echo getSQL($ticket);exit;
        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $ticket = $ticket->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $ticket->get()->count();

            $ticket = $ticket->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $ticket = $ticket->get();

            $filteredRecords = count($ticket);

            $pager = ['sortBy' => $sortByName,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }
        //For download excel if excel =1
        if ($request->has('excel') && $request->get('excel') == 1) {
            //format data in array
            $data = $ticket->toArray();
            $userData = \App\Models\User::get()->pluck("userfullname", "id")->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Ticket Code', 'Ticket type', 'Ticket status', 'Subject','Parent Trading Name', 'Client Name', 'Problem On Our Side', 'Division Head /TAM', 'TH/TL.ATL', 'Assign Staff', 'Created on', 'Created By', 'Client Status'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $th = "-";
                    if ($data['technical_head'] != null && $data['technical_head'] != '') {
                        $thData = explode(",", $data['technical_head']);
                        $userList = \App\Models\User::whereIn("id", $thData)
                                        ->select(DB::raw("GROUP_CONCAT(userfullname) as TH"))->first();
                        $th = $userList->TH;
                    }
                    $columnData[] = $i;
                    $columnData[] = $data['code'];
                    $columnData[] = $data['ticket_type'];
                    $columnData[] = $data['status'];
                    $columnData[] = $data['subject'];
                    $columnData[] = $data['parent_name'];
                    $columnData[] = $data['trading_name'];
                    $columnData[] = ($data['problem_our_side'] == 1) ? 'Yes' : 'No';
                    $columnData[] = isset($userData[$data['technical_account_manager']]) ? $userData[$data['technical_account_manager']] : '';
                    $columnData[] = $th;
                    $columnData[] = $data['ticket_assignee_with_date'];
                    $columnData[] = $data['created_on'];
                    $columnData[] = $data['created_by']['userfullname'];
                    $columnData[] = ($data['discontinue_stage'] == 1) ? 'Discontinue Process Initiated' : 'Active';
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'TicketList', 'xlsx', 'A1:N1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Ticket list.", ['data' => $ticket], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Ticket listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Ticket", ['error' => 'Server error.']);
          } */
    }

    /**
     * Store ticket details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'type_id' => 'required',
            'entity_id' => 'required_if:type_id,1,2,3,7,8,11,12,13,14,15,19,20,21,24',
            'team_id' => 'required_if:type_id,1,2,7,8,11,13,14,15,19,20,21,24',
            'status_id' => 'required',
            'severity' => 'required_if:type_id,5,16,26',
            'priority' => 'required_if:type_id,5,16',
            'subject' => 'required',
            'technical_account_manager' => 'required_if:type_id,1,2,7,8,11,13,14,15,19,20,21,24',
            'problem_our_side' => 'in:0,1',
            'staff_incharge' => 'required_if:type_id,9',
            'department_id' => 'required_if:type_id,26',
            'process' => 'required_if:type_id,26',
            'ticket_assignee' => 'required|json',
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $lastRow = \App\Models\Backend\Ticket::orderBy("id", "desc")->first();
        //showArray($lastRow);exit;
        // store ticket details
        $loginUser = loginUser();
        $entityId = $request->has('entity_id') ? $request->input('entity_id') : '1422';
        if ($request->input('type_id') == 5) {
            $entityId = 1422;
        } else if ($request->input('type_id') == 16) {
            $entityId = 386;
        }
        $ticket = \App\Models\Backend\Ticket::create([
                    'type_id' => $request->input('type_id'),
                    'entity_id' => $entityId,
                    'team_id' => $request->has('team_id') ? $request->input('team_id') : '',
                    'status_id' => $request->has('status_id') ? $request->input('status_id') : '',
                    'severity' => $request->has('severity') ? $request->input('severity') : '',
                    'priority' => $request->has('priority') ? $request->input('priority') : '',
                    'subject' => $request->has('subject') ? $request->input('subject') : '',
                    'technical_head' => $request->has('technical_head') ? $request->input('technical_head') : '',
                    'technical_account_manager' => $request->has('technical_account_manager') ? $request->input('technical_account_manager') : '',
                    'problem_our_side' => $request->has('problem_our_side') ? $request->input('problem_our_side') : '',
                    'staff_involved_issue' => $request->has('staff_involved_issue') ? $request->input('staff_involved_issue') : '',
                    'type_of_mistake' => $request->has('type_of_mistake') ? $request->input('type_of_mistake') : '',
                    'issue_detail' => $request->has('issue_detail') ? $request->input('issue_detail') : '',
                    'reason_why_this_has_occurred' => $request->has('reason_why_this_has_occurred') ? $request->input('reason_why_this_has_occurred') : '',
                    'resolution' => $request->has('resolution') ? $request->input('resolution') : '',
                    'staff_incharge' => $request->has('staff_incharge') ? $request->input('staff_incharge') : '',
                    'ticket_topic' => $request->has('ticket_topic') ? $request->input('ticket_topic') : '',
                    'sr_topic' => $request->has('sr_topic') ? $request->input('sr_topic') : '',
                    'sr_practice_id' => $request->has('sr_practice_id') ? $request->input('sr_practice_id') : '',
                    'sr_practice_name' => $request->has('sr_practice_id') ? $request->input('sr_practice_id') : '',
                    'process' => $request->has('process') ? $request->input('process') : '',
                    'sub_process' => $request->has('sub_process') ? $request->input('sub_process') : '',
                    'department_id' => $request->has('department_id') ? $request->input('department_id') : '',
                    'head_count' => $request->has('head_count') ? $request->input('head_count') : '',
                    'save_hour' => $request->has('save_hour') ? $request->input('save_hour') : '',
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => $loginUser
        ]);

        $code = $lastRow->code + 1;
        \App\Models\Backend\Ticket::where("id", $ticket->id)->update(["code" => $code]);
        if ($request->has('ticket_assignee')) {
            $this->addUpdateTicketAssignee($request, $request->get('ticket_assignee'), $ticket->id, 'Add');
        }

        //Upload File  
        if ($request->has('document_file') && $request->file('document_file') != null) {

            $path = $this->uploadDocument($request, $ticket->id, $code);
            \App\Models\Backend\Ticket::where("id", $ticket->id)->update(["doc_upload_path" => $path]);
        }
        // Send mail to respactive person
        $this->sentMail($request, 'Add', $ticket->id);

        return createResponse(config('httpResponse.SUCCESS'), 'Ticket has been added successfully', ['data' => $ticket]);
        /* } catch (\Exception $e) {
          app('log')->error("Ticket creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add ticket', ['error' => 'Could not add ticket']);
          } */
    }

    /**
     * update Ticket details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // ticket id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'is_flag' => 'required|in:1,0',
            'type_id' => 'required_if:is_flag,0',
            'entity_id' => 'required_if:type_id,1,2,3,7,8,11,12,13,14,15,19,20,21,22,23,24',
            'team_id' => 'required_if:type_id,1,2,7,8,11,13,14,15,19,20,21,22,23,24',
            'status_id' => 'required_if:is_flag,0',
            'severity' => 'required_if:type_id,5,16,26',
            'priority' => 'required_if:type_id,5,16',
            'subject' => 'required_if:is_flag,0',
            'technical_account_manager' => 'required_if:type_id,1,2,7,8,11,13,14,15,19,20,21,22,23,24',
            'problem_our_side' => 'in:0,1',
            'staff_incharge' => 'required_if:type_id,9',
            'department_id' => 'required_if:type_id,26',
            'process' => 'required_if:type_id,26',
            'ticket_assignee' => 'required_if:is_flag,0|json',
                ], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
        $ticket = \App\Models\Backend\Ticket::find($id);

        if (!$ticket)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Ticket does not exist', ['error' => 'The Ticket does not exist']);

        $updateData = array();
        // Filter the fields which need to be updated
        $loginUser = loginUser();
        $updateData = filterFields(['type_id', 'entity_id', 'team_id', 'status_id', 'severity', 'priority', 'subject', 'technical_head', 'technical_account_manager',
            'problem_our_side', 'staff_involved_issue', 'type_of_mistake', 'issue_detail', 'reason_why_this_has_occurred', 'resolution', 'staff_incharge', 'ticket_topic', 'sr_topic', 'sr_practice_id', 'sr_practice_name',
            'department_id', 'process', 'sub_process', 'hold_count', 'save_hour', 'flag_open', 'open_time', 'opened_by'], $request);
        if ($request->input('is_flag') == 1) {
            $updateData['open_time'] = date("H:i:s");
            $updateData['opened_by'] = loginUser();
        } else {
            $updateData['open_time'] = '';
            $updateData['opened_by'] = 0;
        }


        //update the details
        $ticket->update($updateData);
        if ($request->input('is_flag') == 0) {
            if ($request->has('ticket_assignee')) {
                $this->addUpdateTicketAssignee($request, $request->get('ticket_assignee'), $ticket->id, 'Update');
            }
            //Upload File  
            if ($request->has('document_file') && $request->file('document_file') != null) {

                $path = $this->uploadDocument($request, $ticket->id, $ticket->code);
                \App\Models\Backend\Ticket::where("id", $id)->update(["doc_upload_path" => $path]);
            }
            
            if ($request->input('status_id') == '5') {
                \App\Models\Backend\Ticket::where("id",$ticket->id)->update(["completed_date"=>date("Y-m-d")]);
                $this->sentMail($request, 'Close', $ticket->id);
            }
        }
        if ($request->input('is_flag') != 1) {
            return createResponse(config('httpResponse.SUCCESS'), 'Ticket has been updated successfully', ['message' => 'Ticket has been updated successfully']);
        } else {
            return createResponse(config('httpResponse.SUCCESS'), 'Ticket has been updated successfully', ['message' => '']);
        }
        /*  } catch (\Exception $e) {
          app('log')->error("Ticket updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update ticket details.', ['error' => 'Could not update ticket details.']);
          } */
    }

    public static function addUpdateTicketAssignee($request, $ticketAss, $ticketId, $type) {

        $ticket_assignee = \GuzzleHttp\json_decode($ticketAss);
        foreach ($ticket_assignee as $assignee) {
            $ticketAssigneeDetail = \App\Models\Backend\TicketAssignee::where("id", $assignee->id);
            if($ticketAssigneeDetail->count() > 0){
                $ticketAssigneeDetail =$ticketAssigneeDetail->first();
                if($ticketAssigneeDetail->mark_as_complete ==1){
                continue;
                }
            }
            $ticketAssignee['ticket_id'] = $ticketId;
            $ticketAssignee['action_require_details'] = $assignee->action_require_details;
            $ticketAssignee['ticket_assignee'] = $assignee->ticket_assignee;
            $ticketAssignee['action_taken_details'] = $assignee->action_taken_details;
            $ticketAssignee['mark_as_complete'] = $assignee->mark_as_complete;            
            $ticketAssignee['complete_date'] = ($assignee->mark_as_complete == 1) ? date('Y-m-d H:i:s') : '';
            $ticketAssignee['created_on'] = date('Y-m-d H:i:s');
            $ticketAssignee['created_by'] = loginUser();
            if ($assignee->mark_as_complete == 1) {
                self::assigneeCloseMail($ticketId, $ticketAssignee);
            }
            if (isset($assignee->id) && $assignee->id != '') {
                \App\Models\Backend\TicketAssignee::where("id", $assignee->id)->update($ticketAssignee);
            } else {
                \App\Models\Backend\TicketAssignee::create($ticketAssignee);
                if($request->input('status_id') == 3){
                    self::sentMail($request, 'Assignee', $ticketId);
                }
                /*if ($type == 'Update') {
                    //self::sentMail($request, 'Assignee', $ticketId);
                }*/
            }
        }
    }

    public static function sentMail($request, $type, $id) {
        $userEmail = \App\Models\User::where("is_active", 1)->select("id", "email")->pluck("email", "id")->toArray();
        $ticket = \App\Models\Backend\Ticket::find($id);
        $ticketType = \App\Models\Backend\TicketType::find($request->input('type_id'));
        if ($type == 'Add') {
            $template = 'NET';
            $email = $ticketType->add_email;
        } else if ($type == 'Edit') {
            $template = 'TSA';
            $email = $ticketType->update_email;
        } else if ($type == 'Assignee') {
            $template = 'NEWASSIGNEE';
            $email = $ticketType->update_email;
        } else {
            $template = 'TEC';
            $email = $ticketType->close_email;
        }

        $ticketAssigneeUser = \App\Models\Backend\TicketAssignee::
                leftjoin("user as u", "u.id", "ticket_assignee.ticket_assignee")
                ->select(DB::raw("group_concat(u.email) as email"))
                ->where("ticket_assignee.ticket_id", $id)
                ->where("u.is_active", "1")
                ->where("u.send_email", "1")
                ->first();
        $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", $template)->first();
        if ($emailTemplate->is_active) {
            $to = $ticketAssigneeUser->email;
            $tam = $tl = '';
            if ($ticket->technical_head != '' && $ticket->technical_head != 0 && $ticket->technical_head != null) {
                $tamList = [];
                $teamEmail = explode(",", $ticket->technical_head);
                for ($t = 0; $t < count($teamEmail); $t++) {
                    $tamList[] = $userEmail[$teamEmail[$t]];
                }
                $tam = implode(",", $tamList);
                $tam = "," . $tam;
            }

            if ($ticket->technical_account_manager != '') {
                $tl = ',' . $userEmail[$ticket->technical_account_manager];
            }
            $data['to'] = $to . $tam . $tl;
            $data['cc'] = isset($userEmail[$ticket->created_by]) ? $userEmail[$ticket->created_by] : $to;
            if ($email != '') {
                $data['cc'] = $data['cc'] . ',' . $email;
            }
            $data['to'] = rtrim($data['to'], ',');
            $data['cc'] = rtrim($data['cc'], ',');
            $data['subject'] = str_replace(array("[TICKET_TYPE]", "[SUBJECT]"), array($ticketType->name, $request->input('subject')), $emailTemplate->subject);

            $client = '';
            if ($request->has('entity_id') && $request->input('entity_id') > 0) {
                $entityName = \App\Models\Backend\Entity::select("name")->where("id", $request->input('entity_id'))->first();
                $client = '<p> Client Name: ' . $entityName->name;
            }
            $url = config('constant.url.base');
            $rawUrl = array('code' => $ticket->code);
            $queryString = urlEncrypting($rawUrl);
            $ticketCode = '<a href="' . $url . "workflow/pending-tickets?" . $queryString . '">' . $ticket->code . '</a>';
            $user = \App\Models\User::find(loginUser());
            $content = html_entity_decode(str_replace(array('[CODE]', '[SUBJECT]', '[DESCRIPTION]', '[TICKET_TYPE]', '[CLIENT]', '[UPDATED]', '[RESOLUTION]'), array($ticketCode, $request->input('subject'), $request->input('issue_detail'), $ticketType->name, $client, $user->userfullname, $ticket->resolution), $emailTemplate->content));

            $attachment = \App\Models\Backend\TicketDocument::where("ticket_id", $id)
                            ->where("is_deleted", "0")
                            ->select("document_path")
                            ->get()->pluck("document_path")->toArray();

            $data['content'] = $content;
            $data['attachment'] = $attachment;
            $store = storeMail($request, $data);
        }
    }

    public static function assigneeCloseMail($id, $ticketAssignee) {
        $userEmail = \App\Models\User::where("is_active", 1)->select("id", "email")->pluck("email", "id")->toArray();
        $ticket = \App\Models\Backend\Ticket::leftjoin("ticket_type as t", "t.id", "ticket.type_id")
                ->select("ticket.*", "t.name")
                ->find($id);

        $user = \App\Models\User::where("id", $ticketAssignee['ticket_assignee'])->where("is_active", "1")->where("send_email", "1");
        $template = 'TAC';
        if($user->count() > 0){
            $user = $user->first();
        $email = $user->email;


        $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", $template)->first();
        if ($emailTemplate->is_active) {
            $to = $email;
            $tl = $tam = '';
            if ($ticket->technical_account_manager != '' && $ticket->technical_account_manager != 0) {
                $tamEmail = $userEmail[$ticket->technical_account_manager];
                if($tamEmail!=''){
                $tam = ',' . $tamEmail;
                }
            }

            if ($ticket->technical_head != '' && $ticket->technical_head != 0) {
                $userTlEMail = \App\Models\User::whereIn("id", [$ticket->technical_head])->select(DB::raw("GROUP_CONCAT(email) as email"))
                                ->where("is_active", "1")->where("send_email", "1")->first();
                if($userTlEMail->email !=''){
                $tl = ',' . $userTlEMail->email;
                }
            }
            $tEmail = $to . $tam . $tl;
            $data['to'] = rtrim($tEmail,',');
            $data['cc'] = isset($userEmail[$ticket->created_by]) ? $userEmail[$ticket->created_by] : $email;
            if ($emailTemplate->cc != '') {
                $data['cc'] = $data['cc'] . ',' . $emailTemplate->cc;
            }
            $data['subject'] = $emailTemplate->subject;

            $client = '';
            if ($ticket->entity_id) {
                $entityName = \App\Models\Backend\Entity::select("name")->where("id", $ticket->entity_id)->first();
                $client = '<p> Client Name: ' . $entityName->name;
            }

            $table = ' <div class="table_template">
            <table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">
             <tr>
           <td>Action required details</td>
           <td>Assign staff</td>
           <td>Action taken details</td>
           <td>Complete</td>
           <td>Completion date</td>
       </tr>
       <tr>
           <td>' . $ticket->subject . '</td>
           <td>' . $user->userfullname . '</td>
           <td>' . $ticketAssignee['action_taken_details'] . '</td>
           <td>Yes</td>
           <td>' . date('Y-m-d H:i:s') . '</td>
       </tr></table></div>';

            $url = config('constant.url.base');
            $rawUrl = array('code' => $ticket->code);
            $queryString = urlEncrypting($rawUrl);
            $ticketCode = '<a href="' . $url . "workflow/pending-tickets?" . $queryString . '">' . $ticket->code . '</a>';
            $user = \App\Models\User::find(loginUser());
            $content = html_entity_decode(str_replace(array('[CODE]', '[SUBJECT]', '[DESCRIPTION]', '[TICKET_TYPE]', '[UPDATED]', '[TABLE-ACTION]'), array($ticketCode, $ticket->subject, $ticket->issue_detail, $ticket->name, $user->userfullname, $table), $emailTemplate->content));

            $data['content'] = $content;
            $store = cronStoreMail($data);
        }
        }
    }

    /**
     * get particular ticket details
     *
     * @param  int  $id   //ticket id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        //try {
        $ticket = \App\Models\Backend\Ticket::getTickets()->where("ticket.id", $id)->first();
        $th = '';
        if ($ticket->technical_head != '' && $ticket->technical_head != null) {
            $thData = explode(",", $ticket->technical_head);
            $userList = \App\Models\User::whereIn("id", $thData)
                            ->select(DB::raw("GROUP_CONCAT(userfullname) as TH"))->first();
            $th = $userList->TH;
        }
        $ticket['TH'] = $th;
        $ticketAssignee = \App\Models\Backend\TicketAssignee::
                leftjoin("user as u", "u.id", "ticket_assignee.ticket_assignee")
                ->select("u.userfullname", "ticket_assignee.*")
                ->where("u.is_active","1")
                ->where("ticket_id", $id);
        $ticketDocument = \App\Models\Backend\TicketDocument::where("ticket_id", $id)->where("is_deleted", "0");
        $ticket['ticketDocument'] = '';
        if ($ticketDocument->count() > 0) {
            $ticket['ticketDocument'] = $ticketDocument->get();
        }
        $ticket['ticketAssignee'] = '';
        if ($ticketAssignee->count() > 0) {
            $ticket['ticketAssignee'] = $ticketAssignee->get();
        }
        if (!isset($ticket))
            return createResponse(config('httpResponse.NOT_FOUND'), 'The ticket does not exist', ['error' => 'The ticket does not exist']);

        //send ticket information
        return createResponse(config('httpResponse.SUCCESS'), 'Ticket data', ['data' => $ticket]);
        /* } catch (\Exception $e) {
          app('log')->error("Ticket details api failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get ticket.', ['error' => 'Could not get ticket.']);
          } */
    }

    /*
     * Created By - Pankaj
     * Created On - 25/04/2018
     * Common function for save history
     */

    public static function saveHistory($model, $col_name) {
        //showArray($model);exit;
        $ArrayYesNo = array('reason_why_this_has_occurred', 'problem_our_side');
        $ArrayDropdown = array('type_id', 'entity_id', 'team_id', 'status_id', 'type_of_mistake', 'ticket_topic', 'sr_practice_id', 'severity', 'priority', 'department_id');
        $userArray = array('technical_account_manager', 'staff_incharge', 'staff_involved_issue');
        $diff_col_val = array();
        if (!empty($model->getDirty())) {
            foreach ($model->getDirty() as $key => $value) {
                if ($key == 'flag_open' || $key == 'open_time' || $key == 'opened_by') {
                    continue;
                }
                $oldValue = $model->getOriginal($key);
                $colname = isset($col_name[$key]) ? $col_name[$key] : $key;
                if (in_array($key, $ArrayYesNo)) {
                    $oldval = ($oldValue == '1') ? 'Yes' : 'No';
                    $newval = ($value == '1') ? 'Yes' : 'No';
                    $oldValue = $oldval;
                    $value = $newval;
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value,
                    ];
                } else if (in_array($key, $ArrayDropdown)) {
                    if ($key == 'ticket_topic') {
                        $tickettopic = config("constant.tickettopic");
                        $oldval = ($oldValue != '') ? $tickettopic[$oldValue] : '';
                        $newval = ($value != '') ? $tickettopic[$value] : '';
                    }if ($key == 'type_of_mistake') {
                        $typeofmistake = config("constant.tickettypeofmistake");
                        $oldval = ($oldValue != '') ? $typeofmistake[$oldValue] : '';
                        $newval = ($value != '') ? $typeofmistake[$value] : '';
                    } if ($key == 'severity') {
                        $severity = config("constant.ticketseverity");
                        $oldval = ($oldValue != '') ? $severity[$oldValue] : '';
                        $newval = ($value != '') ? $severity[$value] : '';
                    } if ($key == 'priority') {
                        $priority = config("constant.ticketpriority");
                        $oldval = ($oldValue != '') ? $priority[$oldValue] : '';
                        $newval = ($value != '') ? $priority[$value] : '';
                    } else if ($key == 'type_id') {
                        $type = \App\Models\Backend\TicketType::get()->pluck("name", "id")->toArray();
                        $oldval = ($oldValue != '') ? $type[$oldValue] : '';
                        $newval = ($value != '') ? $type[$value] : '';
                    } else if ($key == 'status_id') {
                        $status = \App\Models\Backend\TicketStatus::get()->pluck("status", "id")->toArray();
                        $oldval = ($oldValue != '') ? $status[$oldValue] : '';
                        $newval = ($value != '') ? $status[$value] : '';
                    } else if ($key == 'entity_id') {
                        $entityName = \App\Models\Backend\Entity::get()->pluck("trading_name", "id")->toArray();
                        $oldval = ($oldValue != '') ? $entityName[$oldValue] : '';
                        $newval = ($value != '') ? $entityName[$value] : '';
                    } else if ($key == 'team_id') {
                        $teamname = \App\Models\Backend\Team::where("is_active", "1")->get()->pluck("team_name", "id")->toArray();
                        $oldval = ($oldValue != '') ? $teamname[$oldValue] : '';
                        $newval = ($value != '') ? $teamname[$value] : '';
                    } else if ($key == 'sr_practice_id') {
                        $entitySr = \App\Models\Backend\Entity::get()->pluck("name", "id")->toArray();
                        $oldval = ($oldValue != '') ? $entitySr[$oldValue] : '';
                        $newval = ($value != '') ? $entitySr[$value] : '';
                    } else if ($key == 'department_id') {
                        $department = \App\Models\Backend\Department::get()->pluck("department_name", "id")->toArray();
                        $oldval = ($oldValue > 0) ? $department[$oldValue] : '';
                        $newval = ($value > 0) ? $department[$value] : '';
                    }
                    $oldValue = $oldval;
                    $value = $newval;
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value,
                    ];
                } else if (in_array($key, $userArray)) {
                    $old = \App\Models\User::find($oldValue);
                    $new = \App\Models\User::find($value);
                    $oldValue = $old->userfullname;
                    $value = $new->userfullname;
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value,
                    ];
                } else {
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value,
                    ];
                }
            }
            //showArray($diff_col_val);exit;
            return $diff_col_val;
        }
        return $diff_col_val;
    }

    /**
     * update user history details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $user_id,$type
     * @return Illuminate\Http\JsonResponse
     */
    public function history(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'modified_on';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $history = \App\Models\Backend\TicketAudit::with("modifiedBy:id,userfullname")->where("ticket_id", $id);

            if (!isset($history))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The billing history does not exist', ['error' => 'The billing history does not exist']);

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
            return createResponse(config('httpResponse.SUCCESS'), 'Ticket history', ['data' => $history], $pager);
        } catch (\Exception $e) {
            app('log')->error("Could not load billing history : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not load billing history.', ['error' => 'Could not load billing history.']);
        }
    }

    /**
     * Store ticket documents detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public static function uploadDocument($request, $id, $code) {
        try {
            $ticketDetail = \App\Models\Backend\Ticket::find($id);
            if ($ticketDetail->entity_id != '' && $ticketDetail->entity_id > 0) {
                $entityDetail = \App\Models\Backend\Entity::select("code")->find($ticketDetail->entity_id);
                $clientCode = $entityDetail->code;
            } else {
                $clientCode = '01BEFREE';
            }
            $ticketDocumentUploaded = 0;
            $files = $request->file('document_file');
            foreach ($files as $file) {
                $fileName = rand(1, 2000000) . strtotime(date('Y-m-d H:i:s')) . '.' . $file->getClientOriginalExtension();

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

                $mainFolder = $clientCode;
                if (!is_dir($uploadPath . $dir . '/' . $mainFolder))
                    mkdir($uploadPath . $dir . '/' . $mainFolder, 0777, true);

                $location = 'Ticket/' . $code;
                $document_path = $uploadPath . $dir . '/' . $mainFolder . '/' . $location;

                if (!is_dir($document_path))
                    mkdir($document_path, 0777, true);


                if ($file->move($document_path, $fileName)) {
                    $ticketDocumentUploaded = 1;
                    $data['ticket_id'] = $id;
                    $data['document_title'] = $file->getClientOriginalName();
                    $data['document_name'] = $fileName;
                    $data['document_path'] = $commanFolder . $dir . '/' . $mainFolder . '/' . $location . '/';
                    $data['created_by'] = loginUser();
                    $data['created_on'] = date('Y-m-d H:i:s');
                    \App\Models\Backend\TicketDocument::insert($data);
                }
            }
            if ($ticketDocumentUploaded == 1) {
                return $document_path;
            }
            //return createResponse(config('httpResponse.SERVER_ERROR'), 'upload ticket document', ['error' => $ticketDocumentUploaded]);
        } catch (\Exception $e) {
            app('log')->error("Document upload failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not upload ticket document', ['error' => 'Could not upload ticket document']);
        }
    }

    /**
     * Download ticket documents detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function downloadDocument($id) {
        try {
            $ticket = \App\Models\Backend\TicketDocument::find($id);
            if (!$ticket)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The ticket document does not exist', ['error' => 'The ticket document does not exist']);

            //$file = storage_path() . $ticket->document_path . $ticket->document_name;
            if (file_exists(storageEfs($ticket->document_path . $ticket->document_title))) {
                $file = storageEfs() . $ticket->document_path . $ticket->document_title;
            } else {
                $file = storageEfs() . $ticket->document_path . $ticket->document_name;
            }
            return response()->download($file);
        } catch (\Exception $e) {
            app('log')->error("Document download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not download ticket document.', ['error' => 'Could not download ticket document.']);
        }
    }

    /**
     * Download ticket documents detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function downloadZip($id) {
       // try {
            $ticket = \App\Models\Backend\Ticket::find($id);
            if (!$ticket)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The ticket document does not exist', ['error' => 'The ticket document does not exist']);

            $ticketDocument = \App\Models\Backend\TicketDocument::where("ticket_id", $id)->get();

            $zip = new \ZipArchive();
            $storagePath = storageEfs('/uploads/ticketZip');
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0777, true);
            }

            $zipfile = $storagePath . '/TicketDocument' . $ticket->id . '.zip';

            if ($zip->open($zipfile, \ZipArchive::CREATE) === TRUE) {
                // Add File in ZipArchive
                foreach ($ticketDocument as $key => $value) {
                    $fileName = $value['document_name'];
                    $path = storageEfs($value['document_path'] . $value['document_name']);
                    $zip->addFile($path, $fileName);
                }
                // Close ZipArchive
                $zip->close();
            }
            $headers = array('Content-Type' => 'application/octet-stream',
                'Content-disposition: attachment; filename = ' . $zipfile);

            //return response()->download($zipfile)->deleteFileAfterSend(true);
            $response = response()->download($zipfile);
            register_shutdown_function('removeDirWithFiles', $storagePath);
            return $response;
        /*} catch (\Exception $e) {
            app('log')->error("Document download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not download ticket document.', ['error' => 'Could not download ticket document.']);
        }*/
    }

    /**
     * Remove ticket documents detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function removeDocument($id) {
        try {
            $ticketDocumentDetail = \App\Models\Backend\TicketDocument::find($id);
            if (!$ticketDocumentDetail)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The ticket document does not exist', ['error' => 'The ticket document does not exist']);

            $ticketDocumentDetail->is_deleted = 1;
            $ticketDocumentDetail->deleted_by = loginUser();
            $ticketDocumentDetail->deleted_on = date('Y-m-d H:i:s');
            $ticketDocumentDetail->save();

            return createResponse(config('httpResponse.SUCCESS'), 'Document has been deleted successfully', ['message' => 'Document has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Docuement download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete ticket docuement.', ['error' => 'Could not delete ticket document.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Dec 14, 2018 
     * Purpose: discontinue entity history listing
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function ticketCount(Request $request, $id) {
        try {
            $ticket = \App\Models\Backend\Ticket::select('ticket.*', 'utam.userfullname as technicalaccountmanager', 'e.code', 'e.trading_name', 'ucreatedby.userfullname as created_by_name')
                    ->leftjoin('user as ucreatedby', 'ucreatedby.id', '=', 'ticket.created_by')
                    ->leftjoin('user as utam', 'utam.id', '=', 'ticket.technical_account_manager')
                    ->leftjoin('entity as e', 'e.id', '=', 'ticket.entity_id')
                    //->leftjoin('ticket_status as ts', 'ts.id', '=', 'ticket.status_id')
                    ->whereIn('ticket.type_id', [1, 2, 3])
                    ->where('entity_id', $id);
            //leftjoin('ticket_type_master as ty', 'ty.type_id', '=', 'ticket.ticket_type_id')

            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array('problem_our_side' => 'ticket');
                $ticket = search($ticket, $search, $alias);
            }

            if ($request->has('counter') && $request->get('counter') == 1)
                $ticket = $ticket->count();
            else
                $ticket = $ticket->get();

            return createResponse(config('httpResponse.SUCCESS'), 'Ticket has been loaded successfully', ['data' => $ticket]);
        } catch (\Exception $e) {
            app('log')->error("Fetch ticket detail failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch ticket detail.', ['error' => 'Could not fetch ticket detail.']);
        }
    }

}
