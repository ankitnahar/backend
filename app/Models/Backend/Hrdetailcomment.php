<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class Hrdetailcomment extends Model
{
    protected $guarded = ['id'];
    protected $fillable = ['hr_detail_id', 'status', 'type', 'comment', 'comment_by', 'comment_on'];
    protected $table = 'hr_detail_comment';
    protected $hidden = [ ];
    public $timestamps = false;

    public function comment_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'comment_by', 'id');
    }
}