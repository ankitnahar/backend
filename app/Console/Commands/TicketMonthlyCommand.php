<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
class TicketMonthlyCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ticket:monthly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'TAM Team Monthly Report Send mail using cron file';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $startDt = date("Y-m-01", strtotime("-1 months"));
            $endDt = date("Y-m-31", strtotime("-1 months"));

            $TAMList = \App\Models\Backend\UserHierarchy::leftjoin("user as ut", "ut.id", "user_hierarchy.user_id")
                            ->select("user_hierarchy.designation_id", "user_hierarchy.user_id", "ut.userfullname","ut.email")
                            ->where("designation_id", "9")
                            ->where("is_active", "1");
            $template = \App\Models\Backend\EmailTemplate::where("code","MTR")->first();
            if($TAMList->count() > 0){        
            foreach ($TAMList->get() as $tam) {
                try {
                    $ticket = \App\Models\Backend\Ticket::
                            leftjoin("ticket_type as tt", "tt.id", "ticket.type_id")
                            ->leftjoin("ticket_status as ts", "ts.id", "ticket.status_id")
                            ->leftjoin("entity as e", "e.id", "ticket.entity_id")
                            ->leftjoin("user as u", "u.id", "ticket.created_by")
                            ->select("u.userfullname", "ticket.*", "tt.name", "ts.status", "e.trading_name")
                            ->whereIn("ticket.type_id", [1, 2, 3])
                            ->whereBetween('ticket.created_on', [$startDt, $endDt])
                            ->where("technical_account_manager", $tam->user_id);
                    //showArray($ticket->toSql());exit;
                    if ($ticket->count() > 0) {
                        $data = $columnData = array();
                        $code = '';
                        $i = 1;
                        $data[0][] = 'Sr.No';
                        $data[0][] = 'Code';
                        $data[0][] = 'Type';
                        $data[0][] = 'Status';
                        $data[0][] = 'Subject';
                        $data[0][] = 'Client name';
                        $data[0][] = 'Technical account manager';
                        $data[0][] = 'Description';
                        $data[0][] = 'Explain why this has occurred';
                        $data[0][] = 'Resolution / Comments';
                        $data[0][] = 'Created by';
                        $data[0][] = 'Created on';

                        foreach ($ticket->get()->toArray() as $value) {
                            $columnData[] = $i;
                            $columnData[] = $value['code'];
                            $columnData[] = $value['name'];
                            $columnData[] = $value['status'];
                            $columnData[] = $value['subject'];
                            $columnData[] = $value['trading_name'];
                            $columnData[] = $tam->userfullname;
                            $columnData[] = $value['issue_detail'];
                            $columnData[] = $value['reason_why_this_has_occurred'];
                            $columnData[] = isset($value['resolution']) ? $value['resolution'] : '-';
                            $columnData[] = isset($value['userfullname']) ? $value['userfullname'] : '-';
                            $columnData[] = dateFormat($value['created_by']);
                            $data[] = $columnData;
                            $columnData = array();
                            $i++;
                        }
                        //showArray($data);exit;
                        app('excel')->create('MonthlyTicketReport ' . $tam->userfullname .date('d-m-Y'), function($excel) use ($data) {
                            $excel->sheet('Ticket Monthly Report', function($sheet) use ($data) {
                                $sheet->cell('A1:L1', function($cell) {
                                    $cell->setFontColor('#ffffff');
                                    $cell->setBackground('#0c436c');
                                });

                                $sheet->getAllowedStyles();
                                $sheet->fromArray($data, null, 'A1', false, false);
                            });
                        })->store('xlsx', storage_path('templocation/ticket/monthly'));                       
                    }
                     $ticketAttechment = storage_path('templocation/ticket/monthly/MonthlyTicketReport ' . $tam->userfullname .date('d-m-Y').'.xlsx');
                   // $request->merge(['exceldownload' => '0']);
                     $attachment = array();
                    $attachment[] = $ticketAttechment;
                   

                    $emailData['to'] = $tam->email;
                    $emailData['cc'] = $template->cc;
                    $emailData['subject'] = $template->subject;   
                    $emailData['content'] = html_entity_decode(str_replace('[MONTH]', date("M-Y", strtotime("-1 months")), $template->content));
                    $emailData['attachment'] = $attachment;                  
                    
                   $sendMail =  cronStoreMail($emailData);
                   if(!$sendMail){
                        app('log')->channel('monthlyreport')->error("Ticket monthly report send failed ");
                   }
                } catch (Exception $e) {
                    app('log')->channel('monthlyreport')->error("Ticket monthly report send failed : " . $e->getMessage());
                }
            }
            }
        } catch (Exception $e) {
            $cronName = "Ticket Monthly";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
