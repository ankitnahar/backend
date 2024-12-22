<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;
use DB;
class Button extends Model
{
    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'tab_button';
    protected $hidden = [ ];
    public $timestamps = false;    
    
    public static function buttonData($id) {
        return Button::leftJoin('tabs as t', 't.id', '=', 'tab_button.tab_id')
                ->leftJoin('designation_tab_right as dt', function($join) use ($id) {
                    $join->on('dt.tab_id', '=', 't.id');
                    $join->on('dt.designation_id', '=', DB::raw($id));
                })
                ->select(['dt.tab_id', 'tab_button.id', 't.tab_name', 'tab_button.button_label', 'tab_button.button_name', DB::raw('IF(find_IN_SET(tab_button.id,dt.other_right),1,0) as view')])
                ->where("t.parent_id", "!=", 0)
                ->where("t.is_active", 1)
                ->where("dt.view", "=", 1)
                ->whereOr("dt.add_edit", "=", 1)
                ->orderby("tab_button.tab_id");    
    }
    
    public static function userbuttonData($id) {
        return Button::leftJoin('tabs as t', 't.id', '=', 'tab_button.tab_id')
                ->leftJoin('user_tab_right as dt', function($join) use ($id) {
                    $join->on('dt.tab_id', '=', 't.id');
                    $join->on('dt.user_id', '=', DB::raw($id));
                })
                ->select(['dt.tab_id', 'tab_button.id', 't.tab_name', 'tab_button.button_label','tab_button.button_name', DB::raw('IF(find_IN_SET(tab_button.id,dt.other_right),1,0) as view')])
                ->where("t.parent_id", "!=", 0)
                ->where("t.is_active", 1)
                ->where("dt.view", "=", 1)
                ->whereOr("dt.add_edit", "=", 1)
                ->orderby("tab_button.tab_id");  
    } 
  
    
    public static function getButtonNames($bid)
    {
        return Button::select(DB::raw("group_concat(button_label) as button_label"),DB::raw("group_concat(button_name) as button_name"))
                                ->whereRaw("id IN (" . $bid . ")")
                                ->groupby('tab_id')
                                ->first();
        
    }
                   
}
