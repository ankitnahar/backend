<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Frequency extends Model
{
    protected $guarded = ['id'];
    protected $fillable = [];
    protected $hidden = [ ];
    public $timestamps = false;
    protected $table = 'frequency';
    
    public static function getFrequency(){
        return Frequency::where('is_active', 1)->get()->pluck('name', 'id')->toArray();
    }
    
}
