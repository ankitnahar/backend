<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class WorksheetSchedule extends Model {

    protected $guarded = [];
    protected $fillable = [ 'master_activity_id','entity_id','task_id','start_date','end_date','frequency_id','expert_day','expert_month','due_after_day','due_month_day','due_on_particular_date','notes','created_by','created_on','is_display_schedule'];
    protected $table = 'worksheet_schedule';
    protected $hidden = [];
    public $timestamps = false;
}
