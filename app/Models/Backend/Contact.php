<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class Contact extends Model {

    //
    protected $table = 'contact';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function archivedBy() {
        return $this->belongsTo(\App\Models\User::class, 'archived_by', 'id');
    }

    public function contactRemark() {
        return $this->belongsTo(\App\Models\Backend\ContactRemark::class, 'id', 'contact_id');
    }
    
    public static function newsletterData($id = '') {
        return $Contact = Contact::select(['contact.to','contact.cc','contact.other_email','e.id as entity_id','contact.contact_position_id'])
                ->leftJoin('entity as e', 'e.id', '=', 'contact.entity_id')  
                ->leftJoin('billing_basic as b', 'b.entity_id', '=', 'contact.entity_id')  
                ->where("e.discontinue_stage","!=","2")
                ->where("contact.is_archived","0")
                ->whereNotIn("b.entity_grouptype_id",[2,8,9,14])
                ->where("contact.send_newsletter","1")
                ->whereRaw("e.id NOT IN(132,1388,1420,3428,3431) AND contact.contact_position_id NOT IN(2,3)");
    }

     public static function contactData($id = '') {
        return $Contact = Contact::with('contactRemark:id,contact_id,notes,created_on','archivedBy:userfullname as archived_by,id', 'createdBy:userfullname as created_by,id')
                ->select(['ed.code','ed.trading_name','ep.trading_name as parent_name','ed.parent_id', 'ed.billing_name', 'ed.name','ed.tfn_number','ed.abn_number', 'contact.*', 'ed.discontinue_stage', DB::raw('GROUP_CONCAT(DISTINCT s.service_name) AS service_name'),
                    DB::raw('GROUP_CONCAT(DISTINCT ut.userfullname) AS tam_name'),DB::raw('GROUP_CONCAT(DISTINCT utl.userfullname) AS tl_name'), DB::raw('count(cr.id) as remark_count'), 'bb.is_related'
                ])
                ->leftJoin('entity as ed', 'ed.id', '=', 'contact.entity_id')
                ->leftJoin('entity as ep', 'ep.id', '=', 'ed.parent_id')
                ->leftJoin('entity_allocation as ea', function($query) {
                    $query->on('ea.entity_id', '=', 'contact.entity_id');
                    $query->whereRaw('FIND_IN_SET(ea.service_id,contact.service_id)');
                })
                ->leftJoin('user as ut', function($query) {
                    $query->where('ut.id',DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                })
                 ->leftJoin('user as utl', function($query) {
                    $query->where('utl.id',DB::raw('JSON_VALUE(ea.allocation_json,"$.60")'));
                })
                ->leftJoin("services as s", DB::raw("FIND_IN_SET(s.id,contact.service_id)"), ">", DB::raw("'0'"))
                ->leftJoin('contact_remark as cr', 'cr.contact_id', '=', 'contact.id')
                ->leftJoin('billing_basic as bb', 'bb.entity_id', '=', 'contact.entity_id');
    }
    /*
     * Created by - Pankaj
     * save history when user information update 
     */

    public static function boot() {
        parent::boot();
        self::updating(function($contact) {
            $col_name = [
                'service_id' => 'Service',
                'contact_position_id' => 'Contact Position',
                'first_name' => 'First Name',
                'from_email' => 'From Email',
                'contact_person' => 'contact person',
                'other_email' => 'other email',
                'is_display_bk_checklist' => 'Display in Bk checklist',
                'is_display_information' => 'Display in Information',
                'is_login' => 'Client portal right',
                'send_newsletter' => 'newsletter',
                'mobile_no' => 'mobile no',
                'office_no' => 'office no',
                'fax_no' => 'fax no',
                'is_feedback_contact' => 'Client feedback',
                'feedback_email' => 'Feddback Email',
                'from_email' => 'Group From Email'
            ];
            if ($contact->is_archived != 1) {
                $changesArray = \App\Http\Controllers\Backend\Contact\ContactController::saveHistory($contact, $col_name);

                $updatedBy = loginUser();
                //Insert value in audit table
                ContactAudit::create([
                    'contact_id' => $contact->id,
                    'changes' => json_encode($changesArray),
                    'modified_on' => date('Y-m-d H:i:s'),
                    'modified_by' => $updatedBy
                ]);
            }
        });
    }
    
    public static function checkContact($entityId){
        return Contact::where("entity_id",$entityId)->where("is_archived","0")
                ->where("is_feedback_call","1");
        
    }
}
