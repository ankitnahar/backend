<?php

namespace App\Http\Controllers\Backend\Query;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade as PDF;
use DB;

class QuerySendController extends Controller {

    public function index(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'query_id' => 'required'], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $queryId = $request->get('query_id');


        $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", "QUERYCLIENT")->where("is_active", "1")->first();
        if (isset($emailTemplate)) {
            $getContactInfo = \App\Models\Backend\Query::contactMailData()->where("query.id", $queryId)->first();
            $billing = \App\Models\Backend\Billing::where("entity_id", $getContactInfo->entity_id)->first();
           
                $contactInfo = \App\Models\Backend\Contact::leftjoin("entity as e", "e.id", "contact.entity_id")
                        ->select("contact.to as to_email", "contact.cc as cc_email", "contact.other_email as bcc", "contact.first_name", "e.trading_name")
                        ->where("contact.entity_id", $getContactInfo->entity_id)
                        ->where("contact.is_display_bk_checklist", "1")
                        ->where('contact.is_archived', "=", "0");
                if ($contactInfo->count() == 0) {
                    return createResponse(config('httpResponse.UNPROCESSED'), config('Please Update Contact info where BK checklist YES'), ['error' => "Please Update Contact info where BK checklist YES"]);
                }
                $contactInfo = $contactInfo->first();
                $data['from_email'] = 'no-reply@befree.com.au';
                $data['to'] = $contactInfo->to_email;
                $data['cc'] = $contactInfo->cc_email;
                $data['bcc'] = $contactInfo->bcc;
            
            $period = date('d-M-Y', strtotime($getContactInfo->start_period)) . ' To ' . date('d-M-Y', strtotime($getContactInfo->end_period));
            $subject = str_replace(array("CLIENTNAME", "SUBJECT"), array($contactInfo->trading_name, $getContactInfo->subject), $emailTemplate->subject);
            $signature = signatureTemplate(3, $getContactInfo->entity_id);
            $content = str_replace(array("CONTACTNAME", "SUBJECT", "LINK", "PERIOD", "SIGNATURE"), array($contactInfo->first_name, $getContactInfo->subject, '<a href="https://client.befree.com.au">Click here for login</a>', $period, $signature), $emailTemplate->content);
            $data['subject'] = $subject;
            $data['content'] = $content;
        }


        return createResponse(config('httpResponse.SUCCESS'), "Query Contact list.", ['data' => $data]);
        /* } catch (\Exception $e) {
          app('log')->error("Query Preview failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not preview query', ['error' => 'Could not preview query']);
          } */
    }

    /**
     * Store Query details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        // try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'query_id' => 'required',
            'to' => 'required|email_array',
            'cc' => 'email_array',
            'bcc' => 'email_array',
            'subject' => 'required',
            'content' => 'required',
            'stage_id' => 'required'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $query = \App\Models\Backend\Query::find($id);
        if (!$query) {
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Query Description does not exist', ['error' => 'The Query Description does not exist']);
        }
        $billing = \App\Models\Backend\Billing::where("entity_id", $query->entity_id)->first();
        
            $dataArray = [
                'query_id' => $request->input('query_id'),
                'from_email' => $request->input('from_email'),
                'to' => trim($request->input('to')),
                'cc' => trim($request->input('cc')),
                'bcc' => trim($request->input('bcc')),
                'subject' => $request->input('subject'),
                'content' => $request->input('content'),
                'created_on' => date("Y-m-d H:i:s"),
                'created_by' => loginUser()
            ];

            $data['to'] = str_replace(' ', '', $request->input('to'));
            $data['from'] = $request->input('from_email');
            $data['cc'] = str_replace(' ', '', $request->input('cc'));
            $data['bcc'] = str_replace(' ', '', $request->input('bcc'));
            $data['content'] = $request->input('content');
            $data['subject'] = $request->input('subject');
        
        //send mail to the client
        storeMail($request, $data);
        //update reminder_date

        $reminder_days = $query->reminder;
        //if($reminder_days > 0){
        $nextremiderDate = date('Y-m-d', strtotime(date("Y-m-d") . "+" . $reminder_days . " days"));
        \App\Models\Backend\Query::where("id", $request->input('query_id'))->update(
                [
                    'reminder_date' => $nextremiderDate,
                    "stage_id" => 5]);
        //}
        //change worksheet status 
        \App\Models\Backend\Worksheet::where("id", $query->worksheet_id)->update(["status_id" => '11']);
        $worksheetLog = \App\Models\Backend\WorksheetLog::create(['worksheet_id' => $query->worksheet_id,
                    'status_id' => 11,
                    'created_by' => app('auth')->guard()->id(),
                    'created_on' => date('Y-m-d H:i:s')]);


        $queryTemplate = \App\Models\Backend\QueryTemplate::where("query_id", $request->input('query_id'))->first();
        if (isset($queryTemplate->id)) {
            \App\Models\Backend\QueryTemplate::where("id", $queryTemplate->id)->update($dataArray);
        } else {
            $queryTemplate = \App\Models\Backend\QueryTemplate::create($dataArray);
        }

        $queryLog = \App\Models\Backend\QueryLog::addLog($query->id, 5);

        return createResponse(config('httpResponse.SUCCESS'), 'Query has been sent to the client successfully', ['data' => $queryTemplate]);
        /* } catch (\Exception $e) {
          app('log')->error("Query send to the client failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not send query to the client', ['error' => 'Could not send query to the client']);
          } */
    }

    /**
     * Query call listing
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function callList(Request $request, $id) {
        try {
            $getCallDetail = \App\Models\Backend\QueryCall::callListData($id);
            $getContactInfo = $getCallDetail->get();

            return createResponse(config('httpResponse.SUCCESS'), "Query Call list.", ['data' => $getContactInfo]);
        } catch (\Exception $e) {
            app('log')->error("Query Call list failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get query call list', ['error' => 'Could not get query call list']);
        }
    }

}

?>