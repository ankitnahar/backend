<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EmailServerIssue extends Model
{
    protected $guarded = ['id'];
    protected $table = 'email_server_issue';
    public $timestamps = false;

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }    
}
