<?php

namespace App\Http\Controllers\Backend\Contact;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\Contact;
use DB;

class ContactController extends Controller {

    /**
     * Get contact detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
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
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'contact.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        $contact = \App\Models\Backend\Contact::contactData();
        $right = checkButtonRights(23, 'all_entity');
        if ($right == false) {
            //check entity allocation
            $entity_ids = checkUserClientAllocation(app('auth')->guard()->id());
            if (is_array($entity_ids))
                $contact = $contact->whereRaw("ed.id IN(". implode(",",$entity_ids).")");
        }
        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array('contact_person' => 'contact', 'service_id' => 'contact', 'entity_id' => 'contact',"parent_id" => "ed", 'mobile_no' => 'contact');
            $contact = search($contact, $search, $alias);
        }

        // for relation ship sorting
        if ($sortBy == 'created_by' || $sortBy == 'archived_by') {
            $contact = $contact->leftjoin("user as u", "u.id", "contact.$sortBy");
            $sortBy = 'userfullname';
        }

        $contact = $contact->where('ed.discontinue_stage', "!=", "2")->groupBy('contact.id');
        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $contact = $contact->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            $totalRecords = $contact->get()->count();

            $contact = $contact->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $contact = $contact->get();

            $filteredRecords = count($contact);

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
            $data = $contact->toArray();
            $column = array();
            $column[] = ['Sr.No','Client Code', 'Parent Trading Name','Entity Name', 'Billing Name', 'Trading Name', 'Service', 'First Name', 'Contact Person', 'From Email Address', 'Position', 'To Email', 'CC Email', 'Send Newsletter', 'BK Checklist', 'Mobile Phone', 'Office Phone', 'Technical Account Manager','TL Name', 'Is Feedback Contact', 'Feedback Email', 'Actions', 'Archived Reason', 'Archived by', 'Archived On', 'Client Status'];
            $entitydiscontinuestage = config('constant.entitydiscontinuestage');
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $position = config('constant.position');
                    $columnData[] = $i;
                    $columnData[] = $data['code'];
                    $columnData[] = $data['parent_name'];
                    $columnData[] = $data['name'];
                    $columnData[] = $data['billing_name'];
                    $columnData[] = $data['trading_name'];
                    $columnData[] = $data['service_name'];
                    $columnData[] = $data['first_name'];
                    $columnData[] = $data['contact_person'];
                    $columnData[] = $data['from_email'];
                    $columnData[] = isset($position[$data['contact_position_id']]) ? $position[$data['contact_position_id']] : '';
                    $columnData[] = ($data['to'] != '') ? $data['to'] : '';
                    $columnData[] = ($data['cc'] != '') ? $data['cc'] : '';
                    $columnData[] = ($data['send_newsletter'] != 0) ? 'Yes' : 'No';
                    $columnData[] = ($data['is_display_bk_checklist'] != 0) ? 'Yes' : 'No';
                    $columnData[] = ($data['mobile_no'] != '') ? $data['mobile_no'] : '';
                    $columnData[] = ($data['office_no'] != '') ? $data['office_no'] : '';
                    $columnData[] = $data['tam_name'];
                    $columnData[] = $data['tl_name'];
                    $columnData[] = ($data['is_feedback_contact'] != 0) ? 'Yes' : 'No';
                    $columnData[] = $data['feedback_email'];
                    $columnData[] = $data['is_archived'];
                    $columnData[] = $data['archived_reason'];
                    $columnData[] = $data['archived_by']['archived_by'];
                    $columnData[] = $data['archived_on'];
                    $columnData[] = $entitydiscontinuestage[$data['discontinue_stage']];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'ContactList', 'xlsx', 'A1:V1');
        }


        return createResponse(config('httpResponse.SUCCESS'), "Contact list.", ['data' => $contact], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Contact listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing contact", ['error' => 'Server error.']);
          } */
    }

    /**
     * Store contact details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric',
                'service_id' => 'required|array',
                'contact_position_id' => 'required|numeric|min:1',
                'contact_person' => 'required|alpha_spaces',
                'to' => 'required|email_array',
                'cc' => 'email_array',
                'bcc' => 'email_array',
                'other_email' => 'email_array',
                'is_display_bk_checklist' => 'required|numeric|in:0,1',
                'send_newsletter' => 'required|numeric|in:0,1',
                'is_feedback_contact' => 'required|numeric|in:0,1,2',
                'feedback_email' => 'required_if:is_feedback_contact,1|email',
                'from_email' => 'required_if:is_display_bk_checklist,1|email_array'
                    ], [
                'to.email_array' => "Please enter valid Email Id",
                'cc.email_array' => "Please enter valid Email Id",
                'other_email.email_array' => "Please enter valid Email Id",
            ]);


            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);
            //Get current user detail
            $loginUser = loginUser();
            // check feedback contact 
            if ($request->input('is_feedback_contact') == 1) {
                $checkContact = self::checkFeedbackContact($request->input('entity_id'));
                if ($checkContact > 0) {
                    return createResponse(config('httpResponse.UNPROCESSED'), 'you can not add two contact with feedback', ['error' => 'you can not add two contact with feedback']);
                }
            }
            // store client details
            $contact = Contact::create([
                        'entity_id' => $request->input('entity_id'),
                        'service_id' => implode(",", $request->input('service_id')),
                        'contact_position_id' => $request->input('contact_position_id'),
                        'contact_person' => $request->input('contact_person'),
                        'first_name' => $request->input('first_name'),
                        'from_email' => $request->input('from_email'),
                        'director_number' => $request->input('director_number'),
                        'to' => $request->input('to'),
                        'from_name' => $request->input('from_name'),
                        'bcc' => $request->input('bcc'),
                        'is_display_bk_checklist' => $request->input('is_display_bk_checklist'),
                        //'is_login' => $request->input('is_login'),
                        'send_newsletter' => $request->input('send_newsletter'),
                        'is_feedback_contact' => $request->input('is_feedback_contact'),
                        'feedback_email' => $request->input('feedback_email'),
                        'cc' => ($request->has('cc')) ? $request->input('cc') : '',
                        'other_email' => ($request->has('other_email')) ? $request->input('other_email') : '',
                        'mobile_no' => ($request->has('mobile_no')) ? $request->input('mobile_no') : '',
                        'office_no' => ($request->has('office_no')) ? $request->input('office_no') : '',
                        'fax_no' => ($request->has('fax_no')) ? $request->input('fax_no') : '',
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser]
            );
            
            return createResponse(config('httpResponse.SUCCESS'), 'Contact has been added successfully', ['data' => $contact]);
        } catch (\Exception $e) {
            app('log')->error("Contact creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add contact', ['error' => 'Could not add contact']);
        }
    }

    public static function checkFeedbackContact($entityId) {
        return Contact::where("entity_id", $entityId)->where("is_feedback_contact", "1")->where("is_archived", "0")->count();
    }

    /**
     * get particular contact details
     *
     * @param  int  $id   //contact id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $contact = Contact::contactData()->whereIn('ed.discontinue_stage', [0, 1])->where("contact.id", $id)->groupBy('contact.id')->get();

            if (!isset($contact))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Contact does not exist', ['error' => 'The Contact does not exist']);

            //send contact information
            return createResponse(config('httpResponse.SUCCESS'), 'Contact data', ['data' => $contact]);
        } catch (\Exception $e) {
            app('log')->error("Contact details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get contact.', ['error' => 'Could not get contact.']);
        }
    }

    /**
     * update contact details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // contact id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'contact_position_id' => 'numeric|min:1',
            'contact_person' => 'required',
            'to' => 'email_array',
            'cc' => 'email_array',
            'bcc' => 'email_array',
            'other_email' => 'email_array',
            'is_display_bk_checklist' => 'numeric|in:0,1',
            //'is_login' => 'numeric|in:0,1',
            'send_newsletter' => 'numeric|in:0,1',
            'is_feedback_contact' => 'numeric|in:0,1',
            'feedback_email' => 'required_if:is_feedback_contact,1|email',
            'from_email' => 'required_if:is_display_bk_checklist,1|email_array'
                ], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $contact = Contact::find($id);

        if (!$contact)
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Contact does not exist', ['error' => 'The Contact does not exist']);

        $updateData = array();
        // Filter the fields which need to be updated
        $loginUser = loginUser();
        // check feedback contact 
        if ($request->input('is_feedback_contact') != 0) {
            $checkContact = self::checkFeedbackContact($request->input('entity_id'));
            if ($checkContact > 1) {
                return createResponse(config('httpResponse.UNPROCESSED'), 'you can not add two contact with feedback', ['error' => 'you can not add two contact with feedback']);
            }
        }
        $updateData = filterFields(['contact_position_id', 'contact_person', 'first_name', 'from_email', 'from_name', 'to', 'bcc', 'cc', 'other_email', 'is_display_bk_checklist', 'feedback_email', 'is_feedback_contact', 'send_newsletter',
            'mobile_no', 'office_no', 'fax_no','director_number'], $request);
        $updateData['service_id'] = ($request->has('service_id')) ? implode(",", $request->input('service_id')) : $contact->service_id;

        //update the details
        $contact->update($updateData);

        return createResponse(config('httpResponse.SUCCESS'), 'Contact has been updated successfully', ['message' => 'Contact has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Contact updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update contact details.', ['error' => 'Could not update contact details.']);
          } */
    }

    /**
     * archive contact details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // contact id
     * @return Illuminate\Http\JsonResponse
     */
    public function archive(Request $request, $id) {
        try {
            //check button right
            $right = checkButtonRights(23, 'update_archive');
            if ($right) {
                $validator = app('validator')->make($request->all(), [
                    'archived_reason' => 'required'
                        ], []);

                // If validation fails then return error response
                if ($validator->fails())
                    return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

                $contactArchive = Contact::find($id);

                if (!$contactArchive)
                    return createResponse(config('httpResponse.NOT_FOUND'), 'The Contact does not exist', ['error' => 'The Contact does not exist']);

                if ($contactArchive->is_archived != '0')
                    return createResponse(config('httpResponse.NOT_FOUND'), 'The Contact is already archived', ['error' => 'The Contact is already archived']);

                $updateData = array();
                // Filter the fields which need to be updated
                $loginUser = loginUser();
                $updateData = filterFields(['archived_reason'], $request);
                $updateData['is_archived'] = "1";
                $updateData['archived_on'] = date('Y-m-d H:i:s');
                $updateData['archived_by'] = $loginUser;
                //update the details
                $contactArchive->update($updateData);

                $client = \App\Models\Backend\Client::where("contact_id", $id)->first();
                \App\Models\Backend\Client::whereRaw("contact_id = $id OR parent_id =$client->id")->update(['is_active' => 0]);

                return createResponse(config('httpResponse.SUCCESS'), 'Contact has been archived successfully', ['message' => 'Contact has been archived successfully']);
            }else {
                return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
            }
        } catch (\Exception $e) {
            app('log')->error("Contact archived failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not archived contact details.', ['error' => 'Could not archived contact details.']);
        }
    }

    /*
     * Created By - Pankaj
     * Created On - 25/04/2018
     * Common function for save history
     */

    public static function saveHistory($model, $col_name) {
        $ArrayYesNo = array('send_newsletter', 'is_display_bk_checklist', 'is_display_information', 'is_feedback_contact');
        $ArrayDropdown = array('contact_position_id', 'service_id','is_feedback_contact');
        if (!empty($model->getDirty())) {
            foreach ($model->getDirty() as $key => $value) {
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
                    if ($key == 'contact_position_id') {
                        $position = config('constant.position');
                        $oldval = ($oldValue != '') ? $position[$oldValue] : '';
                        $newval = ($value != '') ? $position[$value] : '';
                    } else if ($key == 'service_id') {
                        $old_name = \App\Models\Backend\Services::whereRaw("id IN (" . $oldValue . ")")->select(DB::raw('group_concat(service_name) as service_name'))->first();
                        $new_name = \App\Models\Backend\Services::whereRaw("id IN (" . $value . ")")->select(DB::raw('group_concat(service_name) as service_name'))->first();
                        $oldval = $old_name->service_name;
                        $newval = $new_name->service_name;
                    } else if ($key == 'is_feedback_contact') {
                        $feedback = config('constant.feedback');
                        $oldval = ($oldValue != '') ? $feedback[$oldValue] : '';
                        $newval = ($value != '') ? $feedback[$value] : '';
                    }
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldval,
                        'new_value' => $newval,
                    ];
                } else {
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value,
                    ];
                }
            }
            return $diff_col_val;
        }
        return $diff_col_val;
    }

    public function getRelatedEntityList(Request $request, $entity_id) {
        try {
            $relatedEntity = \App\Models\Backend\Billing::getRelatedEntity($entity_id);
            if (empty($relatedEntity)) {
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Related Entity does not exist', ['error' => 'The Related Entity does not exist']);
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Related Entity list', ['data' => $relatedEntity]);
        } catch (\Exception $e) {
            app('log')->error("Related Entity data details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Related Entity data.', ['error' => 'Could not get Related Entity data.']);
        }
    }

    public function copyRelatedEntity(Request $request, $id) {
        try {
            //check button right
            $right = checkButtonRights(23, 'copy_contact');
            if ($right) {
                $validator = app('validator')->make($request->all(), [
                    'entity_id' => 'required'
                        ], []);

                // If validation fails then return error response
                if ($validator->fails())
                    return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

                $contact = Contact::find($id);

                if (!isset($contact))
                    return createResponse(config('httpResponse.NOT_FOUND'), 'The Contact does not exist', ['error' => 'The Contact does not exist']);

                if ($contact->is_archived != '0')
                    return createResponse(config('httpResponse.NOT_FOUND'), 'The Contact is archived', ['error' => 'The Contact is archived']);

                $loginUser = loginUser();
                $entity_id = explode(",", $request->input('entity_id'));


                for ($i = 0; $i < count($entity_id); $i++) {
                    $contactEntity[] = [
                        'entity_id' => $entity_id[$i],
                        'service_id' => $contact->service_id,
                        'contact_position_id' => $contact->contact_position_id,
                        'contact_person' => $contact->contact_person,
                        'to' => $contact->to,
                        'is_display_bk_checklist' => $contact->is_display_bk_checklist,
                        'is_display_information' => $contact->is_display_information,
                        'send_newsletter' => $contact->send_newsletter,
                        'cc' => $contact->cc,
                        'other_email' => $contact->other_email,
                        'mobile_no' => $contact->mobile_no,
                        'office_no' => $contact->office_no,
                        'fax_no' => $contact->fax_no,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser];
                }
                $contact = \App\Models\Backend\Contact::insert($contactEntity);
                //send contact information
                return createResponse(config('httpResponse.SUCCESS'), 'All Contact copy with related entity data', ['data' => 'All Contact copy with related entity data']);
            } else {
                return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
            }
        } catch (\Exception $e) {
            app('log')->error("Contact details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get contact.', ['error' => 'Could not get contact.']);
        }
    }

    /**
     * update contact history details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $contact_id
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

            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'modified_on';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $contactHistory = \App\Models\Backend\ContactAudit::with('modifiedBy:userfullname,id')->where("contact_id", $id);

            if (!isset($contactHistory))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The contact history does not exist', ['error' => 'The contact history does not exist']);

            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $contactHistory = search($contactHistory, $search);
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $contactHistory = $contactHistory->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $contactHistory->count();

                $contactHistory = $contactHistory->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $contactHistory = $contactHistory->get();

                $filteredRecords = count($contactHistory);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Contact history data', ['data' => $contactHistory], $pager);
        } catch (\Exception $e) {
            app('log')->error("Contact history api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get contact history history.', ['error' => 'Could not get contact history history.']);
        }
    }

    

}
