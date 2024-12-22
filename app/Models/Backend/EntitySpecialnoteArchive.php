<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class EntitySpecialnoteArchive extends Model
{
    protected $guarded = ['id'];
    protected $fillable = ['entity_specialnotes_id', 'archive_by', 'archive_on'];
    protected $table = 'entity_specialnotes_archive';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function archive_by(){
        return $this->belongsTo(\App\Models\User::class, 'archive_by', 'id');
    }
}
