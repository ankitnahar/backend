<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Category extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'category';
    protected $hidden = [];
    public $timestamps = false;

    public static function getCategory() {
        return Category::where('is_active', 1)->get()->pluck('name', 'id')->toArray();
    }

}
