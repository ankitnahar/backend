<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;
use DB;
class WorksheetTaskchecklistOutcome extends Model
{
    protected $guarded = [ ];
    protected $fillable = [];

    protected $table = 'worksheet_taskchecklist_outcome';
    protected $hidden = [ ];
    public $timestamps = false;   
}

