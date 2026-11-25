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
