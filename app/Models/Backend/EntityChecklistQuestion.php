<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use App\Models\Backend\MasterChecklistQuestion,
    App\Models\Backend\MasterChecklistGroup;

class EntityChecklistQuestion extends Model {
    
    protected $guarded = ['id'];
    protected $table = 'entity_checklist_question';
    protected $fillable = ['id', 'entity_id', 'entity_checklist_id', 'checklist_group_id', 'question_name', 'help_text', 'is_applicable', 'created_by', 'created_on'];
    public $timestamps = false;
    
    public static function arrangeData($data, $newQuestoin){
        $i=0;
        $questionBygroup = $groupIds = array();
        foreach($data->toArray() as $key => $value){
            if(!isset($value['is_applicable']))
                $value['is_applicable'] = 0;
            
            if($newQuestoin == 1){
                $value['master_checklist_question_id'] = $value['id'];
                $value['id'] = 0;
            }
            
            $questionBygroup[$value['checklist_group_id']][] = $value;
            $i++;
            if(!in_array($value['checklist_group_id'], $groupIds))
                $groupIds[] = $value['checklist_group_id'];
        }
        $groupName = MasterChecklistGroup::select('id','name')->whereIn('id', $groupIds)->get()->pluck('name','id')->toArray();
        
        $questionDetail['question'] = $questionBygroup;
        $questionDetail['group']    = $groupName;
        
        return $questionDetail;
    }
}
