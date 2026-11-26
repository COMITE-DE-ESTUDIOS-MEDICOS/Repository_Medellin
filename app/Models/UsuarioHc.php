<?php

namespace App\Models;

use App\Models\Scopes\ComponenteScope;
use App\Models\Scopes\DependenciaScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * DOC-26: la ficha de una persona atendida (Usuario HC). Lleva el filtro de
 * dependencia y NO el de componente: la identificación se busca en toda la
 * dependencia (spec §6.1). Lo sensible (caracterización, respuestas,
 * historial) cuelga de ella en otras tablas y se abre después de la Policy.
 */
#[ScopedBy([DependenciaScope::class])]
class UsuarioHc extends Model
{
    use HasFactory;

    public const VIVA = 'viva';

    public const FALLECIDA = 'fallecida';

    /** Marca de una opción que el registro tiene y ya no se ofrece (CA-18). Solo para mostrar. */
    public const RETIRADA = ' (retirada)';

    /**
     * «Sin documento» en tipos_documento: con él el número no se pide (UHC-07).
     * Cambia el comportamiento, así que no se retira ni se le cambia el código.
     */
    public const SIN_DOCUMENTO = 'SD';

    /**
     * Los campos de la caracterización que son listas (spec §3, plan D8). Un
     * campo nuevo es un cambio de la spec. Las fechas y el tiempo en calle son
     * columnas de usuario_hc_caracterizaciones y no van aquí.
     *
     * 'tipo': una, varias o varias_con_principal (solo medicina general; en
     * «otros de psiquiatría» no se marca principal, PA-08).
     * 'fuente': config:<lista> (repositorio.usuarios_hc.<lista>), comunas
     * (repositorio.comunas) o catalogo:paises / catalogo:cie10 (tablas).
     * 'busqueda': la spec dice «con búsqueda».
     */
    public const CAMPOS = [
        'escolaridad' => ['etiqueta' => 'Escolaridad', 'tipo' => 'una', 'fuente' => 'config:escolaridad'],
        'sustancias' => ['etiqueta' => 'Sustancias de impacto', 'tipo' => 'varias', 'fuente' => 'config:sustancias'],
        'red_apoyo' => ['etiqueta' => 'Red de apoyo', 'tipo' => 'una', 'fuente' => 'config:si_no'],
        'nivel_apoyo' => ['etiqueta' => 'Nivel de apoyo de la red', 'tipo' => 'una', 'fuente' => 'config:nivel_apoyo'],
        'clasificacion' => ['etiqueta' => 'Clasificación', 'tipo' => 'una', 'fuente' => 'config:clasificacion'],
        'sexo' => ['etiqueta' => 'Sexo según documento de identidad', 'tipo' => 'una', 'fuente' => 'config:sexo'],
        'orientacion_sexual' => ['etiqueta' => 'Orientación sexual', 'tipo' => 'una', 'fuente' => 'config:orientacion'],
        'nacionalidad' => ['etiqueta' => 'Nacionalidad', 'tipo' => 'una', 'fuente' => 'catalogo:paises', 'busqueda' => true],
        'comuna' => ['etiqueta' => 'Comuna o corregimiento donde permanece o vive', 'tipo' => 'una', 'fuente' => 'comunas'],
        // Autorizada al aprobar la spec, solo para el Usuario HC (PA-07).
        'eps' => ['etiqueta' => 'EPS', 'tipo' => 'una', 'fuente' => 'config:eps', 'busqueda' => true],
        'listado_censal' => ['etiqueta' => 'Listado censal', 'tipo' => 'una', 'fuente' => 'config:si_no'],
        'diagnostico_psiquiatria' => ['etiqueta' => 'Diagnóstico principal de psiquiatría', 'tipo' => 'una', 'fuente' => 'catalogo:cie10', 'busqueda' => true],
        'otros_diagnosticos_psiquiatria' => ['etiqueta' => 'Otros diagnósticos de psiquiatría', 'tipo' => 'varias', 'fuente' => 'catalogo:cie10', 'busqueda' => true],
        'diagnosticos_medicina_general' => ['etiqueta' => 'Diagnósticos de medicina general', 'tipo' => 'varias_con_principal', 'fuente' => 'catalogo:cie10', 'busqueda' => true],
        'proteccion_infancia' => ['etiqueta' => 'Antecedentes de protección institucional en la infancia', 'tipo' => 'una', 'fuente' => 'config:si_no'],
        'discapacidad' => ['etiqueta' => 'Tipo de discapacidad', 'tipo' => 'varias', 'fuente' => 'config:discapacidad'],
        'cedula_fisica' => ['etiqueta' => 'Cédula física', 'tipo' => 'una', 'fuente' => 'config:si_no'],
        'portabilidad' => ['etiqueta' => 'Portabilidad', 'tipo' => 'una', 'fuente' => 'config:si_no'],
        'codigo_900' => ['etiqueta' => 'Código 900', 'tipo' => 'una', 'fuente' => 'config:si_no'],
        'oxigeno_dependiente' => ['etiqueta' => 'Oxígeno dependiente', 'tipo' => 'una', 'fuente' => 'config:si_no'],
        'autonomia' => ['etiqueta' => 'Clasificación de autonomía', 'tipo' => 'una', 'fuente' => 'config:autonomia'],
        'independencia' => ['etiqueta' => 'Nivel de independencia funcional', 'tipo' => 'una', 'fuente' => 'config:independencia'],
    ];

