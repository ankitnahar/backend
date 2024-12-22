<?php
namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Software extends Model
{
    // Table name which we used from database
    protected $table = 'software';
    protected $fillable = ['name', 'is_active'];
    protected $hidden = [];
    public $timestamps = false;
}