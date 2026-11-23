<?php

namespace Tests\Feature\Turnos;

use App\Enums\RolDependencia;
use App\Http\Controllers\Admin\Turnos\CargaMasivaCasosController as Carga;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Models\Dependencia;
use App\Models\Derivacion;
use App\Models\Documento;
use App\Models\Nodo;
use App\Models\User;
use App\Support\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/** DOC-24 · CAR-02 y CAR-03: carga única de casos ya atendidos, desde un Excel con las imágenes en las celdas. */
class CargaMasivaCasosTest extends TestCase
{
    use RefreshDatabase;

    /** Un PNG de 1×1. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private Dependencia $dependencia;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('repositorio.disco'));
        Carbon::setTestNow('2026-11-17 10:00:00');

        $this->dependencia = Dependencia::factory()->create();
        $calle = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Calle']);
        $nodo = Nodo::factory()->create(['componente_id' => $calle->id, 'nombre' => 'Nodo 2']);
        Colaborador::factory()->create([
            'dependencia_id' => $this->dependencia->id, 'componente_id' => $calle->id, 'nodo_id' => $nodo->id,
            'documento' => '1017000111', 'nombre' => 'José Amaya Pérez', 'cargo' => 'Monitor de ruta',
        ]);
        $this->coordinador = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia);
    }

    /** Una fila válida (las 14 columnas de texto); $cambios por índice de columna. */
    private function fila(array $cambios = []): array
    {
        return array_replace([
            'calle', 'Habitante de calle requiere traslado.', 'alcaldia', null, 5, 'Parque de Castilla',
            '13/11/2026 14:30', '13/11/2026 16:00', 'nodo 2', 1017000111, 'Se trasladó a la persona al centro día.', 'Sí', null, null,
        ], $cambios);
    }

