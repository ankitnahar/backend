<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class EntitySpecialnotes extends Model
{
    protected $guarded = ['id'];
    protected $fillable = ['entity_id', 'service_id', 'note', 'expiry_on', 'type', 'is_active', 'created_by'];
    protected $table = 'entity_specialnotes';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy(){
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public function service_id(){
        return $this->belongsTo(\App\Models\Backend\Services::class, 'service_id', 'id');
    }
    
    public function archiveBy(){
        return $this->belongsTo(\App\Models\Backend\EntitySpecialnoteArchive::class, 'id', 'entity_specialnotes_id')->with('archive_by:id,userfullname,email');
    }
}
