<?php
namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class ReportField extends Model
{
    // Table name which we used from database
    protected $guarded = [ ];
    protected $table = 'report_field';
    protected $hidden = [];
    public $timestamps = false;   
    
    
}