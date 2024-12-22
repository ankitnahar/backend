<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class WorksheetChecklistGroupChecked extends Model {

    protected $guarded = [];
    protected $fillable = [];
    protected $table = 'worksheet_checklist_group_checked';
    protected $hidden = [];
    public $timestamps = false;
}
