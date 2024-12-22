<?php
namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    // Table name which we used from database
    protected $table = 'state';
    protected $fillable = [];
    protected $hidden = [];
    public $timestamps = false;
    
    
}