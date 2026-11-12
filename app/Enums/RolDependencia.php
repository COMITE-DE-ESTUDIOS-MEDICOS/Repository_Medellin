<?php

namespace App\Enums;

enum RolDependencia: string
{
    case Lectura = 'lectura';
    case Edicion = 'edicion';
    case Coordinacion = 'coordinacion';
    case Administracion = 'administracion';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Lectura => 'Solo lectura',
            self::Edicion => 'Edición',
            self::Coordinacion => 'Coordinación',
            self::Administracion => 'Administración',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Lectura => 'Consulta y descarga los documentos autorizados. No modifica nada.',
            self::Edicion => 'Sube archivos, crea, copia y mueve carpetas y documentos, y genera sus propios enlaces de carga. '
                .'Puede inactivar las carpetas que creó, si no contienen nada de otras personas. '
                .'Gestiona los turnos: enlaces, personas de campo, novedades y reporte.',
            self::Coordinacion => 'Lo anterior, más gestionar usuarios, tipos, componentes de turnos, todos los enlaces de carga y auditoría. '
                .'Inactiva y reactiva cualquier carpeta; los documentos los retira solo Administración.',
            self::Administracion => 'Control total, incluido retirar carpetas y documentos de la vista.',
        };
    }

    public function nivel(): int
    {
        return match ($this) {
            self::Lectura => 1,
            self::Edicion => 2,
            self::Coordinacion => 3,
            self::Administracion => 4,
        };
    }

    public function puedeEditar(): bool
    {
        return $this->nivel() >= self::Edicion->nivel();
    }

    /**
     * Administrar personas y estructura: usuarios, tipos de documento,
     * enlaces de carga y auditoría.
     *
     * Es la frontera que separa a Coordinación de Edición.
     */
    public function puedeGestionar(): bool
    {
        return $this->nivel() >= self::Coordinacion->nivel();
    }

    /**
     * Retirar contenido de la vista —inactivar y reactivar carpetas y
     * documentos— y ver lo ya retirado.
     *
     * Es la frontera que separa a Administración de Coordinación: esconder
     * información de toda la dependencia es la única acción que no se
     * delega.
     */
    public function puedeAdministrar(): bool
    {
        return $this === self::Administracion;
    }

    /**
     * Roles que quien tiene este puede otorgar a otra persona.
     *
     * Nadie reparte un rol por encima del suyo. Sin esta regla, Coordinación
     * podría crear un usuario con rol Administración —o ascenderse a sí
     * misma— y saltarse en dos clics justo lo que no debe poder hacer.
     *
     * @return list<self>
     */
    public function asignables(): array
    {
        if (! $this->puedeGestionar()) {
            return [];
        }

        return array_values(array_filter(
            self::cases(),
            fn (self $rol) => $rol->nivel() <= $this->nivel(),
        ));
    }

    /** @return array<string, string> */
    public static function opciones(): array
    {
        $opciones = [];

        foreach (self::cases() as $rol) {
            $opciones[$rol->value] = $rol->etiqueta();
        }

        return $opciones;
    }
}
