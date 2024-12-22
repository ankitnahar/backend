<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class WorksheetLog extends Model
{
    protected $guarded = [ ];
    protected $fillable = [];

    protected $table = 'worksheet_status_log';
    protected $hidden = [ ];
    public $timestamps = false;   
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public function statusId()
    {
        return $this->belongsTo(WorksheetStatus::class, 'status_id', 'id');
    }
    
    
    
    
   
}