    protected $table = 'usuarios_hc';

    // componente_registro_id no va aquí a propósito: lo pone el servidor al crear y no cambia (CA-20).
    // Las columnas normalizadas y nombre_completo tampoco: las arma saving.
    protected $fillable = [
        'dependencia_id',
        'tipo_documento',
        'tipo_documento_texto',
        'numero_documento',
        'numero_ficha',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'alias',
        'estado_vital',
        'fecha_fallecimiento',
        'creado_por',
        'actualizado_por',
    ];

    protected $attributes = [
        'estado_vital' => self::VIVA,
        'revision' => 0,
    ];

    protected function casts(): array
    {
        return [
            'fecha_fallecimiento' => 'date',
            'revision' => 'integer',
        ];
    }

    /**
     * Arma lo derivado, la escriba quien la escriba (plan D5). Una columna
     * normalizada vacía queda NULL, no '': así los índices únicos admiten
     * varias fichas «Sin documento» o sin número de ficha.
     */
    protected static function booted(): void
    {
        static::saving(function (self $ficha) {
            // También el espacio duro (U+00A0) de un dato pegado desde Excel.
            $limpio = fn (?string $v) => ($v = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', (string) $v)) === '' ? null : $v;

            foreach (['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'alias', 'numero_documento'] as $campo) {
                $ficha->{$campo} = $limpio($ficha->{$campo});
            }
            // Se muestra como se escribió, en mayúsculas y con sus guiones (PA-20).
            $ficha->numero_ficha = $limpio(Str::upper((string) $ficha->numero_ficha));

            $ficha->nombre_completo = preg_replace('/\s+/u', ' ', collect([$ficha->primer_nombre, $ficha->segundo_nombre, $ficha->primer_apellido, $ficha->segundo_apellido])
                ->whereNotNull()->implode(' '));

            $ficha->nombre_busqueda = $limpio(self::normalizarTexto($ficha->nombre_completo));
            $ficha->alias_busqueda = $limpio(self::normalizarTexto($ficha->alias));
            $ficha->documento_normalizado = $limpio(self::normalizarCodigo($ficha->numero_documento));
            $ficha->ficha_normalizada = $limpio(self::normalizarCodigo($ficha->numero_ficha));
        });
    }

    /** Nombres, alias y nombres de catálogo: «Peña Álvarez, J.» → «pena alvarez j» (D5). */
    public static function normalizarTexto(?string $t): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(Str::ascii((string) $t))));
    }

    /** Documento, ficha y CIE-10, uno solo: «12.345 67» → «1234567»; «f-120 » y «F120» → «F120» (D5). */
    public static function normalizarCodigo(?string $t): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(Str::ascii((string) $t)));
    }

    /**
     * Lo que se ofrece en una lista, para cualquier registro (plan D8; DOC-27
     * la llama con su bloque 'ingresos_egresos'). No conoce CAMPOS ni la ficha.
     *
     * $lista: un bloque de la configuración (opciones, retiradas, excluyentes).
     * $yaTiene: lo que el registro ya tiene, [código => texto guardado].
     * Devuelve [código => texto], en el orden de 'opciones':
     * - las vigentes (opciones − retiradas) con el texto de la configuración;
     * - las que el registro ya tiene, con SU texto guardado aunque sigan
     *   vigentes y se hayan renombrado (CA-18: conserva el texto original);
     * - si además ya no están vigentes, con « (retirada)»;
     * - al final, las del registro que ya ni están en 'opciones', igual.
     *
     * El texto devuelto es para MOSTRAR. Para guardar se usa el texto guardado
     * ($yaTiene) o el de la configuración, nunca este: llevaría « (retirada)».
     * Si de todos modos llega con la marca, no se repite.
     *
     * La comuna es una lista plana: se pasa como ['opciones' => config('repositorio.comunas')].
     */
    public static function opciones(array $lista, array $yaTiene = []): array
    {
        $retiradas = array_map('strval', $lista['retiradas'] ?? []);
        $marcar = fn (string $texto) => str_ends_with($texto, self::RETIRADA) ? $texto : $texto.self::RETIRADA;
        $resultado = [];

        foreach ($lista['opciones'] ?? [] as $codigo => $texto) {
            $vigente = ! in_array((string) $codigo, $retiradas, true);
            if (array_key_exists($codigo, $yaTiene)) {
                $guardado = (string) $yaTiene[$codigo] !== '' ? (string) $yaTiene[$codigo] : (string) $texto;
                $resultado[$codigo] = $vigente ? $guardado : $marcar($guardado);
            } elseif ($vigente) {
                $resultado[$codigo] = $texto;
            }
        }

        foreach ($yaTiene as $codigo => $texto) {
            $resultado[$codigo] ??= $marcar((string) $texto !== '' ? (string) $texto : (string) $codigo);
        }

        return $resultado;
    }

    /**
     * true si entre $codigos hay una opción excluyente junto con cualquier otra
     * (CA-10). Los vacíos no cuentan: un formulario manda '' o null cuando la
     * lista se deja sin marcar.
     */
    public static function chocanExcluyentes(array $lista, array $codigos): bool
    {
        $codigos = array_unique(array_filter(array_map('strval', $codigos), fn (string $c) => $c !== ''));

        return count($codigos) > 1
            && array_intersect($codigos, array_map('strval', $lista['excluyentes'] ?? [])) !== [];
    }

    /**
     * CA-12, por comodidad desde la ficha: la edad sale de la caracterización,
     * que es sensible. Mostrarla solo donde se puede ver la caracterización.
     */
    protected function edad(): Attribute
    {
        return Attribute::get(fn () => $this->caracterizacion?->edad ?? CaracterizacionUsuarioHc::SIN_INFORMACION);
    }

    public function dependencia(): BelongsTo
    {
        return $this->belongsTo(Dependencia::class);
    }

    /**
     * Salto de ComponenteScope a propósito (plan D1, DOC-25 D4): la ficha se ve
     * desde cualquier componente con el módulo, y sin el salto el componente de
     * registro de una ficha de otro componente saldría vacío (CA-02). El de
     * dependencia sigue puesto.
     */
    public function componenteRegistro(): BelongsTo
    {
        return $this->belongsTo(Componente::class, 'componente_registro_id')->withoutGlobalScope(ComponenteScope::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function identidades(): HasMany
    {
        return $this->hasMany(IdentidadUsuarioHc::class);
    }

    public function caracterizacion(): HasOne
    {
        return $this->hasOne(CaracterizacionUsuarioHc::class);
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaUsuarioHc::class);
    }

    public function cambios(): HasMany
    {
        return $this->hasMany(CambioUsuarioHc::class);
    }

    /** Todas las fotos que tuvo; ninguna se borra (CA-22). */
    public function fotos(): HasMany
    {
        return $this->hasMany(FotoUsuarioHc::class);
    }

    /**
     * La foto vigente: la de foto_id, y solo si es de esta ficha. foto_id no
     * tiene FK (sería circular), así que se pide por los dos lados: la foto
     * cuelga de la ficha (hasOne) y la ficha apunta a ella. Sirve igual al
     * cargarla sola o con with().
     */
    public function foto(): HasOne
    {
        return $this->hasOne(FotoUsuarioHc::class)->whereExists(fn ($q) => $q->selectRaw('1')->from('usuarios_hc')
            ->whereColumn('usuarios_hc.id', 'usuario_hc_fotos.usuario_hc_id')
            ->whereColumn('usuarios_hc.foto_id', 'usuario_hc_fotos.id'));
    }

    public function estaFallecida(): bool
    {
        return $this->estado_vital === self::FALLECIDA;
    }
}
