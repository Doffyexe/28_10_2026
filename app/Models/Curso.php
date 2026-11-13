<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    use HasFactory;

    protected $fillable = ['sigla', 'nombreCurso', 'creditos'];

    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }
}
