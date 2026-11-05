<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);


function crearCurso(): Curso
{
    return Curso::create([
        'sigla' => 'IF6100',
        'nombreCurso' => 'Análisis y Diseño de Sistemas',
        'creditos' => 4,
    ]);
}

function crearGrupo01(Curso $curso, bool $abierto = false): Grupo
{
    return Grupo::create([
        'curso_id' => $curso->id,
        'numeroCiclo' => 2,
        'ano' => 2026,
        'numeroGrupo' => 1,
        'abierto' => $abierto,
    ]);
}

function crearDocente(int $codigo): Docente
{
    $datos = [
        1001 => ['Ana', 'Mora Vargas'],
        1002 => ['Luis', 'Rojas Solano'],
        1003 => ['Carla', 'Jiménez Araya'],
    ];

    return Docente::create([
        'codEmpleado' => $codigo,
        'nombreEmpleado' => $datos[$codigo][0],
        'apellidosEmpleado' => $datos[$codigo][1],
    ]);
}

function matricularEstudiantes(Grupo $grupo, int $cantidad, Docente $consejero): void
{
    Estudiante::factory()
        ->count($cantidad)
        ->create(['consejero_id' => $consejero->id])
        ->each(fn (Estudiante $estudiante) => $grupo->matricular($estudiante));
}



it('CP-01: registra un grupo con toda su información y su curso', function () {
    $curso = crearCurso();


    $creado = crearGrupo01($curso, abierto: true);

    $grupo = Grupo::find($creado->id);

    expect($grupo->numeroCiclo)->toBe(2)
        ->and($grupo->ano)->toBe(2026)
        ->and($grupo->numeroGrupo)->toBe(1)
        ->and($grupo->abierto)->toBeTrue()->toBeBool(); 

    expect($grupo->curso)->toBeInstanceOf(Curso::class)
        ->and($grupo->curso->sigla)->toBe('IF6100')
        ->and($grupo->curso->creditos)->toBe(4);

    $this->assertDatabaseCount('grupos', 1);
});


it('CP-02: un curso tiene varios grupos y se distinguen los abiertos', function () {
    $curso = crearCurso();

    foreach ([1 => true, 2 => true, 3 => false] as $numero => $abierto) {
        $curso->grupos()->create([
            'numeroCiclo' => 2,
            'ano' => 2026,
            'numeroGrupo' => $numero,
            'abierto' => $abierto,
        ]);
    }

    $grupos = $curso->fresh()->grupos;

    $abiertos = $grupos->where('abierto', true);

    expect($grupos)->toHaveCount(3)
        ->and($abiertos)->toHaveCount(2)
        ->and($abiertos->pluck('numeroGrupo')->sort()->values()->all())->toBe([1, 2]);

    expect($grupos->pluck('curso_id')->unique()->all())->toBe([$curso->id]);
});


it('CP-03: un grupo acepta dos docentes', function () {
    $grupo = crearGrupo01(crearCurso());
    $docente1001 = crearDocente(1001);
    $docente1002 = crearDocente(1002);

    $grupo->asignarDocente($docente1001);
    $grupo->asignarDocente($docente1002);

    expect($grupo->docentes)->toHaveCount(2)
        ->and($grupo->docentes->pluck('codEmpleado')->sort()->values()->all())->toBe([1001, 1002]);

    expect($docente1001->grupos()->pluck('grupos.id')->all())->toContain($grupo->id);

    $this->assertDatabaseCount('docente_grupo', 2);
});


it('CP-04: un grupo rechaza un tercer docente', function () {
    $grupo = crearGrupo01(crearCurso());
    $grupo->asignarDocente(crearDocente(1001));
    $grupo->asignarDocente(crearDocente(1002));
    $docente1003 = crearDocente(1003);

    expect(fn () => $grupo->asignarDocente($docente1003))
        ->toThrow(DomainException::class);

    expect($grupo->fresh()->docentes)->toHaveCount(2);
    $this->assertDatabaseMissing('docente_grupo', ['docente_id' => $docente1003->id]);
});


it('CP-05: un grupo con 14 estudiantes no puede abrirse', function () {
    $grupo = crearGrupo01(crearCurso());
    $docente1001 = crearDocente(1001);
    $grupo->asignarDocente($docente1001);
    matricularEstudiantes($grupo, 14, $docente1001);

    expect($grupo->puedeAbrirse())->toBeFalse();

    expect(fn () => $grupo->abrir())->toThrow(DomainException::class);

    expect($grupo->fresh()->abierto)->toBeFalse();
    $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'abierto' => false]);
});


it('CP-06: un grupo con 15 estudiantes y un docente se abre', function () {
    $grupo = crearGrupo01(crearCurso());
    $docente1001 = crearDocente(1001);
    $grupo->asignarDocente($docente1001);
    matricularEstudiantes($grupo, 15, $docente1001);

    expect($grupo->puedeAbrirse())->toBeTrue();

    $grupo->abrir();

    expect($grupo->fresh()->abierto)->toBeTrue();
    $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'abierto' => true]);
});
