<?php

namespace Tests\Feature;

use App\Enums\RolDependencia;
use App\Models\Carpeta;
use App\Models\Dependencia;
use App\Models\Documento;
use App\Models\EnlaceCarga;
use App\Models\Recepcion;
use App\Models\User;
use App\Notifications\RecepcionRecibida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cargas grandes partidas en tandas de 50 (carga-tandas.js): cada tanda es
 * una petición, pero todas juntas se comportan como una sola carga.
 */
class CargaPorTandasTest extends TestCase
{
    use RefreshDatabase;

    private Dependencia $dependencia;

    private Carpeta $carpeta;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Notification::fake();

        $this->dependencia = Dependencia::factory()->create();
        $this->carpeta = Carpeta::factory()->create(['dependencia_id' => $this->dependencia->id]);
        $this->editor = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
    }

    private function archivos(int $desde, int $cuantos): array
    {
        return array_map(fn ($i) => $this->archivoPdf("acta-{$i}.pdf", "CONTENIDO {$i}"), range($desde, $desde + $cuantos - 1));
    }

    private function tanda(string $lote, int $parte, int $partes, int $total, array $archivos): array
    {
        return [
            'carpeta_id' => $this->carpeta->id,
            'archivo' => $archivos,
            'lote' => $lote,
            'lote_parte' => $parte,
            'lote_partes' => $partes,
            'lote_archivos' => $total,
        ];
    }

    /**
     * PHP tira sin avisar lo que pasa de max_file_uploads. Como el formulario
     * manda un nombre por archivo, menos archivos que nombres delata la pérdida:
     * no se guarda nada y se dice por qué, en vez de guardar la mitad.
     */
    public function test_si_llegan_menos_archivos_que_nombres_no_se_guarda_nada(): void
    {
        $this->actingAs($this->editor)->post(route('documentos.store'), [
            'carpeta_id' => $this->carpeta->id,
            'archivo' => $this->archivos(1, 2),
            'nombres' => ['Uno', 'Dos', 'Tres', 'Cuatro'],
        ])->assertSessionHasErrors(['archivo' => 'El servidor recibió 2 de 4 archivos y no se guardó nada. Vuelve a intentarlo con menos archivos por vez, o avisa a soporte (límite de PHP: max_file_uploads).']);

        $this->assertSame(0, Documento::count());
    }

    public function test_la_tanda_nunca_pasa_del_limite_de_php(): void
    {
        $this->assertLessThanOrEqual((int) ini_get('max_file_uploads'), \App\Services\CargaPorTandas::porTanda());
        $this->assertLessThanOrEqual(config('repositorio.carga.por_tanda'), \App\Services\CargaPorTandas::porTanda());
    }

    public function test_tres_tandas_cargan_todo_y_la_ultima_dice_a_donde_volver(): void
    {
        $lote = (string) Str::uuid();

        $this->actingAs($this->editor)
            ->postJson(route('documentos.store'), $this->tanda($lote, 1, 3, 120, $this->archivos(1, 50)))
            ->assertOk()->assertExactJson(['cargados' => 50]);

        $this->actingAs($this->editor)
            ->postJson(route('documentos.store'), $this->tanda($lote, 2, 3, 120, $this->archivos(51, 50)))
            ->assertOk();

        $ultima = $this->actingAs($this->editor)
            ->postJson(route('documentos.store'), $this->tanda($lote, 3, 3, 120, $this->archivos(101, 20)));

        $ultima->assertOk()->assertJson([
            'cargados' => 20,
            'total' => 120,
            'redirect' => route('documentos.index', ['carpeta' => $this->carpeta->uuid]),
        ]);
        $ultima->assertSessionHas('exito', '120 documentos cargados.');
        $this->assertSame(120, Documento::count());
    }

    public function test_reintentar_una_tanda_que_si_habia_llegado_no_duplica(): void
    {
        $lote = (string) Str::uuid();
        $tanda = $this->tanda($lote, 1, 2, 60, $this->archivos(1, 50));

        $this->actingAs($this->editor)->postJson(route('documentos.store'), $tanda)->assertOk();
        // Se cortó la red justo al responder: el navegador la manda otra vez.
        $this->actingAs($this->editor)
            ->postJson(route('documentos.store'), $this->tanda($lote, 1, 2, 60, $this->archivos(1, 50)))
            ->assertExactJson(['cargados' => 0]);

        $this->assertSame(50, Documento::count());
    }

    public function test_sin_tandas_el_mismo_archivo_dos_veces_se_carga_dos_veces(): void
    {
        // La protección contra duplicados es solo dentro de una carga.
        foreach ([1, 2] as $vez) {
            $this->actingAs($this->editor)->post(route('documentos.store'), [
                'carpeta_id' => $this->carpeta->id,
                'archivo' => [$this->archivoPdf('acta.pdf', 'MISMO CONTENIDO')],
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Documento::count());
    }

    public function test_cada_tanda_sigue_limitada_a_50(): void
    {
        $this->actingAs($this->editor)
            ->postJson(route('documentos.store'), $this->tanda((string) Str::uuid(), 1, 1, 51, $this->archivos(1, 51)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }

    public function test_en_un_enlace_de_un_solo_uso_todas_las_tandas_cuentan_como_un_envio(): void
    {
        $token = EnlaceCarga::generarToken();
        $creador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
        EnlaceCarga::factory()->hacia($this->carpeta)->conToken($token)->creadoPor($creador)->create(['max_usos' => 1]);
        $lote = (string) Str::uuid();
        $identidad = ['remitente_nombre' => 'Ana', 'remitente_email' => 'ana@x.co', 'remitente_entidad' => 'X', 'nodo' => 1];

        $this->postJson(route('envio.recibir', ['token' => $token]), $identidad + $this->tanda($lote, 1, 2, 60, $this->archivos(1, 50)))
            ->assertOk()->assertExactJson(['recibidos' => 50]);
        Notification::assertNothingSent();

        // El único uso ya se gastó, pero esta tanda es de la misma carga: entra.
        $this->postJson(route('envio.recibir', ['token' => $token]), $identidad + $this->tanda($lote, 2, 2, 60, $this->archivos(51, 10)))
            ->assertOk()->assertJson(['redirect' => route('envio.confirmacion')]);

        $this->assertSame(60, Recepcion::withoutGlobalScopes()->count());
        $this->assertSame(1, EnlaceCarga::withoutGlobalScopes()->sole()->usos);
        Notification::assertSentToTimes($creador, RecepcionRecibida::class, 1);

        // La confirmación muestra los 60, no solo los 10 de la última tanda.
        $this->get(route('envio.confirmacion'))->assertOk()->assertSee('acta-1.pdf')->assertSee('acta-60.pdf');

        // Una carga nueva con ese enlace ya no entra.
        $this->postJson(route('envio.recibir', ['token' => $token]), $identidad + $this->tanda((string) Str::uuid(), 1, 1, 1, $this->archivos(61, 1)))
            ->assertNotFound();
    }
}
