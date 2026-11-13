<?php

namespace App\Models;

use DomainException;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    public const MIN_DOCENTES = 1;
    public const MAX_DOCENTES = 2;

    public const MIN_ESTUDIANTES = 15;

    public const NOTA_MINIMA = 0;
    public const NOTA_MAXIMA = 10;

    protected $fillable = ['numeroCiclo', 'ano', 'numeroGrupo', 'abierto', 'curso_id'];

    protected $casts = [
        'numeroCiclo' => 'integer',
        'ano' => 'integer',
        'numeroGrupo' => 'integer',
        'abierto' => 'boolean',
    ];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }

    public function docentes()
    {
        return $this->belongsToMany(Docente::class, 'grupo_docente')->withTimestamps();
    }

    public function estudiantes()
    {
        return $this->belongsToMany(Estudiante::class, 'estudiantes_matriculados')
                    ->using(EstudianteMatriculado::class)
                    ->withPivot('nota')
                    ->withTimestamps();
    }

    public function matriculas()
    {
        return $this->hasMany(EstudianteMatriculado::class);
    }

    /**
     *
     * @throws DomainException si el grupo ya tiene 2 docentes
     */
    public function asignarDocente(Docente $docente): void
    {
        if ($this->docentes()->count() >= self::MAX_DOCENTES) {
            throw new DomainException('Un grupo no puede tener más de ' . self::MAX_DOCENTES . ' docentes.');
        }

        $this->docentes()->attach($docente->id);
        $this->unsetRelation('docentes');
    }


    public function matricular(Estudiante $estudiante): void
    {
        $this->estudiantes()->attach($estudiante->id, ['nota' => null]);
        $this->unsetRelation('estudiantes');
    }


    public function puedeAbrirse(): bool
    {
        $docentes = $this->docentes()->count();

        return $docentes >= self::MIN_DOCENTES
            && $docentes <= self::MAX_DOCENTES
            && $this->estudiantes()->count() >= self::MIN_ESTUDIANTES;
    }

    /**
     *
     * @throws DomainException si el grupo no cumple las condiciones para abrirse
     */
    public function abrir(): void
    {
        if (! $this->puedeAbrirse()) {
            throw new DomainException('El grupo necesita al menos ' . self::MIN_DOCENTES
                . ' docente y ' . self::MIN_ESTUDIANTES . ' estudiantes para abrirse.');
        }

        $this->abierto = true;
        $this->save();
    }

    /**
     *
     * @throws InvalidArgumentException si la nota está fuera del rango 0–10
     * @throws DomainException si el estudiante no está matriculado en el grupo
     */
    public function registrarNota(Estudiante $estudiante, float $nota): void
    {
        if ($nota < self::NOTA_MINIMA || $nota > self::NOTA_MAXIMA) {
            throw new InvalidArgumentException('La nota debe estar entre ' . self::NOTA_MINIMA
                . ' y ' . self::NOTA_MAXIMA . '.');
        }

        $actualizados = $this->estudiantes()->updateExistingPivot($estudiante->id, ['nota' => $nota]);

        if ($actualizados === 0) {
            throw new DomainException('El estudiante ' . $estudiante->carne . ' no está matriculado en este grupo.');
        }

        $this->unsetRelation('estudiantes');
    }
}
