<?php

namespace App\Http\Controllers\Backend\Contact;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\Contact;
use DB;

class ContactNewsletterController extends Controller {

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
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'newsletter_email.email';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        self::storeContact();        
        $contact = DB::table('newsletter_email')->leftjoin("entity as e", "e.id", "newsletter_email.entity_id")
                ->leftjoin("contact_position as cp", "cp.id", "newsletter_email.contact_position_id")
                ->select('e.trading_name', 'newsletter_email.email', 'cp.position');

        if ($request->has('search')) {
            $search = $request->get('search');
            $contact = search($contact, $search);
        }

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
            $data = $contact;
            $column = array();
            $column[] = ['Trading Name', 'Contact Position', 'Email'];
            if (!empty($data)) {

                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $columnData[] = $data->trading_name;
                    $columnData[] = $data->position;
                    $columnData[] = trim($data->email);
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'NewsletterList', 'xlsx', 'A1:C1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "Newsletter list.", ['data' => $contact], $pager);
        /*  } catch (\Exception $e) {
          app('log')->error("Newsletter listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing newsletter", ['error' => 'Server error.']);
          } */
    }

    public static function storeContact() {

        $contact = \App\Models\Backend\Contact::newsletterData();
        $contact = $contact->get()->toArray();
       
        $newsletterEmail = $newsletterEmailList = array();
        foreach ($contact as $data) {
            $emailistTo = $emailistCC = $emailistBCC = array();
            if ($data['to'] != '') {
                $emailistTo = explode(",", $data['to']);
            }
            if ($data['cc'] != '') {
                $emailistCC = explode(",", $data['cc']);
                if (!empty($emailistTo)) {
                    $emailTo = array_merge($emailistTo, $emailistCC);
                }
            } else if (!empty($emailistTo)) {
                $emailTo = $emailistTo;
            }
            if ($data['other_email'] != '') {
                $emailistBCC = explode(",", $data['other_email']);
                if (!empty($emailTo)) {
                    $emailTo = array_merge($emailistTo, $emailistBCC);
                }
            }
            if (!empty($emailTo)) {
                $newsArrayMerge = array_unique($emailTo);
            }
            $newsletterEmail['email'] = $newsArrayMerge;
            $newsletterEmail['entity_id'] = $data['entity_id'];
            $newsletterEmail['contact_position_id'] = $data['contact_position_id'];
            $news[] = $newsletterEmail;
        }
        //showArray($news);exit;
        // $newsletterEmailList = array_values(array_unique($newsletterEmailList));
        $unscribeEmails = \App\Models\Backend\NewsletterUnsubscribe::get();
        foreach ($unscribeEmails as $unemail) {
            $unscribeEmail[] = $unemail->email;
        }
        $addinList['entity_id'] = 385;
        $addinList['position'] = 1;
        $addinList['email'] = array('akshay.m@befree.com.au',
            'darshan.d@befree.com.au',
            'jaivik.s@befree.com.au',
            'vinny.g@befree.com.au',
            'hardik.c@befree.com.au',
            'rahul.p@befree.com.au',
            'darshan.t@befree.com.au',
            'billing@befree.com.au',
            'dilip.p@befree.com.au',
            'kevin@befree.com.au',
            'sandeep.j@befree.com.au',
            'rebecca@befree.com.au',
            'garry@befree.com.au');
        $news = array_merge($news, $addinList);
        //showArray($news);exit;
        DB::table('newsletter_email')->delete();
        foreach ($news as $emailList) {
            if (!empty($emailList['email'])) {
                for ($j = 0; $j < count($emailList['email']); $j++) {
                    if (isset($emailList['email'][$j]) && $emailList['email'][$j] != "") {
                        if (in_array($emailList['email'][$j], $unscribeEmail)) {
                            continue;
                        }
                        //$newsEmailList[] = $newsletterEmail[$j];
                        $insertArray[] = array('entity_id' => $emailList['entity_id'],
                            'contact_position_id' => $emailList['contact_position_id'],
                            'email' => $emailList['email'][$j]);
                    }
                }
            }
        }
        DB::table('newsletter_email')->insert($insertArray);
        DB::select('DELETE t1 FROM newsletter_email t1
INNER JOIN newsletter_email t2 
WHERE 
    t1.id < t2.id AND 
    t1.email = t2.email');
    }

    public function moveToArchive(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'email' => 'required|email',
                    ], []);

            if ($validator->fails()) { // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
            }

            $newsEmail = \App\Models\Backend\NewsletterUnsubscribe::where("email", $request->input('email'));
            if ($newsEmail->count() == 0) {
                \App\Models\Backend\NewsletterUnsubscribe::create(["email" => $request->input('email')]);
            }
            return createResponse(config('httpResponse.SUCCESS'), "Email Unsubscribe sucessfully", ['data' => 'Email Unsubscribe sucessfully']);
        } catch (\Exception $e) {
            app('log')->error("Email Unsubscribe failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while add email on unscuscribe", ['error' => 'Server error.']);
        }
    }

}
