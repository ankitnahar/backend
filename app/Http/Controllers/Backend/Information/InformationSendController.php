<?php

namespace App\Http\Controllers\Backend\Information;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade as PDF;
use DB;

class InformationSendController extends Controller {

    public function index(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'information_id' => 'required'], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $informationId = $request->get('information_id');

        $getContactInfo = \App\Models\Backend\Information::contactMailData()->where("information.id", $informationId)->first();

        $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", "INFOCLIENT")->where("is_active", "1")->first();
        if (isset($emailTemplate)) {
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
            $signature = signatureTemplate(3, $getContactInfo->entity_id);
            $subject = str_replace(array("CLIENTNAME", "SUBJECT"), array($contactInfo->trading_name, $getContactInfo->subject), $emailTemplate->subject);
            $content = str_replace(array("CONTACTNAME", "SUBJECT", "LINK", "SIGNATURE"), array($contactInfo->first_name, $getContactInfo->subject, '<a href="https://client.befree.com.au">Click here for login</a>', $signature), $emailTemplate->content);
            $data['subject'] = $subject;
            $data['content'] = $content;
        }


        return createResponse(config('httpResponse.SUCCESS'), "Information Contact list.", ['data' => $data]);
        /* } catch (\Exception $e) {
          app('log')->error("Information Preview failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not preview information', ['error' => 'Could not preview information']);
          } */
    }

    /**
     * Store Information details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'information_id' => 'required',
                'to' => 'required|email_array',
                'cc' => 'email_array',
                'bcc' => 'email_array',
                'subject' => 'required',
                'content' => 'required',
                'stage_id' => 'required'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $information = \App\Models\Backend\Information::find($id);
            if (!$information) {
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Information Description does not exist', ['error' => 'The Information Description does not exist']);
            }

            $dataArray = [
                'information_id' => $request->input('information_id'),
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
            $reminder_days = $information->reminder;
            \App\Models\Backend\Information::where("id", $request->input('information_id'))->update(
                    [
                        'reminder_date' => date('Y-m-d', strtotime("+" . $reminder_days . " days", strtotime(date("Y-m-d")))),
                        "stage_id" => 5]);
            $informationTemplate = \App\Models\Backend\InformationTemplate::where("information_id", $request->input('information_id'))->first();
            if (isset($informationTemplate->id)) {
                \App\Models\Backend\InformationTemplate::where("id", $informationTemplate->id)->update($dataArray);
            } else {
                $informationTemplate = \App\Models\Backend\InformationTemplate::create($dataArray);
            }

            $informationLog = \App\Models\Backend\InformationLog::addLog($information->id, 5, loginUser());

            return createResponse(config('httpResponse.SUCCESS'), 'Information has been sent to the client successfully', ['data' => $informationTemplate]);
        } catch (\Exception $e) {
            app('log')->error("Information send to the client failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not send information to the client', ['error' => 'Could not send information to the client']);
        }
    }

    /**
     * Information call listing
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function callList(Request $request, $id) {
        try {
            $getCallDetail = \App\Models\Backend\InformationCall::callListData($id);
            $getContactInfo = $getCallDetail->get();

            return createResponse(config('httpResponse.SUCCESS'), "Information Call list.", ['data' => $getContactInfo]);
        } catch (\Exception $e) {
            app('log')->error("Information Call list failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get information call list', ['error' => 'Could not get information call list']);
        }
    }

}

?>