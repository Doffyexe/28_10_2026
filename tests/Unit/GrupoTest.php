    <?php

    namespace Tests\Unit;
    use App\Models\Curso;
    use App\Models\Docente;
    use App\Models\Estudiante;
    use App\Models\EstudianteMatriculado;
    use App\Models\Grupo;
    use Illuminate\Database\Eloquent\Collection;
    use Tests\TestCase;

    use PHPUnit\Framework\TestCase;

    class GrupoTest extends TestCase
    {
        private Grupo $grupo;

        protected function setUp(): void
        {
            parent::setUp();

            $curso = new Curso([
                'sigla' => 'IF-7100',
                'nombreCurso' => 'Ingeniería del Software',
                'creditos' => 4,
            ]);

            $docente1 = new Docente([
                'codEmpleado' => 1001,
                'nombreEmpleado' => 'Ana',
                'apellidosEmpleado' => 'Rojas Mora',
            ]);

            $docente2 = new Docente([
                'codEmpleado' => 1002,
                'nombreEmpleado' => 'Carlos',
                'apellidosEmpleado' => 'Vargas Solís',
            ]);

            $this->grupo = new Grupo([
                'numeroCiclo' => 2,
                'ano' => 2026,
                'numeroGrupo' => 1,
                'abierto' => true,
            ]);

            $this->grupo->setRelation('curso', $curso);
            $this->grupo->setRelation('docentes', new Collection([$docente1, $docente2]));

            $matriculas = new Collection();
            for ($i = 1; $i <= 15; $i++) {
                $estudiante = new Estudiante([
                    'carne' => 'C' . str_pad($i, 5, '0', STR_PAD_LEFT),
                    'nombre' => "Estudiante $i",
                    'apellidos' => "Apellido $i",
                ]);
                $estudiante->setRelation('consejero', $docente1);

                $matricula = new EstudianteMatriculado(['nota' => 70.5 + $i]);
                $matricula->setRelation('estudiante', $estudiante);

                $matriculas->push($matricula);
            }

            $this->grupo->setRelation('matriculas', $matriculas);
        }

        public function test_grupo_tiene_sus_atributos(): void
        {
            $this->assertEquals(2, $this->grupo->numeroCiclo);
            $this->assertEquals(2026, $this->grupo->ano);
            $this->assertEquals(1, $this->grupo->numeroGrupo);
            $this->assertTrue($this->grupo->abierto);
        }

        public function test_grupo_pertenece_a_un_curso(): void
        {
            $this->assertInstanceOf(Curso::class, $this->grupo->curso);
            $this->assertEquals('IF-7100', $this->grupo->curso->sigla);
            $this->assertEquals('Ingeniería del Software', $this->grupo->curso->nombreCurso);
            $this->assertEquals(4, $this->grupo->curso->creditos);
        }

        public function test_grupo_es_impartido_por_uno_o_dos_docentes(): void
        {
            $cantidad = $this->grupo->docentes->count();

            $this->assertGreaterThanOrEqual(1, $cantidad);
            $this->assertLessThanOrEqual(2, $cantidad);
            $this->assertContainsOnlyInstancesOf(Docente::class, $this->grupo->docentes);
        }

        public function test_grupo_tiene_al_menos_15_estudiantes_matriculados(): void
        {
            $this->assertGreaterThanOrEqual(15, $this->grupo->matriculas->count());
            $this->assertContainsOnlyInstancesOf(EstudianteMatriculado::class, $this->grupo->matriculas);
        }

        public function test_cada_matricula_tiene_estudiante_y_nota(): void
        {
            foreach ($this->grupo->matriculas as $matricula) {
                $this->assertInstanceOf(Estudiante::class, $matricula->estudiante);
                $this->assertIsFloat($matricula->nota);
            }
        }

        public function test_cada_estudiante_tiene_un_consejero(): void
        {
            foreach ($this->grupo->matriculas as $matricula) {
                $this->assertInstanceOf(Docente::class, $matricula->estudiante->consejero);
            }
        }
    }
