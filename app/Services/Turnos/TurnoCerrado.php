<?php

namespace App\Services\Turnos;

use RuntimeException;

/**
 * Lo que no se puede hacer con un turno, con un mensaje para mostrarle tal
 * cual a quien lo intentó: marcar con el turno del día ya cerrado, o
 * corregir una hora de forma que el turno quede al revés.
 */
class TurnoCerrado extends RuntimeException
{
}
