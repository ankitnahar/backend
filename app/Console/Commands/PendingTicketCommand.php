<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class PendingTicketCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ticket:pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ticket Pending Reminder';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $emailEvent = \App\Models\Backend\EmailTemplate::where("code", "PENDINGTICKETREMINDER")->first();
            if ($emailEvent->is_active) {
                $tickets = \App\Models\Backend\Ticket::
                        leftjoin("ticket_assignee as ta", "ta.ticket_id", "ticket.id")
                        ->leftjoin("ticket_type as tt", "tt.id", "ticket.type_id")
                        ->leftjoin("ticket_status as ts", "ts.id", "ticket.status_id")
                        ->leftjoin("entity as e", "e.id", "ticket.entity_id")
                        ->leftjoin("user as u", "u.id", "ticket.created_by")
                        ->leftjoin("user as ut", "ut.id", "ta.ticket_assignee")
                        ->select("u.userfullname", "ut.userfullname as assignee_name", "ut.email as assignee_email", "ticket.*","ta.ticket_assignee", "tt.name", "ts.status", "e.trading_name")
                        ->where("ta.mark_as_complete", 0)
                        ->where("u.is_active","1")
                        ->where("ut.is_active","1")
                        ->whereIn("ticket.status_id", [1, 2]);

                $asigneeTicket = array();
                $i=0;
                if ($tickets->count() > 0) {
                    foreach ($tickets->get() as $ticket) {
                        $i++;
                        $allassignee = \App\Models\Backend\TicketAssignee::where("ticket_id", $ticket->id)
                                        ->leftjoin("user as u", "u.id", "ticket_assignee.ticket_assignee")
                                        ->select("u.userfullname as assignee", "ticket_assignee.mark_as_complete")
                                        ->where("u.is_active","1")
                                        ->get()->toArray();
                        $asigneeTicket[$ticket->ticket_assignee][$i] = array('ticket_id' => $ticket->id,
                            'subject' => $ticket->subject,
                            'ticket_code' => $ticket->code,
                            'created_on' => $ticket->created_on,
                            'modified_on' => $ticket->modified_on,
                            'created_by' => $ticket->userfullname,
                            'assignee' => $ticket->assignee_name,
                            'assignee_email' => $ticket->assignee_email,
                            'ticket_type' => $ticket->name,
                            'status' => $ticket->status,
                            'ticketassginee' => $allassignee
                        );
                        
                    }
                }

                if (isset($asigneeTicket) && !empty($asigneeTicket)) {
                    foreach ($asigneeTicket as $key => $value) {
                        $tblRow1 = "";
                        foreach ($value as $keyuser => $keyvalue) {
                            if ($keyvalue['modified_on'] != '' && $keyvalue['modified_on'] != '0000-00-00') {
                                $modified_date = date('d-m-Y', strtotime($keyvalue['modified_on']));
                            } else {
                                $modified_date = "----------";
                            }
                            if (isset($keyvalue['ticketassginee']) && !empty($keyvalue['ticketassginee'])) {
                                $assigneeDetails = "";
                                foreach ($keyvalue['ticketassginee'] as $ke => $val) {
                                    $comFlag = (isset($val['mark_as_complete']) && !empty($val['mark_as_complete']) ? '<span title="Ticket is completed by this assignee"> <img src="http://befreecrm.com.au/images/emailtemplate/completed.png"/>  </span>' : '<span title="Ticket is not completed by this assignee"> <img src="http://befreecrm.com.au/images/emailtemplate/pending.png" />  </span>');
                                    $assiName = (isset($val['assignee']) && !empty($val['assignee']) ? $val['assignee'] : '');
                                    $assigneeDetails .= $assiName . ' - ' . $comFlag . '<br/>';
                                }
                            }
                            $tblRow1 .= '<tr>
                        <td>' . $keyvalue['ticket_code'] . '</td>
                        <td>' . $keyvalue['subject'] . '</td>
                        <td>' . $keyvalue['ticket_type'] . '</td>
                        <td>' . $keyvalue['status'] . '</td>    
                        <td>' . $keyvalue['created_by'] . ' | ' .date('d-m-Y', strtotime($keyvalue['created_on'])).'</td>
                        <td>' . $modified_date . '</td>
                        <td>' . trim($assigneeDetails, '<br/>') . '</td>    
                    </tr>';
                        }
                        $tblAct1 = '<div class="table_template">
            <table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">
              <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Type</th>
                <th>Status</th>
                <th>Created</th>
                <th>Last Updated On</th>
                <th>Assignee</th>
              </tr>' . $tblRow1 . '
                    </table></div>';

                        $data['to'] = $keyvalue['assignee_email'];
                        $data['cc'] = ($emailEvent->cc != "") ? $emailEvent->cc : '';
                        $data['subject'] = $emailEvent->subject;
                        $data['content'] = html_entity_decode(str_replace(array('[USERNAME]', 'TABLE-ACTION'), array($keyvalue['assignee'], $tblAct1), $emailEvent->content));
                        $email = cronStoreMail($data);
                        if(!$email){
                            app('log')->channel('ticketreminder')->error("Ticket Reminder mail send failed");
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $cronName = "Pending Ticket";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
