<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $fillable = ['numeroCiclo', 'ano', 'numeroGrupo', 'abierto', 'curso_id'];

    protected $casts = [
        'abierto' => 'boolean',
    ];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }

   
    public function docentes()
    {
        return $this->belongsToMany(Docente::class, 'docente_grupo')->withTimestamps();
    }

    
    public function estudiantes()
    {
        return $this->belongsToMany(Estudiante::class, 'estudiante_matriculados')
                    ->withPivot('nota')
                    ->withTimestamps();
    }

    public function matriculas()
    {
        return $this->hasMany(EstudianteMatriculado::class);
    }
}