<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;
use DB;
class UserTabRight extends Model
{
    protected $guarded = [ ];

    protected $table = 'user_tab_right';
    protected $hidden = [ ];
    public $timestamps = false;    
    
    public static function checkRight($tab_id,$id) {
        return UserTabRight::leftjoin('tabs as bt', "bt.id", "user_tab_right.tab_id")
                                ->select("user_tab_right.id", "bt.tab_name", "user_tab_right.view", "user_tab_right.add_edit","user_tab_right.delete","user_tab_right.export","user_tab_right.download", "user_tab_right.other_right")
                                ->where("user_tab_right.tab_id", "=", $tab_id)
                                ->where("user_tab_right.user_id", "=", $id)->first();
    }   
    
    
    
    
}
