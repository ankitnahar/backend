<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class UserAudit extends Model {

    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'user_audit';
    protected $hidden = [];
    public $timestamps = false;

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id')->select('id', 'userfullname');
    }

}
