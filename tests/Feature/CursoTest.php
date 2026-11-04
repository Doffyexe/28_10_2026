<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CursoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_curso_se_guarda_en_base_de_datos(): void
    {
        $datos = [
            'sigla' => 'IF-7100',
            'nombreCurso' => 'Ingeniería del Software',
            'creditos' => 4,
        ];

        $response = $this->postJson('/api/cursos', $datos);

        $response->assertStatus(201)
            ->assertJsonFragment($datos);

        $this->assertDatabaseHas('cursos', $datos);
        $this->assertDatabaseCount('cursos', 1);
    }

    public function test_crear_curso_requiere_campos_obligatorios(): void
    {
        $response = $this->postJson('/api/cursos', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sigla', 'nombreCurso', 'creditos']);

        $this->assertDatabaseCount('cursos', 0);
    }

    public function test_no_permite_sigla_duplicada(): void
    {
        $datos = [
            'sigla' => 'IF-7100',
            'nombreCurso' => 'Ingeniería del Software',
            'creditos' => 4,
        ];

        $this->postJson('/api/cursos', $datos)->assertStatus(201);
        $this->postJson('/api/cursos', $datos)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sigla']);

        $this->assertDatabaseCount('cursos', 1);
    }
}
