<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class Ticket extends Model {

    protected $guarded = [];
    protected $table = 'ticket';
    protected $hidden = [];
    public $timestamps = false;

    public static function getTickets() {
        return Ticket::with("createdBy:id,userfullname","openedBy:id,userfullname")
                        ->leftjoin("ticket_type as tt", "tt.id", "ticket.type_id")
                        ->leftjoin("ticket_status as ts", "ts.id", "ticket.status_id")
                        ->leftjoin("entity as e", "e.id", "ticket.entity_id")
                        ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                        ->leftjoin("ticket_assignee as ta", "ta.ticket_id", "ticket.id")
                        ->leftjoin("user as u", "u.id", "ta.ticket_assignee")
                        ->leftjoin("user as ut", "ut.id", "ticket.technical_account_manager")
                        ->select("e.trading_name","ep.trading_name as parent_name", "e.discontinue_stage","e.parent_id", "ts.status", DB::raw("GROUP_CONCAT(ta.ticket_assignee) as ticket_assignee"),DB::raw("GROUP_CONCAT(CONCAT(u.userfullname,'-',ta.complete_date)) as ticket_assignee_with_date"), DB::raw("GROUP_CONCAT(u.userfullname) as ticket_assignee_name"), "tt.name as ticket_type", "ut.userfullname as tam", "ut.user_image", "ticket.*")
                        ->whereRaw("(e.discontinue_stage != 2 OR ticket.entity_id =0)");
    }

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function openedBy() {
        return $this->belongsTo(\App\Models\User::class, 'opened_by', 'id');
    }

    public static function boot() {
        parent::boot();
        self::updating(function($ticket) {
            $col_name = [
                'type_id' => 'Ticket Type',
                'entity_id' => 'Entity',
                'team_id' => 'Team',
                'status_id' => 'Status',
                'subject' => 'Subject',
                'technical_head' => 'Technical Head',
                'technical_account_manager' => 'Technical Account Manager',
                'problem_our_side' => 'Problem Our Side',
                'staff_involved_issue' => 'Staff Involved Issue',
                'type_of_mistake' => 'Type Of Mistake',
                'issue_detail' => 'Issue Detail',
                'reason_why_this_has_occurred' => 'Ticket Type',
                'staff_incharge' => 'Staff Incharge',
                'ticket_topic' => 'Ticket Topic',
                'sr_topic' => 'SR Topic',
                'sr_practice_id' => 'SR Practice'
            ];
            $changesArray = \App\Http\Controllers\Backend\Ticket\TicketController::saveHistory($ticket, $col_name);

            if (!empty($changesArray)) {
                //Insert value in audit table
                TicketAudit::create([
                    'ticket_id' => $ticket->id,
                    'changes' => json_encode($changesArray),
                    'modified_on' => date('Y-m-d H:i:s'),
                    'modified_by' => loginUser()
                ]);
            }
        });
    }

    public static function getReportData() {
        return Ticket::leftjoin("entity as e", "e.id", "ticket.entity_id")
                ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                        ->leftjoin("ticket_assignee as ta", "ta.ticket_id", "ticket.id")
                        ->where("e.discontinue_stage", "!=", "2");
    }

    public static function reportArrangeData($data) {
        $user = \App\Models\User::select('userfullname', 'id')->get()->pluck('userfullname', 'id')->toArray();

        $arrDDOption['Ticket Type'] = TicketType::select('name', 'id')->get()->pluck('name', 'id')->toArray();
        $arrDDOption['Team'] = Team::select('team_name', 'id')->get()->pluck('team_name', 'id')->toArray();
        $arrDDOption['Ticket Status'] = TicketStatus::select('status', 'id')->get()->pluck('status', 'id')->toArray();
        $arrDDOption['Severity'] = config('constant.ticketseverity');
        $arrDDOption['Priority'] = config('constant.ticketpriority');
        $arrDDOption['Technical Head'] = $user;
        $arrDDOption['Technical Account Manager'] = $user;
        $arrDDOption['Created By'] = $user;
        $arrDDOption['Is this problem on our side'] = config('constant.yesNo');
        $arrDDOption['Staff involved in issue'] = $user;
        $arrDDOption['Staff incharge'] = $arrDDOption['Ticket Assignee'] = $user;
        $arrDDOption['Ticket Topic'] = config('constant.tickettopic');
        $arrDDOption['Type of mistake'] = config('constant.tickettypeofmistake');
        $arrDDOption['Practice Name'] = \App\Models\User::select('userfullname', 'id')->get()->pluck('userfullname', 'id')->toArray();
        foreach ($data->toArray() as $key => $value) {
            foreach ($value as $rowkey => $rowvalue) {
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue;
            }
        }

        return $data;
    }

}
