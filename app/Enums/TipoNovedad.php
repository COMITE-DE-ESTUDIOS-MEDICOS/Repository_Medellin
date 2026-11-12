<?php

namespace App\Enums;

enum TipoNovedad: string
{
    case TurnoCorto = 'turno_corto';
    case HorasExtra = 'horas_extra';
    case SinSalida = 'sin_salida';
    case SinUbicacion = 'sin_ubicacion';
    case FueraDeZona = 'fuera_de_zona';
    case Tardanza = 'tardanza';
    case SinEntrada = 'sin_entrada';
    case SinEpp = 'sin_epp';
    case UbicacionImprecisa = 'ubicacion_imprecisa';

    public function etiqueta(): string
    {
        return match ($this) {
            self::TurnoCorto => 'Turno corto',
            self::HorasExtra => 'Horas extra',
            self::SinSalida => 'Sin salida',
            self::SinUbicacion => 'Sin ubicación',
            self::FueraDeZona => 'Fuera de zona',
            self::Tardanza => 'Tardanza',
            self::SinEntrada => 'No registró entrada',
            self::SinEpp => 'Sin EPP',
            self::UbicacionImprecisa => 'Ubicación imprecisa',
        };
    }
}
