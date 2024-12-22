<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WelcomeKitController extends Controller {

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
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];
        $userHierarchy = getLoginUserHierarchy();
        $welcomekit = \App\Models\Backend\WelcomeKitDetail::with("createdBy:id,userfullname", "modifiedBy:id,userfullname")->leftjoin("user as u", "u.id", "welcomekit_detail.user_id")
                ->leftjoin("user_hierarchy as uh", "uh.user_id", "u.id")
                ->leftjoin("user_zoho_detail as uz", "uz.EmployeeID", "u.user_bio_id")
                ->leftjoin("hr_location as l", "l.id", "u.location_id")
                ->leftjoin("department as d", "d.id", "uh.department_id")
                ->select("u.user_bio_id", "l.location_name", "uz.Emergency_No_1", "uz.Name_Emergency_Contact_1", "uz.Blood_Group", "u.user_image", "u.user_joining_date", "u.userfullname", "d.department_name", "welcomekit_detail.*");

        $right = checkButtonRights(229, 'all_user');
        if ($right == false && $userHierarchy->designation_id != 7) {
            $welcomekit = $welcomekit->where("welcomekit_detail.user_id", $userHierarchy->user_id);
        }
        if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
            $welcomekit = $welcomekit->leftjoin("user as u", "u.id", "welcomekit_detail.$sortBy");
            $sortBy = 'userfullname';
        }

        if ($request->has('search')) {
            $search = $request->get('search');

            $welcomekit = search($welcomekit, $search);
        }
        //echo getSQL($welcomekit);exit;
        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $welcomekit = $welcomekit->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            $totalRecords = $welcomekit->count();
            $welcomekit = $welcomekit->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);
            //echo $welcomekit->toSql(); die;
            $welcomekit = $welcomekit->get();
            $filteredRecords = count($welcomekit);

            $pager = ['sortBy' => $request->get('sortBy'),
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        if ($request->has('excel') && $request->get('excel') == 1) {
            //format data in array 
            $welcomekitdata = $welcomekit->toArray();
            $column = array();
            $column[] = ['Sr.No', 'User Bio Id', 'User name', 'Join Date','User Image', 'Department', 'Location', 'Emergency Contact No', 'Emergency Contact Name', 'Blood Group', 'Shirt size', 'Welcome kit Status', 'I-Card Status', 'Created on', 'Created By'];
            if (!empty($welcomekitdata)) {
                $columnData = array();
                $i = 1;
                foreach ($welcomekitdata as $data) {
                    $status = config('constant.welcomekitStatus');
                    $columnData[] = $i;
                    $columnData[] = $data['user_bio_id'];
                    $columnData[] = $data['userfullname'];
                    $columnData[] = $data['user_joining_date'];
                    $columnData[] = 'https://befreecrm.com.au:4100'.$data['user_image'];
                    $columnData[] = $data['department_name'];
                    $columnData[] = $data['location_name'];
                    $columnData[] = $data['Emergency_No_1'];
                    $columnData[] = $data['Name_Emergency_Contact_1'];
                    $columnData[] = $data['Blood_Group'];
                    $columnData[] = $data['shirt_size'];
                    $columnData[] = $status[$data['welcome_kit_status']];
                    $columnData[] = $status[$data['i_card_status']];
                    $columnData[] = $data['created_on'];
                    $columnData[] = $data['created_by']['userfullname'];
                    $columnData[] = $data['modified_on'];
                    $columnData[] = $data['modified_by']['userfullname'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }

            return exportExcelsheet($column, 'WelcomeKitList', 'xlsx', 'A1:O1');
        }
        return createResponse(config('httpResponse.SUCCESS'), "Welcome Kit list.", ['data' => $welcomekit], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Welcome Kit listing failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Welcome Kit", ['error' => 'Server error.']);
          } */
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Store shift data
     */

    public function store(Request $request) {
        //try {
        //validate request parameters
        $validator = $this->validateInput($request);
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $checkDetail = \App\Models\Backend\WelcomeKitDetail::where("user_id", $request->get('user_id'));
        // store Welcome Kit  details
        if ($checkDetail->count() == 0) {
            $welcomekit = \App\Models\Backend\WelcomeKitDetail::create([
                        'user_id' => $request->get('user_id'),
                        'shirt_size' => $request->get('shirt_size'),
                        'welcome_kit_status' => 0,
                        'i_card_status' => 0,
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);

            if ($request->has('user_image')) {
                $fileName = uploadUserImage($request, 'user_image', $request->get('user_id'));
                $userImage = \App\Models\User::where("id", $request->get('user_id'))->update(['user_image' => $fileName]);
            }

            $userEmail = \App\Models\Backend\EmailTemplate::getTemplate('WELCOMEKIT');
            $userD = \App\Models\User::where("id", $request->get('user_id'))->select('userfullname')->first();
            $data['to'] = $userEmail->to;
            $data['cc'] = $userEmail->cc;
            $data['bcc'] = $userEmail->bcc;
            $data['subject'] = str_replace(array('USERNAME'), array($userD->userfullname), $userEmail->subject);
            $data['content'] = str_replace(array('USERNAME'), array($userD->userfullname), $userEmail->content);

            //send mail to the client
            storeMail('', $data);
        } else {
            return createResponse(config('httpResponse.SUCCESS'), 'User already entered his detail', ['error' => 'User already entered his detail']);
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Welcome Kit  has been added successfully', ['data' => $welcomekit]);
        /* } catch (\Exception $e) {
          app('log')->error("Shift  creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Welcome Kit ', ['error' => 'Could not add Welcome Kit ']);
          } */
    }

    public function show($id) {
        //try {
        $welcomekit = \App\Models\User::leftjoin("welcomekit_detail as w", "w.user_id", "user.id")
                        ->leftjoin("user_hierarchy as uh", "uh.user_id", "user.id")
                        ->leftjoin("user_zoho_detail as uz", "uz.EmployeeID", "user.user_bio_id")
                        ->leftjoin("hr_location as l", "l.id", "user.location_id")
                        ->leftjoin("department as d", "d.id", "uh.department_id")
                        ->select("user.user_bio_id", "user.user_image", "l.location_name", "uz.Emergency_No_1", "uz.Name_Emergency_Contact_1", "uz.Blood_Group", "user.user_joining_date", "user.userfullname", "d.department_name", "w.*")
                        ->where("user.id", $id)->first();

        if (!isset($welcomekit))
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Welocme Kit does not exist', ['error' => 'The Welcome Kit detail does not exist']);


        //send shift information
        return createResponse(config('httpResponse.SUCCESS'), 'Welcome Kit  data', ['data' => $welcomekit]);
        /* } catch (\Exception $e) {
          app('log')->error("Welcome Kit details api failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Welcome Kit detail.', ['error' => 'Could not get Welcome Kit detail.']);
          } */
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Show shift data
     */

    public function update(Request $request, $id) {
        //try {
            $validator = $validator = app('validator')->make($request->all(), [
                'shirt_size' => 'in:S,M,L,XL,XXL,XXXL'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $welcomekit = \App\Models\Backend\WelcomeKitDetail::find($id);

            if (!$welcomekit)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Welcome Kit does not exist', ['error' => 'The Welcome Kit does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['shirt_size', 'welcome_kit_status', 'i_card_status'], $request);
            if ($request->has('user_image')) {
                $fileName = uploadUserImage($request, 'user_image', $welcomekit->user_id);
                $userImage = \App\Models\User::where("id", $welcomekit->user_id)->update(['user_image' => $fileName]);
            }
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = app('auth')->guard()->id();
            //update the details
            $welcomekit->update($updateData);
            if($request->input('welcome_kit_status') == 1){
               $userEmail = \App\Models\Backend\EmailTemplate::getTemplate('WELCOMEKITDISPATCH');
            $userD = \App\Models\User::where("id", $welcomekit->user_id)->select('email','userfullname')->first();
            $data['to'] = $userD->email;
            $data['cc'] = $userEmail->cc;
            $data['bcc'] = $userEmail->bcc;
            $data['subject'] = $userEmail->subject;
            $data['content'] = str_replace(array('USERNAME'), array($userD->userfullname), $userEmail->content);
             storeMail('', $data);
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Welcome Kit has been updated successfully', ['message' => 'Welcome Kit has been updated successfully']);
       /* } catch (\Exception $e) {
            app('log')->error("Shift updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Welcome Kit details.', ['error' => 'Could not update Welcome Kit details.']);
        }*/
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Make shift In active.
     */

    public function destroy(Request $request, $id) {
        try {
            $welcomekit = \App\Models\Backend\WelcomeKitDetail::find($id);
            if (!$welcomekit)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Welcome Kit does not exist', ['error' => 'The welcome kit does not exist']);

            // Filter the fields which need to be updated
            //$welcomekit->is_delete = 1;
            $welcomekit->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Welcome Kit has been deleted successfully', ['message' => 'Welcome Kit has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted Welcome Kit details.', ['error' => 'Could not deleted Welcome Kit details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate user input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'user_id' => 'required',
            'shirt_size' => 'required|in:S,M,L,XL,XXL,XXXL'
                ], []);
        return $validator;
    }
    
    public function downloadZip($id) {
       // try {
           
            $ticketDocument = \App\Models\Backend\WelcomeKitDocument::where("is_latest", "1")->get();

            $zip = new \ZipArchive();
            $storagePath = storageEfs('/uploads/welcomekit');
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0777, true);
            }

            $zipfile = $storagePath . '/welcomekitReport'.date('d-m-Y').'.zip';
            
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

}
