<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class FiveYearDataRetentionCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'five:dataremove';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'After Five year data will remove';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $date = date('Y-m-d H:i:s', strtotime('-5 years'));
            $twoyeardate = date('Y-m-d H:i:s', strtotime('-2 years'));
            $oneyeardate = date('Y-m-d H:i:s', strtotime('-1 years'));
            $twoyeardate = date('Y-m-d H:i:s', strtotime('-2 years'));
            
            
            \App\Models\Backend\EntityCallManagement::where("created_on","<",$date)->delete();
            $feedback = \App\Models\Backend\Feedback::where("created_on","<",$date)->get();
            foreach($feedback as $f){
                \App\Models\Backend\FeedbackActionDetail::where('feedback_id',$f->id)->delete();
                \App\Models\Backend\FeedbackCallDetail::where('feedback_id',$f->id)->delete();
                \App\Models\Backend\FeedbackLog::where('feedback_id',$f->id)->delete();
                $f->delete();
            }
            //hr
            $hrDetail = \App\Models\Backend\HrDetail::where("created_on","<",$twoyeardate)->get();
            foreach($hrDetail as $hr){
                \App\Models\Backend\HrDetailHistory::where("hr_detail_id",$hr->id)->delete();
                \App\Models\Backend\Hrdetailcomment::where("hr_detail_id",$hr->id)->delete();
                \App\Models\Backend\HrUserInOuttime::where("hr_detail_id",$hr->id)->delete();
                \App\Models\Backend\HrUserInOuttimeAmendment::where("hr_detail_id",$hr->id)->delete();
                $hr->delete();
            }
            $invoice = \App\Models\Backend\Invoice::where("created_on","<",$date)->get();
            foreach($invoice as $inv){
                \App\Models\Backend\InvoiceLog::where("invoice_id",$inv->id)->delete();
                \App\Models\Backend\InvoiceMasterUnitCalc::where("invoice_id",$inv->id)->delete();
                \App\Models\Backend\InvoiceDescription::where("invoice_id",$inv->id)->delete();
                \App\Models\Backend\InvoiceNotes::where("invoice_id",$inv->id)->delete();
                \App\Models\Backend\InvoicePaidDetail::where("invoice_id",$inv->id)->delete();
                //\App\Models\Backend\InvoiceTemplate::where("invoice_id",$inv->id)->delete();
                \App\Models\Backend\InvoiceUserHierarchy::where("invoice_id",$inv->id)->delete();
               // $inv->delete();
            }
            \App\Models\Backend\Timesheet::where("created_on","<",$date)->delete();
            \App\Models\Backend\HrHoliday::where("created_on","<",$oneyeardate)->delete();
            \App\Models\Backend\HrNojob::where("date","<",$oneyeardate)->delete();
            \App\Models\Backend\PendingTimesheet::where("created_on","<",$date)->delete();
            \App\Models\Backend\HrHolidayDetail::where("created_on","<",$oneyeardate)->delete();
            \App\Models\Backend\PunchinQuestionAnswer::where("created_on","<",$oneyeardate)->delete();
            //\App\Models\Backend\SystemSetupEntityStage::where("completed_on","<",$oneyeardate)->delete();
            //\App\Models\Backend\SystemSetupBdmsUpdation::where("created_on","<",$oneyeardate)->delete();
            //\App\Models\Backend\SystemSetupEntityStageService::where("created_on","<",$oneyeardate)->delete();
            $ticket = \App\Models\Backend\Ticket::where("created_on","<",$date)->get();
            foreach($ticket as $t){
                \App\Models\Backend\TicketAssignee::where("ticket_id",$t->id)->delete();
                \App\Models\Backend\TicketAudit::where("ticket_id",$t->id)->delete();
                \App\Models\Backend\TicketDocument::where("ticket_id",$t->id)->delete();
               $t->delete(); 
            }
           
            //\App\Models\Backend\WriteoffBefree::where("created_on","<",$date)->delete();
            \App\Models\Backend\WriteoffReviewer::where("created_on","<",$date)->delete();
            $worksheetMaster = \App\Models\Backend\WorksheetMaster::leftjoin("worksheet as w","w.worksheet_master_id","worksheet_master.id")
                    ->select("w.id","w.worksheet_master_id")
                    ->where("worksheet_master.created_on","<",$date)->where("w.id","!=",259615)->where("w.id","!=",188429)->get();
            foreach($worksheetMaster as $w){                    
                    \App\Models\Backend\WorksheetTaskChecklist::where("worksheet_id",$w->id)->delete();
                    \App\Models\Backend\WorksheetTaskChecklistComment::where("worksheet_id",$w->id)->delete();
                    \App\Models\Backend\WorksheetChecklistGroupChecked::where("worksheet_id",$w->id)->delete();
                    \App\Models\Backend\WorksheetDocument::where("worksheet_id",$w->id)->delete();
                    
                    \App\Models\Backend\WorksheetNotes::where("worksheet_id",$w->id)->delete();
                    \App\Models\Backend\WorksheetLog::where("worksheet_id",$w->id)->delete();
                    \App\Models\Backend\WorksheetTaskchecklistEmailForClient::where("worksheet_id",$w->id)->delete();
                    \App\Models\Backend\Worksheet::where("id",$w->id)->delete();
                    \App\Models\Backend\WorksheetMaster::where("id",$w->worksheet_master_id)->delete();
            }
            // Remove Folder for last 2 year
            
            /*$year = date('Y', strtotime('-2 years'));
            $directoryEntity = \App\Models\Backend\DirectoryEntity::where('year',$year)->where("service_id","1")->whereNotIn('directory_id',['284'])
                    ->whereIn("entity_id",[]);
            if($directoryEntity->count() > 0){
                $directoryEntity = $directoryEntity->get();
            foreach($directoryEntity as $de){                
                \App\Models\Backend\DirectoryEntityFile::where('directory_entity_id',$de->id)->delete(); 
                $de->delete();
                            }
            }*/
        } catch (Exception $e) {
            $cronName = "five year data retention";
            $message = $e->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
