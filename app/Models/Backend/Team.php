<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;
use DB;
class Team extends Model
{
    protected $guarded = [ ];
    protected $fillable = [];

    protected $table = 'team';
    protected $hidden = [ ];
    public $timestamps = false;    
    
    //get team designation wise
    public static function getteamdetail($designation_id) {
        $team = new Team;
        if (!in_array($designation_id, array(9, 10, 14, 15, 22, 59, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 75)))
            $team = $team->whereRaw('id NOT IN (1,2,6)');
        return $team = $team->pluck('team_name', 'id')->prepend('Please Select', '0');
    }
    
    
}
