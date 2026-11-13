<?php

use App\Models\Curso;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\EstudianteMatriculado;
use App\Models\Grupo;
use Illuminate\Database\QueryException;

mutates(Grupo::class);


function crearCurso(): Curso
{
    return Curso::factory()->create([
        'sigla' => 'IF6100',
        'nombreCurso' => 'Análisis y Diseño de Sistemas',
        'creditos' => 4,
    ]);
}

function crearGrupo01(Curso $curso, bool $abierto = false): Grupo
{
    return Grupo::factory()->for($curso)->create([
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

    return Docente::factory()->create([
        'codEmpleado' => $codigo,
        'nombreEmpleado' => $datos[$codigo][0],
        'apellidosEmpleado' => $datos[$codigo][1],
    ]);
}

function crearEstudianteC12345(Docente $consejero): Estudiante
{
    return Estudiante::factory()->create([
        'carne' => 'C12345',
        'nombre' => 'María',
        'apellidos' => 'Solís Quesada',
        'consejero_id' => $consejero->id,
    ]);
}

function matricularEstudiantes(Grupo $grupo, int $cantidad, Docente $consejero): void
{
    Estudiante::factory()
        ->count($cantidad)
        ->create(['consejero_id' => $consejero->id])
        ->each(fn (Estudiante $estudiante) => $grupo->matricular($estudiante));
}

function matriculaDe(Grupo $grupo, Estudiante $estudiante): ?EstudianteMatriculado
{
    return EstudianteMatriculado::where('grupo_id', $grupo->id)
        ->where('estudiante_id', $estudiante->id)
        ->first();
}



test('CP-01 registra un grupo con toda su información y su curso', function () {
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

test('CP-02 un curso tiene varios grupos y se distinguen los abiertos', function () {
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

test('CP-03 un grupo acepta dos docentes', function () {
    $grupo = crearGrupo01(crearCurso());
    $docente1001 = crearDocente(1001);
    $docente1002 = crearDocente(1002);

    $grupo->asignarDocente($docente1001);
    $grupo->asignarDocente($docente1002);

    expect($grupo->docentes)->toHaveCount(2)
        ->and($grupo->docentes->pluck('codEmpleado')->sort()->values()->all())->toBe([1001, 1002]);

    expect($docente1001->grupos()->pluck('grupos.id')->all())->toContain($grupo->id);

    $this->assertDatabaseCount('grupo_docente', 2);
});

test('CP-04 un grupo rechaza un tercer docente', function () {
    $grupo = crearGrupo01(crearCurso());
    $grupo->asignarDocente(crearDocente(1001));
    $grupo->asignarDocente(crearDocente(1002));
    $docente1003 = crearDocente(1003);

    expect(fn () => $grupo->asignarDocente($docente1003))
        ->toThrow(DomainException::class);

    expect($grupo->fresh()->docentes)->toHaveCount(2);
    $this->assertDatabaseMissing('grupo_docente', ['docente_id' => $docente1003->id]);
});

test('CP-05 un grupo con 14 estudiantes no puede abrirse', function () {
    $grupo = crearGrupo01(crearCurso());
    $docente1001 = crearDocente(1001);
    $grupo->asignarDocente($docente1001);
    matricularEstudiantes($grupo, 14, $docente1001);

    expect($grupo->puedeAbrirse())->toBeFalse();

    expect(fn () => $grupo->abrir())->toThrow(DomainException::class);

    expect($grupo->fresh()->abierto)->toBeFalse();
    $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'abierto' => false]);
});

test('CP-06 un grupo con 15 estudiantes y un docente se abre', function () {
    $grupo = crearGrupo01(crearCurso());
    $docente1001 = crearDocente(1001);
    $grupo->asignarDocente($docente1001);
    matricularEstudiantes($grupo, 15, $docente1001);

    expect($grupo->puedeAbrirse())->toBeTrue();

    $grupo->abrir();

    expect($grupo->fresh()->abierto)->toBeTrue();
    $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'abierto' => true]);
});



test('CP-07 un grupo sin docente no se abre aunque tenga cupo completo', function () {
    
    $grupo = crearGrupo01(crearCurso());
    matricularEstudiantes($grupo, 20, crearDocente(1001));

    expect($grupo->docentes)->toHaveCount(0)
        ->and($grupo->estudiantes)->toHaveCount(20)
        ->and($grupo->abierto)->toBeFalse();

    expect($grupo->puedeAbrirse())->toBeFalse();
    expect(fn () => $grupo->abrir())->toThrow(DomainException::class);

    expect($grupo->fresh()->abierto)->toBeFalse();
    $this->assertDatabaseHas('grupos', ['id' => $grupo->id, 'abierto' => false]);
});

test('CP-08 un estudiante queda ligado a su profesor consejero', function () {
    $docente1001 = crearDocente(1001);
    expect($docente1001->estudiantes)->toHaveCount(0);

    $estudiante = crearEstudianteC12345($docente1001);

    expect($estudiante->consejero)->toBeInstanceOf(Docente::class)
        ->and($estudiante->consejero->codEmpleado)->toBe(1001);

    $aconsejados = $docente1001->fresh()->estudiantes;

    expect($aconsejados)->toHaveCount(1)
        ->and($aconsejados->first()->carne)->toBe('C12345');
    $this->assertDatabaseHas('estudiantes', ['carne' => 'C12345', 'consejero_id' => $docente1001->id]);
});

test('CP-09 cambiar de consejero reemplaza al anterior, no lo suma', function () {
    $docente1001 = crearDocente(1001);
    $docente1002 = crearDocente(1002);
    $estudiante = crearEstudianteC12345($docente1001);

    $estudiante->consejero()->associate($docente1002);
    $estudiante->save();

    $docente1001 = $docente1001->fresh();
    $docente1002 = $docente1002->fresh();

    expect($estudiante->fresh()->consejero->codEmpleado)->toBe(1002);

    expect($docente1001->estudiantes)->toHaveCount(0)
        ->and($docente1002->estudiantes)->toHaveCount(1);

    $this->assertDatabaseCount('estudiantes', 1);
    $this->assertDatabaseHas('estudiantes', ['carne' => 'C12345', 'consejero_id' => $docente1002->id]);
});

test('CP-10 al matricular, la nota queda vacía', function () {
    $grupo = crearGrupo01(crearCurso());
    $estudiante = crearEstudianteC12345(crearDocente(1001));

    $grupo->matricular($estudiante);

    $pivot = $grupo->estudiantes->first()->pivot;

    expect($pivot)->toBeInstanceOf(EstudianteMatriculado::class)
        ->and($pivot->nota)->toBeNull();

    $this->assertDatabaseCount('estudiantes_matriculados', 1);
    $this->assertDatabaseHas('estudiantes_matriculados', [
        'grupo_id' => $grupo->id,
        'estudiante_id' => $estudiante->id,
        'nota' => null,
    ]);
});

test('CP-11 registra la nota al finalizar el curso', function () {
    $grupo = crearGrupo01(crearCurso());
    $estudiante = crearEstudianteC12345(crearDocente(1001));
    $grupo->matricular($estudiante);

    $antes = matriculaDe($grupo, $estudiante);
    expect($antes->nota)->toBeNull();

    $grupo->registrarNota($estudiante, 8.5);

    $matricula = matriculaDe($grupo, $estudiante);

    expect($matricula->nota)->toBe(8.5)->toBeFloat();

    expect($matricula->id)->toBe($antes->id)
        ->and($matricula->grupo_id)->toBe($antes->grupo_id)
        ->and($matricula->estudiante_id)->toBe($antes->estudiante_id);

    $this->assertDatabaseCount('estudiantes_matriculados', 1);
});

test('CP-12 rechaza notas menores que 0 o mayores que 10', function (float $nota) {
    $grupo = crearGrupo01(crearCurso());
    $estudiante = crearEstudianteC12345(crearDocente(1001));
    $grupo->matricular($estudiante);

    expect(fn () => $grupo->registrarNota($estudiante, $nota))
        ->toThrow(InvalidArgumentException::class);

    expect(matriculaDe($grupo, $estudiante)->nota)->toBeNull();
    $this->assertDatabaseHas('estudiantes_matriculados', [
        'grupo_id' => $grupo->id,
        'estudiante_id' => $estudiante->id,
        'nota' => null,
    ]);
})->with([-1, 10.5]);

test('CP-13 un estudiante no se matricula dos veces en el mismo grupo', function () {
    $grupo = crearGrupo01(crearCurso());
    $estudiante = crearEstudianteC12345(crearDocente(1001));
    $grupo->matricular($estudiante);

    expect(fn () => $grupo->matricular($estudiante))
        ->toThrow(QueryException::class);

    expect(EstudianteMatriculado::where('grupo_id', $grupo->id)
        ->where('estudiante_id', $estudiante->id)
        ->count())->toBe(1);
});



// C3 · Tests agregados para eliminar los mutantes sobrevivientes de Grupo.

test('C3-01 el mensaje al rechazar un tercer docente indica el máximo', function () {
    $grupo = crearGrupo01(crearCurso());
    $grupo->asignarDocente(crearDocente(1001));
    $grupo->asignarDocente(crearDocente(1002));

    expect(fn () => $grupo->asignarDocente(crearDocente(1003)))
        ->toThrow(DomainException::class, 'Un grupo no puede tener más de 2 docentes.');
});

test('C3-02 el mensaje al no poder abrir indica los mínimos', function () {
    $grupo = crearGrupo01(crearCurso());

    expect(fn () => $grupo->abrir())
        ->toThrow(DomainException::class, 'El grupo necesita al menos 1 docente y 15 estudiantes para abrirse.');
});

test('C3-03 el mensaje de nota fuera de rango indica el rango', function () {
    $grupo = crearGrupo01(crearCurso());
    $estudiante = crearEstudianteC12345(crearDocente(1001));
    $grupo->matricular($estudiante);

    expect(fn () => $grupo->registrarNota($estudiante, 11))
        ->toThrow(InvalidArgumentException::class, 'La nota debe estar entre 0 y 10.');
});

test('C3-04 acepta las notas frontera 0 y 10', function (float $nota) {
    $grupo = crearGrupo01(crearCurso());
    $estudiante = crearEstudianteC12345(crearDocente(1001));
    $grupo->matricular($estudiante);

    $grupo->registrarNota($estudiante, $nota);

    expect(matriculaDe($grupo, $estudiante)->nota)->toBe($nota);
})->with([0.0, 10.0]);

test('C3-05 un grupo con 2 docentes y 15 estudiantes puede abrirse', function () {
    $grupo = crearGrupo01(crearCurso());
    $docente1001 = crearDocente(1001);
    $grupo->asignarDocente($docente1001);
    $grupo->asignarDocente(crearDocente(1002));
    matricularEstudiantes($grupo, 15, $docente1001);

    expect($grupo->puedeAbrirse())->toBeTrue();
});

test('C3-06 registrar nota a un estudiante no matriculado lanza DomainException', function () {
    $grupo = crearGrupo01(crearCurso());
    $estudiante = crearEstudianteC12345(crearDocente(1001));

    expect(fn () => $grupo->registrarNota($estudiante, 8))
        ->toThrow(DomainException::class, 'El estudiante C12345 no está matriculado en este grupo.');

    $this->assertDatabaseCount('estudiantes_matriculados', 0);
});

test('C3-07 la relación docentes se refresca al asignar un docente', function () {
    $grupo = crearGrupo01(crearCurso());
    expect($grupo->docentes)->toHaveCount(0);

    $grupo->asignarDocente(crearDocente(1001));

    expect($grupo->docentes)->toHaveCount(1);
});

test('C3-08 la relación estudiantes se refresca al matricular', function () {
    $grupo = crearGrupo01(crearCurso());
    expect($grupo->estudiantes)->toHaveCount(0);

    $grupo->matricular(crearEstudianteC12345(crearDocente(1001)));

    expect($grupo->estudiantes)->toHaveCount(1);
});

test('C3-09 la relación estudiantes muestra la nota recién registrada', function () {
    $grupo = crearGrupo01(crearCurso());
    $estudiante = crearEstudianteC12345(crearDocente(1001));
    $grupo->matricular($estudiante);
    expect($grupo->estudiantes->first()->pivot->nota)->toBeNull();

    $grupo->registrarNota($estudiante, 9);

    expect($grupo->estudiantes->first()->pivot->nota)->toBe(9.0);
});

test('C3-10 un grupo lista sus matrículas', function () {
    $grupo = crearGrupo01(crearCurso());
    $estudiante = crearEstudianteC12345(crearDocente(1001));
    $grupo->matricular($estudiante);

    $matriculas = $grupo->matriculas;

    expect($matriculas)->toHaveCount(1)
        ->and($matriculas->first())->toBeInstanceOf(EstudianteMatriculado::class)
        ->and($matriculas->first()->estudiante_id)->toBe($estudiante->id);
});
