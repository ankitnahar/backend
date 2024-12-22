<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class PriorRecurringCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring:prior';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prior Recurring mail send to staff';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle($date = null) {
        try {
            if ($date != '') {
                $date = date('Y-m-d', strtotime($date));
            } else {
                $date = date('Y-m-d');
            }
            $RecurringDate = date('Y-m-d', strtotime($date."+5 days"));
            //check recurring for bk and payroll
            $BKautoRecurring = \App\Models\Backend\BillingServices::getAutoRecurring($RecurringDate, 1);
            $PayrollautoRecurring = \App\Models\Backend\BillingServices::getAutoRecurring($RecurringDate, 2);
            $recurringArray = array();
            if ($BKautoRecurring->count() > 0) {
                foreach ($BKautoRecurring as $bk) {
                    if ($bk->division_head != 0 && $bk->service_id == 1) {
                        if ($bk->fixed_fee != 0) {
                            $recurringArray[$bk->service_id][$bk->division_head][] = array("entity_name" => $bk->name,
                                "amount" => $bk->fixed_total_amount);
                        }
                    }
                    if ($bk->TL != 0 && in_array($bk->service_id, [1])) {
                        if ($bk->fixed_fee != 0) {
                            $recurringArray[$bk->service_id][$bk->TL][] = array("entity_name" => $bk->name,
                                "amount" => $bk->fixed_total_amount);
                        }
                    } 
                    if ($bk->TAM != 0 && in_array($bk->service_id, [1])) {
                        if ($bk->fixed_fee != 0) {
                            $recurringArray[$bk->service_id][$bk->TAM][] = array("entity_name" => $bk->name,
                                "amount" => $bk->fixed_total_amount);
                        }
                    } else {
                        if ($bk->fixed_fee != 0 && $bk->service_id != 2) {
                            $recurringArray[$bk->service_id][0][] = array("entity_name" => $bk->name,
                                "amount" => $bk->service_id == 4 ? $bk->monthly_amount : $bk->fixed_fee);
                        }
                    }
                }
            }

            if ($PayrollautoRecurring->count() > 0) {
                foreach ($PayrollautoRecurring as $payroll) {
                    if ($payroll->division_head != 0 && $payroll->service_id == 2) {
                        $recurringArray[$payroll->service_id][$payroll->division_head][] = array("entity_name" => $payroll->name,
                            "amount" => $payroll->fixed_fee);
                    }
                    if ($payroll->TAM != 0 && $payroll->service_id == 2) {
                        $recurringArray[$payroll->service_id][$payroll->TAM][] = array("entity_name" => $payroll->name,
                            "amount" => $payroll->fixed_fee);
                    }
                    if ($payroll->TL != 0 && $payroll->service_id == 2) {
                        $recurringArray[$payroll->service_id][$payroll->TL][] = array("entity_name" => $payroll->name,
                            "amount" => $payroll->fixed_fee);
                    }
                }
            }

            if (!empty($recurringArray)) {
                $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", "AUT")->first();
                foreach ($recurringArray as $serviceId => $users) {
                    $serviceName = \App\Models\Backend\Services::where("id", $serviceId)->select("service_name")->first();
                    foreach ($users as $user => $value) {
                        $userId = $user;
                        $user = \App\Models\User::find($user);
                        if ((isset($user->is_active) && $user->is_active == '1') || $userId == 0) {
                            $con = $emailTemplate->content;
                            $content = str_replace("[SERVICE]", $serviceName->service_name, $con);
                            $content = str_replace("[SENTDATE]", date('d-m-Y', strtotime($RecurringDate)), $content);
                            $table = '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">'
                                    . '<tr><td>Client Name</td><td align="left">Amount</td></tr>';
                            foreach ($value as $keys => $entitys) {
                                $table .= '<tr><td align="left">' . $entitys['entity_name'] . '</td>';
                                if ($entitys['amount'] == 0) {
                                    $table .= '<td align="left">-</td></tr>';
                                } else {
                                    $table .= '<td align="left">$' . $entitys['amount'] . '</td></tr>';
                                }
                            }
                            $table .= '</table></div>';
                            $content = str_replace("[CLIENTLIST]", $table, $content);

                            $emailData['to'] = $userId != 0 ? strtolower($user->email) : 'billing@befree.com.au';
                            $emailData['cc'] = (isset($emailTemplate->cc) && $emailTemplate->cc != '') ? strtolower($emailTemplate->cc) . ',billing@befree.com.au' : 'billing@befree.com.au';
                            $emailData['bcc'] = strtolower($emailTemplate->bcc);
                            $emailData['subject'] = $emailTemplate->subject;
                            $emailData['content'] = $content;

                            $sendMail = cronStoreMail($emailData);
                            if (!$sendMail) {
                                app('log')->channel('priorrecurring')->error("Prior Recurring failed mail send failed : " . $e->getMessage());
                            }
                        }
                    }
                }
            }

            // check recurring for other service recurring
//        $otherAutoRecurring = \App\Models\Backend\BillingServices::getOtherAutoRecurring($RecurringDate);
//        $otherrecurringArray = array();
//        foreach ($otherAutoRecurring as $recurring) {
//            if ($recurring->fixed_fee != 0)
//                $otherrecurringArray[$recurring->service_id][] = array("entity_name" => $recurring->name,
//                    "amount" => $recurring->fixed_fee);
//        }
//        if (!empty($otherrecurringArray)) {
//            $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", "AUT")->first();
//            foreach ($otherrecurringArray as $serviceId => $other) {
//                $serviceName = \App\Models\Backend\Services::where("id", $serviceId)->select("service_name")->first();
//                $con = $emailTemplate->content;
//                $content = str_replace("[SERVICE]", $serviceName->service_name, $con);
//                $content = str_replace("[SENTDATE]", date('d-m-Y', strtotime($RecurringDate)), $content);
//                $table = '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">'
//                        . '<tr><td>Client Name</td><td align="left">Amount</td></tr>';
//                foreach ($other as $keys => $entitys) {
//                    $table .= '<tr><td align="left">' . $entitys['entity_name'] . '</td>';
//                    $table .= '<td align="left">$' . $entitys['amount'] . '</td></tr>';
//                }
//                $table .= '</table></div>';
//                $content = str_replace("[CLIENTLIST]", $table, $content);
//
//                $emailData['to'] = 'billing@befree.com.au';
//                $emailData['cc'] = strtolower($emailTemplate->cc);
//                $emailData['bcc'] = strtolower($emailTemplate->bcc);
//                $emailData['subject'] = $emailTemplate->subject;
//                $emailData['content'] = $content;
//
//                $sendMail = cronStoreMail($emailData);
//                if (!$sendMail) {
//                    app('log')->channel('priorrecurring')->error("Prior Recurring failed mail send failed ");
//                }
//            }
//        }
        } catch (Exception $e) {
            $cronName = "Prior Recurring";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
