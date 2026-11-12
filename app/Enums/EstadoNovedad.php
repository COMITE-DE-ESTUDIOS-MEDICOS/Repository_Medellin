<?php

namespace App\Enums;

enum EstadoNovedad: string
{
    case Pendiente = 'pendiente';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
    // La marca que la originó se corrigió: el turno se volvió a juzgar.
    case Corregida = 'corregida';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
            self::Corregida => 'Corregida',
        };
    }
}