    /**
     * El Excel con sus imágenes. $imagenes: por fila (desde 0), las columnas
     * que llevan imagen (14 soporte, 15 a 17 evidencias). «En la celda» es
     * como las pone Excel con «Colocar en la celda»; si no, flotantes.
     */
    private function excel(array $filas, ?array $imagenes = null, bool $enCelda = true): UploadedFile
    {
        $imagenes ??= array_fill(0, count($filas), [14, 15, 16, 17]);
        $png = base64_decode(self::PNG);

        if (! $enCelda) {
            foreach ($filas as $i => $fila) {
                foreach ($imagenes[$i] ?? [] as $columna) {
                    $filas[$i][$columna] = ['imagen' => $png, 'ancho' => 10, 'alto' => 10];
                }
                $filas[$i] = array_replace(array_fill(0, 18, null), $filas[$i]);
            }
        }

        $ruta = Xlsx::guardar(tempnam(sys_get_temp_dir(), 'casos'), 'Casos', Carga::ENCABEZADOS, $filas);

        if ($enCelda) {
            $this->ponerEnCeldas($ruta, $imagenes, $png);
        }

        return new UploadedFile($ruta, 'casos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** Las partes que escribe Excel para una imagen dentro de la celda (vm → metadata → richData → media). */
    private function ponerEnCeldas(string $ruta, array $imagenes, string $png): void
    {
        $zip = new ZipArchive;
        $zip->open($ruta);
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $n = 0;
        $futuros = $valores = $rv = $rel = $rels = '';

        foreach ($imagenes as $i => $columnas) {
            $celdas = '';
            foreach ($columnas as $columna) {
                $n++;
                $celdas .= '<c r="'.Xlsx::columna($columna).($i + 2).'" t="e" vm="'.$n.'"><v>#VALUE!</v></c>';
                $futuros .= '<bk><extLst><ext uri="{3e2802c4-a4d2-4d8b-9148-e3be6c30e623}"><xlrd:rvb i="'.($n - 1).'"/></ext></extLst></bk>';
                $valores .= '<bk><rc t="1" v="'.($n - 1).'"/></bk>';
                $rv .= '<rv s="0"><v>'.($n - 1).'</v><v>5</v></rv>';
                $rel .= '<rel r:id="rId'.$n.'"/>';
                $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/celda'.$n.'.png"/>';
                $zip->addFromString("xl/media/celda{$n}.png", $png);
            }
            $hoja = preg_replace('#(<row r="'.($i + 2).'"[^>]*>.*?)</row>#s', '$1'.$celdas.'</row>', $hoja, 1);
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', $hoja);
        $zip->addFromString('xl/metadata.xml', '<metadata xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:xlrd="http://schemas.microsoft.com/office/spreadsheetml/2017/richdata">'
            .'<metadataTypes count="1"><metadataType name="XLRICHVALUE"/></metadataTypes>'
            .'<futureMetadata name="XLRICHVALUE">'.$futuros.'</futureMetadata><valueMetadata>'.$valores.'</valueMetadata></metadata>');
        $zip->addFromString('xl/richData/rdrichvalue.xml', '<rvData xmlns="http://schemas.microsoft.com/office/spreadsheetml/2017/richdata">'.$rv.'</rvData>');
        $zip->addFromString('xl/richData/rdrichvaluestructure.xml', '<rvStructures xmlns="http://schemas.microsoft.com/office/spreadsheetml/2017/richdata">'
            .'<s t="_localImage"><k n="_rvRel:LocalImageIdentifier" t="i"/><k n="CalcOrigin" t="i"/></s></rvStructures>');
        $zip->addFromString('xl/richData/richValueRel.xml', '<richValueRels xmlns="http://schemas.microsoft.com/office/spreadsheetml/2022/richvaluerel" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.$rel.'</richValueRels>');
        $zip->addFromString('xl/richData/_rels/richValueRel.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->close();
    }

    private function cargar(UploadedFile $excel, ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->coordinador)->post(route('admin.turnos.derivaciones.carga.importar'), ['archivo' => $excel]);
    }

    public function test_doc24_ca04_el_formato_se_descarga_y_solo_lo_ve_coordinacion(): void
    {
        $formato = $this->actingAs($this->coordinador)->get(route('admin.turnos.derivaciones.carga.formato'))
            ->assertOk()->assertDownload('formato_carga_de_casos_atendidos.xlsx');

        // Las columnas de valores fijos traen lista desplegable.
        $zip = new ZipArchive;
        $zip->open($formato->baseResponse->getFile()->getPathname());
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertSame(5, substr_count($hoja, '<dataValidation '));
        $this->assertStringContainsString('sqref="I2:I1000"><formula1>&apos;Valores válidos&apos;!$B$2:$B$2</formula1>', $hoja);
        $this->assertStringContainsString('sqref="L2:L1000"><formula1>&quot;Sí,No&quot;</formula1>', $hoja);
        $this->actingAs($this->coordinador)->get(route('admin.turnos.derivaciones.index'))->assertSee('Cargar casos atendidos');

        $lider = $this->usuarioCon(RolDependencia::Edicion, $this->dependencia);
        $this->actingAs($lider)->get(route('admin.turnos.derivaciones.index'))->assertOk()->assertDontSee('Cargar casos atendidos');
        $this->cargar($this->excel([$this->fila()]), $lider)->assertForbidden();

        // Pasada la fecha de cierre, tampoco Coordinación.
        Carbon::setTestNow('2026-12-01 08:00:00');
        $this->cargar($this->excel([$this->fila()]))->assertForbidden();
        $this->assertSame(0, Derivacion::withoutGlobalScopes()->count());
    }

    public function test_doc24_ca05_ca09_ca10_cada_fila_nace_como_caso_atendido_con_las_horas_y_las_imagenes_del_archivo(): void
    {
        Notification::fake();
        // Ya hay un caso: los cargados siguen el consecutivo.
        Derivacion::withoutGlobalScopes()->create([
            'dependencia_id' => $this->dependencia->id, 'componente_id' => Componente::first()->id, 'numero' => 4, 'remitente' => 'alcaldia',
            'entidad' => 'Alcaldía', 'descripcion' => 'Otro', 'ubicacion' => 'Otra parte', 'estado' => Derivacion::SOLUCIONADA,
            'fecha_requerimiento' => '2026-11-15', 'hora_requerimiento' => '09:00', 'tiempo_respuesta_min' => 10,
        ]);

        $this->cargar($this->excel([
            $this->fila(),
            // Sin soporte, «Otra», más de 3 horas con justificación, «No» con observación, fecha como número de Excel.
            $this->fila([2 => 'Otra', 3 => 'Policía', 4 => '5 · Castilla', 5 => 'La Alpujarra', 6 => 46339.3541667, 7 => '13/11/2026 13:00', 11 => 'no', 12 => 'No se encontró a la persona.', 13 => 'Vía cerrada.']),
        ], [[14, 15, 16, 17], [15, 16, 17]]))
            ->assertSessionHasNoErrors()->assertSessionMissing('carga_errores')
            ->assertRedirect(route('admin.turnos.derivaciones.index'))
            ->assertSessionHas('exito', 'Se cargaron 2 casos: de DC-005 a DC-006.');

        $uno = Derivacion::withoutGlobalScopes()->where('numero', 5)->sole();
        $this->assertSame(
            ['solucionada', true, '2026-11-13', '14:30', '2026-11-13 16:00:00', 90, 'José Amaya Pérez', '1017000111', 'Alcaldía', 5, true, $this->coordinador->id],
            [$uno->estado, $uno->carga_masiva, $uno->fecha_requerimiento->toDateString(), substr($uno->hora_requerimiento, 0, 5), $uno->atendida_at->toDateTimeString(),
                $uno->tiempo_respuesta_min, $uno->nombre, $uno->cedula, $uno->entidad, $uno->comuna, $uno->cumplio_requerimiento, $uno->creado_por],
        );
        $this->assertSame('Nodo 2', $uno->nodo->nombre);
        $this->assertTrue($uno->cumplio(), 'Cuenta para la meta de 3 horas con sus horas reales.');

        $nombres = fn (Derivacion $d) => collect($d->archivos())->map(fn ($doc) => $doc === null ? null : Documento::withoutGlobalScopes()->find($doc->id)->nombre)->all();
        $this->assertSame([
            'Soporte' => 'Caso DC-005 · Soporte', 'Evidencia 1' => 'Caso DC-005 · Evidencia 1 · José Amaya Pérez',
            'Evidencia 2' => 'Caso DC-005 · Evidencia 2 · José Amaya Pérez', 'Evidencia 3' => 'Caso DC-005 · Evidencia 3 · José Amaya Pérez',
        ], $nombres($uno));
        $version = $uno->evidencia1()->withoutGlobalScopes()->first()->versionActual;
        $this->assertSame('image/png', $version->mime);
        Storage::disk(config('repositorio.disco'))->assertExists($version->ruta);

        $dos = Derivacion::withoutGlobalScopes()->where('numero', 6)->sole();
        $this->assertSame(
            ['otra', 'Policía', 5, '08:30', 270, false, 'No se encontró a la persona.', 'Vía cerrada.', null],
            [$dos->remitente, $dos->entidad, $dos->comuna, substr($dos->hora_requerimiento, 0, 5), $dos->tiempo_respuesta_min, $dos->cumplio_requerimiento, $dos->observacion, $dos->justificacion, $dos->soporte_documento_id],
        );

        Notification::assertNothingSent();
        $this->assertDatabaseHas('auditorias', ['accion' => 'caso.creado', 'auditable_id' => $uno->id]);
        $this->actingAs($this->coordinador)->get(route('admin.turnos.derivaciones.show', $uno))
            ->assertOk()->assertSee('Carga masiva')->assertSee('Atendido');
    }

    public function test_doc24_ca05_tambien_lee_las_imagenes_flotantes_sobre_la_celda(): void
    {
        // La fecha de inicio es libre: vale un caso de meses atrás.
        $this->cargar($this->excel([$this->fila([6 => '03/08/2026 09:05', 7 => '03/08/2026 10:00'])], enCelda: false))
            ->assertSessionMissing('carga_errores')->assertSessionHas('exito', 'Se cargó 1 caso: DC-001.');

        $caso = Derivacion::withoutGlobalScopes()->sole();
        $this->assertSame('2026-08-03 09:05', $caso->requeridaAt()->format('Y-m-d H:i'));
        $this->assertNotNull($caso->soporte_documento_id);
        $this->assertNotNull($caso->evidencia3_documento_id);
    }

    public function test_doc24_ca06_una_fila_con_error_rechaza_todo_y_dice_la_fila_y_la_causa(): void
    {
        $this->cargar($this->excel([
            $this->fila(),
            $this->fila([0 => 'Marte', 5 => 'B']),
            $this->fila([8 => 'Nodo 9', 9 => 999, 5 => 'C']),
            $this->fila([4 => 17, 6 => '11/11/2026 10:00', 7 => '11/11/2026 09:00', 5 => 'D']),
            $this->fila([7 => '18/11/2026 09:00', 5 => 'E']),
            $this->fila([11 => 'No', 6 => '13/11/2026 08:00', 5 => 'F']),
            $this->fila([1 => null, 10 => 'corto', 2 => 'Vecinos', 6 => 'ayer', 5 => 'G']),
            $this->fila([5 => 'H']),
        ], [0 => [15, 16, 17], 1 => [15, 16, 17], 2 => [15, 16, 17], 3 => [15, 16, 17], 4 => [15, 16, 17], 5 => [15, 16, 17], 6 => [15, 16, 17], 7 => [15, 17]]))
            ->assertSessionHas('carga_errores', function (array $e) {
                $texto = implode("\n", $e);

                foreach ([
                    'Fila 3: el componente «Marte» no existe', 'Fila 4: el nodo «Nodo 9» no es de Calle', 'Fila 4: la cédula «999»',
                    'Fila 5: la comuna no está en la lista','Fila 5: el cierre debe ser posterior al inicio',
                    'Fila 6: la fecha y hora de cierre es futura', 'Fila 7: si no se cumplió, falta la observación', 'Fila 7: el caso tardó más de 3 horas: falta la justificación',
                    'Fila 8: falta el mensaje para la atención', 'Fila 8: la atención o gestión realizada debe tener al menos 10 caracteres',
                    'Fila 8: «Quién lo envía» debe ser', 'Fila 8: la fecha y hora de inicio no se entiende', 'Fila 9: falta la imagen de la evidencia 2',
                ] as $esperado) {
                    $this->assertStringContainsString($esperado, $texto);
                }

                return ! str_contains($texto, 'Fila 2:');
            });

        $this->assertSame(0, Derivacion::withoutGlobalScopes()->count());
        $this->assertSame(0, Documento::withoutGlobalScopes()->count());
    }

    public function test_doc24_ca07_las_filas_repetidas_entre_si_o_con_un_caso_que_ya_existe_rechazan_el_archivo(): void
    {
        $this->cargar($this->excel([$this->fila()]))->assertSessionHas('exito');

        $this->cargar($this->excel([
            $this->fila([5 => '  parque de  CASTILLA ']),
            $this->fila([5 => 'Otro sitio']),
            $this->fila([5 => 'Otro sitio']),
        ]))->assertSessionHas('carga_errores', fn (array $e) => count($e) === 2
            && str_contains($e[0], 'Fila 2') && str_contains($e[0], 'DC-001')
            && str_contains($e[1], 'Fila 4') && str_contains($e[1], 'la fila 3'));

        $this->assertSame(1, Derivacion::withoutGlobalScopes()->count());
    }

    public function test_doc25_ca06_la_huella_de_repetidos_no_cruza_componentes(): void
    {
        $this->cargar($this->excel([$this->fila()]))->assertSessionHas('exito');

        // Básica, sembrado fuera de una petición (el contexto quedó en Calle).
        app(\App\Services\ContextoDependencia::class)->establecer(null);
        $basica = Componente::factory()->create(['dependencia_id' => $this->dependencia->id, 'nombre' => 'Básica']);
        $nodo = Nodo::factory()->create(['componente_id' => $basica->id, 'nombre' => 'Nodo 2']);
        Colaborador::factory()->en($basica, $nodo)->create(['documento' => '1017000222', 'cargo' => 'Monitor de ruta']);
        $deBasica = $this->usuarioCon(RolDependencia::Coordinacion, $this->dependencia, $basica);

        // La misma entidad, ubicación y hora que DC-001 de Calle: en Básica no es repetida ni lo nombra.
        $this->cargar($this->excel([$this->fila([0 => 'Básica', 9 => 1017000222])]), $deBasica)
            ->assertSessionHas('exito')->assertSessionMissing('carga_errores');

        $this->assertSame(1, Derivacion::withoutGlobalScopes()->where('componente_id', $basica->id)->count());
    }
}
