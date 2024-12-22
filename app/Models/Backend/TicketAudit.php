<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class TicketAudit extends Model {

    protected $guarded = [];
    protected $table = 'ticket_audit';
    protected $hidden = [];
    public $timestamps = false;

   public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
