<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class QuoteFuturequoteReminder extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Quote:Futurequote';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reminder for future quote';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $today = strtotime(date('Y-m-d'));
            if ($today == strtotime(date('Y-08-15'))) {
                $quoteMaster = \App\Models\Backend\QuoteMaster::where('stage_id', 9)->where('is_future_quote', 1)->get();
                if (!empty($quoteMaster)) {
                    $defaultAssignee = \App\Models\Backend\Constant::select('constant_value')->where('constant_name', 'QUOTE_DEFAULT_ASSIGNEE')->first();
                    $assigneeArray = \GuzzleHttp\json_decode($defaultAssignee->constant_value);
                    $taxationAssignee = $assigneeArray['taxation'];
                    if (isset($taxationAssignee))
                        $userDetail = \App\Models\User::find($taxationAssignee);
                    else
                        return;

                    foreach ($quoteMaster as $key => $value) {
                        //$quoteAssignee = \App\Models\Backend\QuoteAssignee::where('quote_master_id', $value->id)->where('service_id', 6)->get();
                        $checkBookkeepingDoneByUs = 0;
                        if (isset($value->entity_id) && $value->entity_id != 0) {
                            $checkBookkeepingDoneByUs = \App\Models\Backend\BillingServices::where('entity_id', $value->entity_id)->where('billing_services.is_active', 1)->where('is_updated', 1)->where('is_latest', 1)->whereIn('service_id', [1])->count();
                        }

                        $stageId = 2;
                        if ($value->service_id == 6 && $value->is_new_entity == 2 && $checkBookkeepingDoneByUs != 0)
                            $stageId = 11;

                        $response = divisionHead($value);
                        $divisionHeadName = 'Division Head';
                        $divisionHeadEmail = '';
                        if (isset($response['userfullname']) && $response['userfullname'] != '')
                            $divisionHeadName = $response['userfullname'];

                        if (isset($response['email']) && $response['email'] != '')
                            $divisionHeadEmail = $response['email'];

                        \App\Models\Backend\QuoteMaster::where('id', $value->id)->update(['stage_id' => $stageId]);
                        $updateServiceData['is_quote_requested'] = 1;
                        $updateServiceData['is_quote_submitted'] = 2;
                        \App\Models\Backend\QuoteLeadAgreedServices::where('quote_master_id', $value->id)->where('service_id', 6)->update($updateServiceData);

                        $quoteStageLog['quote_master_id'] = $value->id;
                        $quoteStageLog['stage_id'] = $stageId;
                        $quoteStageLog['is_stage_knockback'] = 0;
                        $quoteStageLog['created_by'] = 1;
                        $quoteStageLog['created_on'] = date('Y-m-d H:i:s');
                        \App\Models\Backend\QuoteStageLog::insert($quoteStageLog);

                        $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('FUTURETOREQUEST');
                        if ($emailTemplate->is_active == 1) {
                            $data['to'] = $divisionHeadEmail;
                            $data['cc'] = $emailTemplate->cc;
                            $data['bcc'] = $emailTemplate->bcc;
                            $find = array('CLIENTNAME', 'SERVICENAME', 'SALESPERSON', 'STAFFNAME');
                            $replace = array($quoteMaster->lead_company_name, 'Taxation', $salesPerson, $divisionHeadName);
                            $data['subject'] = str_replace($find, $replace, $emailTemplate->subject);
                            $data['content'] = str_replace($find, $replace, $emailTemplate->content);
                            storeMail($request, $data);
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            $cronName = "Future quote";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
