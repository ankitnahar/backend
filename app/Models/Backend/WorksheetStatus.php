<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;
use DB;
class WorksheetStatus extends Model
{
    protected $guarded = [ ];
    protected $fillable = [];

    protected $table = 'worksheet_status';
    protected $hidden = [ ];
    public $timestamps = false;    
    
    //user wise worksheet data
    public static function userworksheetData($id) {
    return WorksheetStatus::leftJoin('worksheet_status_user_right as wr', function($join) use ($id) {
                    $join->on('wr.worksheet_status_id', '=', 'worksheet_status.id');
                    $join->on('wr.user_id', '=', DB::raw($id));
                })
                ->select(['worksheet_status.id', 'worksheet_status.status_name', 'wr.right'])
                ->where("worksheet_status.is_active", "=", 1)
                ->orderby("worksheet_status.status_name", "asc");
    }
    
    public static function worksheetData($id) {
    return WorksheetStatus::leftJoin('designation_worksheet_status_right as wd', function($join) use ($id) {
                    $join->on('wd.worksheet_status_id', '=', 'worksheet_status.id');
                    $join->on('wd.designation_id', '=', DB::raw($id));
                })
                ->select(['worksheet_status.id', 'worksheet_status.status_name', 'wd.right'])
                ->where("worksheet_status.is_active", "=", 1)
                ->orderby("worksheet_status.status_name", "asc");
    }
    
    //get status name as per id
    
    public static function getname($id){
         $status  = WorksheetStatus::select("status_name")->where("id", $id)->first();
         return $status->status_name;
    }
   
}

