<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docente extends Model
{
    protected $fillable = ['codEmpleado', 'nombreEmpleado', 'apellidosEmpleado'];

   
    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'docente_grupo')->withTimestamps();
    }

    
    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class, 'consejero_id');
    }
}
