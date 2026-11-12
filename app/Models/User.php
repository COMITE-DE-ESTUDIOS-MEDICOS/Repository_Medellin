<?php

namespace App\Models;

use App\Enums\RolDependencia;
use App\Enums\TemaInterfaz;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'documento',
        'usuario',
        'email',
        'password',
        'cargo',
        'activo',
        'es_superadmin',
        'ultimo_acceso_at',
        'tema',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Mismo valor por defecto que la columna, para que un usuario recién
     * creado ya traiga tema en memoria y no solo después de releerlo.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tema' => 'sistema',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'es_superadmin' => 'boolean',
            'tema' => TemaInterfaz::class,
        ];
    }

    /**
     * Forma canónica del documento: sin puntos, espacios ni guiones, y en
     * mayúsculas.
     *
     * Es lo que evita que «1.234.567» y «1234567» acaben siendo dos cuentas
     * de la misma persona, y lo que permite escribirlo como uno quiera al
     * iniciar sesión. Se usa igual al guardar y al buscar, para que las dos
     * puntas hablen el mismo idioma.
     */
    public static function normalizarDocumento(?string $documento): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $documento));
    }

    /** El alias de acceso se guarda y se compara siempre en minúsculas. */
    public static function normalizarUsuario(?string $usuario): string
    {
        return mb_strtolower(trim((string) $usuario));
    }

    /**
     * Encuentra a alguien por lo que haya tecleado para entrar: su documento,
     * su nombre de usuario o su correo.
     *
     * Se resuelve aquí, y no con tres Auth::attempt seguidos, para que la
     * comprobación de la contraseña ocurra una sola vez pase lo que pase: si
     * unas formas de identificarse tardaran más que otras, el tiempo de
     * respuesta diría cuáles existen.
     */
    public static function porIdentificador(?string $valor): ?self
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return null;
        }

        return static::query()
            ->where('documento', static::normalizarDocumento($valor))
            ->orWhere('usuario', static::normalizarUsuario($valor))
            ->orWhere('email', $valor)
            ->first();
    }

    public function dependencias(): BelongsToMany
    {
        return $this->belongsToMany(Dependencia::class, 'dependencia_usuario')
            ->withPivot('rol', 'aprueba_novedades')
            ->withTimestamps();
    }

    public function carpetasLideradas(): BelongsToMany
    {
        return $this->belongsToMany(Carpeta::class, 'carpeta_lider')->withTimestamps();
    }

    /**
     * Ids de las carpetas que lidera, resueltos una sola vez por petición.
     *
     * @var list<int>|null
     */
    protected ?array $idsDeCarpetasLideradas = null;

    /**
     * Se responde con la lista ya en memoria y no con un exists() por llamada:
     * el listado de enlaces pregunta esto una vez por fila, y con veinticinco
     * enlaces eran veinticinco consultas para lo que cabe en una.
     */
    public function lideraCarpeta(Carpeta $carpeta): bool
    {
        $this->idsDeCarpetasLideradas ??= $this->carpetasLideradas()
            ->pluck('carpetas.id')
            ->map(intval(...))
            ->all();

        return in_array((int) $carpeta->id, $this->idsDeCarpetasLideradas, true);
    }

    /** Rol del usuario dentro de una dependencia, o null si no pertenece a ella. */
    public function rolEn(Dependencia|int|null $dependencia): ?RolDependencia
    {
        if ($dependencia === null) {
            return null;
        }

        $id = $dependencia instanceof Dependencia ? $dependencia->id : $dependencia;

        if ($this->es_superadmin) {
            return RolDependencia::Administracion;
        }

        $relacion = $this->membresia($id);

        if ($relacion === null) {
            return null;
        }

        return RolDependencia::tryFrom($relacion->pivot->rol);
    }

    /** La dependencia con su pivote (rol, aprueba_novedades), o null si no pertenece. */
    protected function membresia(int $id): ?Dependencia
    {
        return $this->dependencias->firstWhere('id', $id)
            ?? $this->dependencias()->whereKey($id)->first();
    }

    /**
     * Aprobar o rechazar novedades de turno: Coordinación y Administración
     * siempre; de Edición, solo quien tenga marcado «Aprueba novedades».
     */
    public function puedeAprobarNovedadesEn(Dependencia|int|null $dependencia): bool
    {
        if ($this->puedeGestionarEn($dependencia)) {
            return true;
        }

        $id = $dependencia instanceof Dependencia ? $dependencia->id : $dependencia;

        return $id !== null && $this->puedeEditarEn($id) && (bool) $this->membresia($id)?->pivot->aprueba_novedades;
    }

    public function perteneceA(Dependencia|int|null $dependencia): bool
    {
        return $this->rolEn($dependencia) !== null;
    }

    public function puedeEditarEn(Dependencia|int|null $dependencia): bool
    {
        return $this->rolEn($dependencia)?->puedeEditar() ?? false;
    }

    /** Gestiona personas y estructura: Coordinación y Administración. */
    public function puedeGestionarEn(Dependencia|int|null $dependencia): bool
    {
        return $this->rolEn($dependencia)?->puedeGestionar() ?? false;
    }

    /** Retira contenido de la vista y ve lo retirado: solo Administración. */
    public function puedeAdministrarEn(Dependencia|int|null $dependencia): bool
    {
        return $this->rolEn($dependencia)?->puedeAdministrar() ?? false;
    }

    public function iniciales(): string
    {
        $partes = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $iniciales = array_map(fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($partes, 0, 2));

        return implode('', $iniciales) ?: '?';
    }
}
