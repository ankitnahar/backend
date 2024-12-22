<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;
use DB;
class WorksheetAdditionalAssinee extends Model
{
    protected $guarded = [ ];
    protected $fillable = [];

    protected $table = 'worksheet_additional_assignee';
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
    
    public function masterId()
    {
        return $this->belongsTo(MasterActivity::class, 'master_id', 'id');
    }
    
    public function taskId()
    {
        return $this->belongsTo(TaskActivity::class, 'task_id', 'id');
    }
    
    public function serviceId()
    {
        return $this->belongsTo(Services::class, 'service_id', 'id');
    }
    
    public function freqId()
    {
        return $this->belongsTo(Frequency::class, 'freq_id', 'id');
    }
    
    public function statusId()
    {
        return $this->belongsTo(WorksheetStatus::class, 'status_id', 'id');
    }
    //user wise worksheet data
    public static function getWorksheet() {
    return Worksheet::with('createdBy:userfullname as created_by,id')
            ->with('modifiedBy:userfullname as modified_by,id')
            ->with('masterId:code,name as master_name,id')
            ->with('taskId:name as task_name,id')
            ->with('serviceId:service_name,id')
            ->with('freqId:frequency_name,id')
            ->with('statusId:status_name,id')
            ->join("entity as e","e.id","worksheet.entity_id")
            ->join("billing_basic as bs","bs.entity_id","e.id")
            ->join("entity_allocation as ea", function($join) {
                            $join->on('ea.entity_id', '=', 'e.id');
                            $join->on('ea.service_id', '=', 'worksheet.service_id');
                        });
    }
    
    public static function worksheetArrangeData($worksheet){
        $entityAllocation = entityAllocation("'9,10,71'");       
        foreach($worksheet as $row){
            $row['allocation'] = (isset($entityAllocation[$row->entity_id. " - " .$row->service_id])) ? $entityAllocation[$row->entity_id. " - " .$row->service_id] : '';
        }       
        return $worksheet;
        
    }
    
    
   
}

