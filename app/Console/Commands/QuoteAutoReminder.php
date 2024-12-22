<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class QuoteAutoReminder extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Quote:Autoreminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Quote auto remider to quote assign staff, If quote not come back to BDM stage within 48 hours of quote requested';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle($date = null) {
        try {
            if ($date == null)
                $daysBeforeYesterday = date('Y-m-d', strtotime("-48 hours"));
            else
                $daysBeforeYesterday = date('Y-m-d', strtotime(date('Y-m-d', strtotime($date)) . "-48 hours"));

            $quoteMaster = \App\Models\Backend\QuoteMaster::with('sales_staff_id:id,userfullname')->select('*')->whereIn('stage_id', [2, 3])->whereRaw("DATE_FORMAT(created_on, '%Y-%m-%d') < '" . $daysBeforeYesterday . "'");
            $envelopId = $entityDetail = $quoteMasterId = array();
            if ($quoteMaster->count() > 0) {
                $services = \App\Models\Backend\Services::whereIn('id', [1, 2, 6])->pluck('service_name', 'id')->toArray();
                $quoteMasterIds = $quoteMasterList = array();
                foreach ($quoteMaster->get()->toArray() as $key => $value) {
                    $quoteMasterIds[] = $value['id'];
                    $quoteMasterList[$value['id']] = $value;
                }

                if (!empty($quoteMasterIds)) {
                    $quoteAssignee = \App\Models\Backend\QuoteAssignee::whereIn('quote_master_id', $quoteMasterIds)->whereIn('stage_id', [2, 3])->where('is_active', 1)->get();
                    $serviceWiseAssignee = array();
                    foreach ($quoteAssignee as $keyAssinee => $valueAssinee) {
                        $quoteNotSubmitted = \App\Models\Backend\QuoteLeadAgreedServices::where('quote_master_id', $valueAssinee->quote_master_id)->where('service_id', $valueAssinee->service_id)->where('is_quote_submitted', 2)->count();
                        if ($quoteNotSubmitted == 1)
                            $serviceWiseAssignee[$valueAssinee->quote_master_id][$valueAssinee->service_id] = $valueAssinee->user_id;
                    }

                    if (!empty($serviceWiseAssignee)) {
                        $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('QUOTEREQUEST');
                        $searchArray = array('CLIENT_NAME', 'SERVICE', 'USERNAME', 'SALESPERSON');
                        foreach ($serviceWiseAssignee as $quoteKey => $quoteValue) {
                            foreach ($quoteValue as $serviceKey => $userId) {
                                $serviceName = $services[$serviceKey];
                                $leadName = $quoteMasterList[$quoteKey]['lead_company_name'];
                                $salesPerson = isset($quoteMasterList[$quoteKey]['sales_staff_id']['userfullname']) ? $quoteMasterList[$quoteKey]['sales_staff_id']['userfullname'] : '';
                                $userDetail = \App\Models\User::find($userId);
                                $replaceArray = array($leadName, $serviceName, $userDetail->userfullname, $salesPerson);
                                if ($emailTemplate->is_active == 1) {
                                    $data = array();
                                    $data['to'] = $userDetail->email;
                                    $data['cc'] = $emailTemplate->cc;
                                    $data['bcc'] = $emailTemplate->bcc;
                                    $data['subject'] = str_replace($searchArray, $replaceArray, $emailTemplate->subject);
                                    $data['content'] = str_replace($searchArray, $replaceArray, $emailTemplate->content);
                                    storeMail('', $data);
                                }
                            }
                        }
                    }
                }
            }
        } catch (Exception $ex) {
            $cronName = "Docusign status cron";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
