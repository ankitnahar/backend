<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
class TicketReportCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ticket:admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Admin Monthly Report Send mail using cron file';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $startDt = date("Y-m-01", strtotime("-1 months"));
            $endDt = date("Y-m-31", strtotime("-1 months"));
            $template = \App\Models\Backend\EmailTemplate::where("code","MTR")->first();
            $ticket = \App\Models\Backend\Ticket::
                    leftjoin("ticket_type as tt", "tt.id", "ticket.type_id")
                    ->leftjoin("ticket_status as ts", "ts.id", "ticket.status_id")
                    ->leftjoin("entity as e", "e.id", "ticket.entity_id")
                    ->leftjoin("user as u", "u.id", "ticket.created_by")
                    ->leftjoin("user as ut", "ut.id", "ticket.technical_account_manager")
                    ->select("u.userfullname","ut.userfullname as tam", "ticket.*", "tt.name", "ts.status", "e.trading_name")
                    ->whereIn("ticket.type_id", [1, 2, 3])
                    ->whereBetween('ticket.created_on', [$startDt, $endDt]);

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
                    $columnData[] = $value['tam'];
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
                app('excel')->create('MonthlyTicketReport '. date('d-m-Y'), function($excel) use ($data) {
                    $excel->sheet('Ticket Monthly Report', function($sheet) use ($data) {
                        $sheet->cell('A1:L1', function($cell) {
                            $cell->setFontColor('#ffffff');
                            $cell->setBackground('#0c436c');
                        });

                        $sheet->getAllowedStyles();
                        $sheet->fromArray($data, null, 'A1', false, false);
                    });
                })->store('xlsx', storage_path('templocation/ticket/admin'));
            }
            $ticketAttechment = storage_path('templocation/ticket/admin/MonthlyTicketReport '. date('d-m-Y') . '.xlsx');
            // $request->merge(['exceldownload' => '0']);
            $attachment = array();
            $attachment[] = $ticketAttechment;


            $emailData['to'] = 'jigneshk@befree.com.au';
            $emailData['cc'] = $template->cc;
            $emailData['subject'] = $template->subject;
            $emailData['content'] = html_entity_decode(str_replace('[MONTH]', date("M-Y", strtotime("-1 months")), $template->content));
            $emailData['attachment'] = $attachment;

            $sendMail = cronStoreMail($emailData);
            if (!$sendMail) {
                app('log')->channel('adminreport')->error("Ticket monthly report send failed ");
            }
        } catch (Exception $e) {
            $cronName = "Ticket Report";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
