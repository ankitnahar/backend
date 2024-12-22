<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class ReminderEmailCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminder:info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reminder Command';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle($date = null) {
        $date = date('Y-m-d');
        $newDate = date("Y-m-d",strtotime('-15 days',strtotime($date)));
        /*for information
        $pendingInformation = \App\Models\Backend\Information::leftjoin("information_log as info","info.information_id","information.id")
                ->where("information.stage_id","5")
                ->where("info.status_id","5")
                ->whereRaw("DATE(info.modified_on) <= '".$newDate."'")->groupBy("information.id")->get();
        foreach($pendingInformation as $p){
            \App\Models\Backend\Information::where("id",$p->information_id)->update(["stage_id" => "1"]);
        }
        
        //For Query
        
        $pendingQuery = \App\Models\Backend\Query::leftjoin("query_log as ql","ql.query_id","query.id")
                ->where("query.stage_id","5")
                ->where("ql.status_id","5")
                ->whereRaw("DATE(ql.modified_on) <= '".$newDate."'")->groupBy("query.id")->get();
        foreach($pendingQuery as $p){
            \App\Models\Backend\Query::where("id",$p->query_id)->update(["stage_id" => "1"]);
        }*/
        
        $information = \App\Models\Backend\Information::leftjoin("entity as e","e.id","information.entity_id")
                 ->select("information.*")
                ->where('information.reminder_date', $date)->where("information.stage_id", "5")->where("e.discontinue_stage", "=", "0"); //->get();
        
        if ($information->count() > 0) {
            foreach ($information->get() as $info) {
                $informationReminder = \App\Models\Backend\InformationReminderLog::where("information_id", $info->id)->whereRaw("DATE_FORMAT(created_on, '%Y-%m-%d') = '" . $date . "'");
                if ($informationReminder->count() == 0) {
                    $nextReminder = date('Y-m-d',strtotime(' +'.$info->reminder.' days'));
                    $emailTemplate = \App\Models\Backend\EmailTemplate::where('code', 'InfoReminder')->where("is_active", "1")->first();
                    $contactInfo = \App\Models\Backend\Contact::leftjoin("entity as e", "e.id", "contact.entity_id")
                            ->select("contact.to as to_email", "contact.cc as cc_email", "contact.other_email as bcc", "contact.first_name", "e.trading_name")
                            ->where("contact.entity_id", $info->entity_id)
                            ->where("contact.is_display_bk_checklist", "1")
                            ->where('contact.is_archived', "=", "0")
                            ->first();

                    $data['from_email'] = 'no-reply@befree.com.au';
                    $data['to'] = $contactInfo->to_email;
                    $data['cc'] = $contactInfo->cc_email;
                    $subject = str_replace(array("PERIOD"), array($info->subject), $emailTemplate->subject);
                    $content = str_replace(array("CONTACTNAME", "SUBJECT", "LINK"), array($contactInfo->first_name, $info->subject, '<a href="http://client.befree.com.au">Click here for login</a>'), $emailTemplate->content);
                    $data['subject'] = $subject;
                    $data['content'] = $content;
                    \App\Models\Backend\InformationReminderLog::addLog($info->id, $contactInfo->to_email);
                    //send mail to the client
                    \App\Models\Backend\Information::where("id",$info->id)->update(["reminder_date" => $nextReminder]);
                    cronStoreMail($data);
                }
            }
        }

        $query = \App\Models\Backend\Query::leftjoin("entity as e","e.id","query.entity_id")
                 ->select("query.*")
                ->where('query.reminder_date', $date)->where("query.stage_id", "5")->where("e.discontinue_stage", "=", "0");
        if ($query->count() > 0) {
            foreach ($query->get() as $q) {
                $queryReminder = \App\Models\Backend\QueryReminderLog::where("query_id", $q->id)->whereRaw("DATE_FORMAT(created_on, '%Y-%m-%d') = '" . $date . "'");
                if ($queryReminder->count() == 0) {
                    $nextReminder = date('Y-m-d',strtotime(' +'.$q->reminder.' days'));
                    $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", "QueryReminder")->where("is_active", "1")->first();

                    $contactInfo = \App\Models\Backend\Contact::leftjoin("entity as e", "e.id", "contact.entity_id")
                            ->select("contact.to as to_email", "contact.cc as cc_email", "contact.other_email as bcc", "contact.first_name", "e.trading_name")
                            ->where("contact.entity_id", $q->entity_id)
                            ->where("contact.is_display_bk_checklist", "1")
                            ->where('contact.is_archived', "=", "0")
                            ->first();
                    $data['from_email'] = 'no-reply@befree.com.au';
                    $data['to'] = $contactInfo->to_email;
                    $data['cc'] = $contactInfo->cc_email;
                    $subject = str_replace(array("PERIOD"), array($q->subject), $emailTemplate->subject);
                    $content = str_replace(array("CONTACTNAME", "SUBJECT", "LINK", "PERIOD"), array($contactInfo->first_name, $q->subject, '<a href="http://client.befree.com.au">Click here for login</a>'), $emailTemplate->content);
                    $data['subject'] = $subject;
                    $data['content'] = $content;
                    \App\Models\Backend\QueryReminderLog::addLog($q->id, $contactInfo->to_email);
                    cronStoreMail($data);
                    \App\Models\Backend\Query::where("id",$q->id)->update(["reminder_date" => $nextReminder]);
                }
            }
        }
    }

}
