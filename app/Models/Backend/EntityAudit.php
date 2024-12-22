<?php
namespace App\Models\Backend;
use Illuminate\Database\Eloquent\Model;

class EntityAudit extends Model
{
    protected $guarded = ['id'];
    protected $fillable = ['id','entity_id','tab','changes','modified_by','modified_on'];
    protected $table = 'entity_audit';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function modifiedBy(){
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
