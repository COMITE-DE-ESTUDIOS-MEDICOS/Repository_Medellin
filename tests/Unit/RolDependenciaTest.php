<?php

namespace Tests\Unit;

use App\Enums\RolDependencia;
use Tests\TestCase;

/**
 * Los tres roles y qué puede cada uno. Es la tabla de la que cuelgan todas
 * las Policies, así que se prueba sola y sin base de datos.
 */
class RolDependenciaTest extends TestCase
{
    public function test_solo_edicion_y_administracion_pueden_editar(): void
    {
        $this->assertFalse(RolDependencia::Lectura->puedeEditar());
        $this->assertTrue(RolDependencia::Edicion->puedeEditar());
        $this->assertTrue(RolDependencia::Administracion->puedeEditar());
    }

    public function test_solo_administracion_puede_administrar(): void
    {
        $this->assertFalse(RolDependencia::Lectura->puedeAdministrar());
        $this->assertFalse(RolDependencia::Edicion->puedeAdministrar());
        $this->assertFalse(RolDependencia::Coordinacion->puedeAdministrar());
        $this->assertTrue(RolDependencia::Administracion->puedeAdministrar());
    }

    /** La frontera que separa a Coordinación de Edición. */
    public function test_coordinacion_y_administracion_gestionan_personas_y_estructura(): void
    {
        $this->assertFalse(RolDependencia::Lectura->puedeGestionar());
        $this->assertFalse(RolDependencia::Edicion->puedeGestionar());
        $this->assertTrue(RolDependencia::Coordinacion->puedeGestionar());
        $this->assertTrue(RolDependencia::Administracion->puedeGestionar());
    }

    public function test_coordinacion_edita_como_edicion(): void
    {
        $this->assertTrue(RolDependencia::Coordinacion->puedeEditar());
    }

    /**
     * Nadie reparte un rol por encima del suyo. Sin esta regla, Coordinación
     * podría crear un administrador y saltarse justo lo que no puede hacer.
     */
    public function test_nadie_puede_otorgar_un_rol_por_encima_del_suyo(): void
    {
        $this->assertSame([], RolDependencia::Lectura->asignables());
        $this->assertSame([], RolDependencia::Edicion->asignables());

        $this->assertSame(
            ['lectura', 'edicion', 'coordinacion'],
            array_column(RolDependencia::Coordinacion->asignables(), 'value'),
        );

        // DOC-25 (PA-03): Administración la da solo el superadministrador.
        $this->assertSame(
            ['lectura', 'edicion', 'coordinacion'],
            array_column(RolDependencia::Administracion->asignables(), 'value'),
        );
    }

    /**
     * puedeEditar() compara niveles: si alguien reordenara los números, un
     * lector podría acabar editando sin que nadie tocara una Policy.
     */
    public function test_los_niveles_van_de_menor_a_mayor_privilegio(): void
    {
        $this->assertLessThan(RolDependencia::Edicion->nivel(), RolDependencia::Lectura->nivel());
        $this->assertLessThan(RolDependencia::Administracion->nivel(), RolDependencia::Edicion->nivel());
    }

    public function test_los_valores_guardados_en_la_pivote_son_los_esperados(): void
    {
        // Si alguien renombrara un case, las filas ya guardadas en
        // dependencia_usuario dejarían de resolverse y rolEn() daría null.
        $this->assertSame(
            ['lectura', 'edicion', 'coordinacion', 'administracion'],
            array_column(RolDependencia::cases(), 'value'),
        );
    }
}
