<?php

namespace Tests\Feature\Turnos;

use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Las piezas de la identificación por cédula, que comparten el enlace de
 * turno y el de evidencias.
 *
 * Mientras la fase 0 no esté —sin Componente ni Nodo—, el componente llega
 * como un objeto de prueba con la misma forma: lo que se prueba aquí es lo
 * que pinta la vista, no de dónde salen los nodos.
 */
class PublicoCamposTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Lo que en una petición de verdad comparte el grupo web.
        $this->withViewErrors([]);
    }

    /** Con la misma forma que un Componente: sus nodos y sus cargos. */
    private function componente(array $nodos, array $cargos = []): object
    {
        return new class(Collection::make($nodos)->map(fn (array $nodo) => (object) $nodo), $cargos)
        {
            public function __construct(public Collection $nodos, private array $listaDeCargos)
            {
            }

            public function cargos(): array
            {
                return $this->listaDeCargos;
            }
        };
    }

    /** La etiqueta de apertura del control con ese name, para mirarle los atributos. */
    private function control(string $html, string $nombre): string
    {
        $patron = '/<(input|select)\b[^>]*\bname="'.preg_quote($nombre, '/').'"[^>]*>/';

        $this->assertMatchesRegularExpression($patron, $html, "No se pintó el campo «{$nombre}».");
        preg_match($patron, $html, $coincidencia);

        return $coincidencia[0];
    }

    public function test_el_selector_ofrece_los_nodos_del_componente_y_no_una_lista_fija(): void
    {
        $componente = $this->componente([
            ['id' => 31, 'nombre' => 'Nodo Centro', 'orden' => 2, 'activo' => true],
            ['id' => 30, 'nombre' => 'Nodo Norte', 'orden' => 1, 'activo' => true],
            ['id' => 32, 'nombre' => 'Nodo Cerrado', 'orden' => 3, 'activo' => false],
            ['id' => 33, 'nombre' => 'Nodo Siete', 'orden' => 7, 'activo' => true],
        ]);

        $vista = $this->blade('<x-campos.selector-nodo :componente="$componente" />', ['componente' => $componente]);

        $vista->assertSeeInOrder(['Nodo Norte', 'Nodo Centro', 'Nodo Siete']);
        $vista->assertSee('value="30"', false);
        $vista->assertDontSee('Nodo Cerrado');

        // Lo que pintaba el range(1, 6) de antes.
        $vista->assertDontSee('Nodo 1');
        $vista->assertDontSee('value="1"', false);

        $this->assertStringContainsString('required', $this->control((string) $vista, 'colaborador[nodo_id]'));
    }

    public function test_sin_nodos_no_hay_selector(): void
    {
        $this->blade('<x-campos.selector-nodo :componente="$componente" />', ['componente' => $this->componente([])])
            ->assertDontSee('<select', false);

        $this->blade('<x-campos.selector-nodo />')->assertDontSee('<select', false);
    }

    public function test_la_identificacion_es_obligatoria_y_abre_el_teclado_numerico(): void
    {
        $vista = $this->blade('<x-campos.cedula consulta="/t/abc/cedula" />');

        $cedula = $this->control((string) $vista, 'cedula');

        $this->assertStringContainsString('required', $cedula);
        $this->assertStringContainsString('inputmode="numeric"', $cedula);
        $this->assertStringContainsString('autocomplete="off"', $cedula);

        $vista->assertSee('No soy yo');
    }

    public function test_la_consulta_va_a_la_ruta_que_le_da_cada_enlace(): void
    {
        $this->blade('<x-campos.cedula consulta="/enviar/xyz/cedula" />')
            ->assertSee('data-consulta="/enviar/xyz/cedula"', false)
            ->assertSee('data-csrf="', false);
    }

    /** Obligatorios: identificación (arriba), nombre y nodo. Lo demás, opcional. */
    public function test_el_registro_pide_nombre_nodo_y_cargo_y_lo_demas_es_opcional(): void
    {
        $componente = $this->componente([
            ['id' => 30, 'nombre' => 'Nodo Norte', 'orden' => 1, 'activo' => true],
        ], ['Conductor camioneta', 'Monitor de ruta']);

        $html = (string) $this->blade(
            '<x-campos.cedula consulta="/t/abc/cedula" :componente="$componente" />',
            ['componente' => $componente],
        );

        $this->assertStringContainsString('required', $this->control($html, 'colaborador[nombre]'));
        $this->assertStringContainsString('required', $this->control($html, 'colaborador[nodo_id]'));

        // El cargo es obligatorio y sale de la lista del componente (PLAN.md §8.1).
        $this->assertStringContainsString('required', $this->control($html, 'colaborador[cargo]'));
        $this->assertStringContainsString('<option value="Monitor de ruta"', $html);

        foreach (['correo', 'telefono', 'entidad'] as $opcional) {
            $this->assertStringNotContainsString(
                'required',
                $this->control($html, "colaborador[{$opcional}]"),
                "«{$opcional}» debía ser opcional.",
            );
        }

        $this->assertStringContainsString('type="email"', $this->control($html, 'colaborador[correo]'));
        $this->assertStringContainsString('type="tel"', $this->control($html, 'colaborador[telefono]'));
    }

    public function test_con_errores_del_registro_vuelve_abierto(): void
    {
        $this->blade('<x-campos.cedula consulta="/t/abc/cedula" />')
            ->assertDontSee('data-registro-abierto', false);

        $this->withViewErrors(['colaborador.nombre' => 'Escribe tu nombre.'])
            ->blade('<x-campos.cedula consulta="/t/abc/cedula" />')
            ->assertSee('data-registro-abierto', false);
    }
}
