<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;
use DB;
class UserFieldRight extends Model
{
    protected $guarded = [ ];
    protected $fillable = [];

    protected $table = 'user_field_right';
    protected $hidden = [ ];
    public $timestamps = false;    
    
    //check field value exist or not
    public  static function checkright($field_id,$id){
        return UserFieldRight::leftjoin("dynamic_field as df", "df.id", "user_field_right.field_id")
                                ->select("user_field_right.id", "df.field_title", "user_field_right.view", "user_field_right.add_edit")
                                ->where("user_field_right.field_id", "=", $field_id)
                                ->where("user_field_right.user_id", "=", $id)->first();
    }
    
    
    //user wise field data
    public static function getGroupfieldData($id,$group_id) {
        return UserFieldRight::leftjoin("dynamic_field", "dynamic_field.id", "user_field_right.field_id")
                        ->select(['dynamic_field.*', 'user_field_right.view', 'user_field_right.add_edit'])
                        ->where("user_field_right.user_id", "=", $id)
                        ->where("dynamic_field.group_id", $group_id)
                        ->where("dynamic_field.is_active", "1")
                        ->where("dynamic_field.disable", "0");
    }
    
    
}
