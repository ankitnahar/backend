<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Exception;
class FutureProposalCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ff:future';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Future Proposal';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $currentDate = date('Y-m-d');
            $firstDateOfCurrentMonth = date('Y-m-21');

            if ($currentDate == $firstDateOfCurrentMonth) {
                $plusOneMonth = date('Y-m-d', strtotime("+1 months" . $currentDate));
                $currentMonth = date('F', strtotime($plusOneMonth));
                $currentMonthYear = date('Y', strtotime($plusOneMonth));
                if ($currentMonth == 'July') {
                    $currentMonthThree = $currentMonth;
                } else {
                    $currentMonthThree = substr($currentMonth, 0, 3);
                }
                $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", "RMDFFP")->first();



                $ff = \App\Models\Backend\FFProposal::leftjoin("entity as e", "e.id", "ff_proposal.entity_id")
                        ->leftJoin('entity_allocation as ea', function($query) {
                            $query->on('ea.entity_id', '=', 'ff_proposal.entity_id');
                            $query->on("ea.service_id", "=", "ff_proposal.service_id");
                        })
                        ->leftJoin('user as ut', function($query) {
                            $query->where('ut.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                        })
                        ->select("ff_proposal.id", "ff_proposal.month", "ff_proposal.year", "ff_proposal.entity_id", "ff_proposal.status_id", "e.name", "ut.email", "ut.userfullname")
                        ->where("month", $currentMonthThree)
                        ->where("year", $currentMonthYear)
                        ->where("status_id", "10");
                       
                if ($ff->count() > 0) {
                    foreach ($ff->get() as $row) {
                        \App\Models\Backend\FFProposal::where("id", $row->id)->update(["status_id" => "1"]);
                        \App\Models\Backend\FFLog::addLog($row->id, 1);

                        if ($emailTemplate->is_active) {

                            $data['to'] = $row->email;
                            $data['cc'] = ($emailTemplate->cc != "") ? $emailTemplate->cc : '';
                            $data['subject'] = str_replace(array('ENTITY_NAME'), array($row->name), $emailTemplate->subject);
                            $data['content'] = html_entity_decode(str_replace(array('USERNAME'), array($row->userfullname), $emailTemplate->content));

                            $email = cronStoreMail($data);
                            if (!$email) {
                                app('log')->channel('futureproposal')->error("Future proposal mail send failed");
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
           $cronName = "Future Proposal";
            $message = $e->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
