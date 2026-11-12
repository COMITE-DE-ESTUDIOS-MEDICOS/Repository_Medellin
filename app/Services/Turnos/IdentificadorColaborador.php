<?php

namespace App\Services\Turnos;

use App\Enums\AccionAuditoria;
use App\Enums\OrigenColaborador;
use App\Models\Colaborador;
use App\Models\Componente;
use App\Services\Auditor;
use Illuminate\Validation\Rule;

/**
 * Quién es la persona que escribe su cédula en un enlace público. La
 * comparten el enlace de turno y el de evidencias: los datos se llenan una
 * sola vez, en cualquiera de los dos, y desde ahí basta la cédula.
 */
class IdentificadorColaborador
{
    public function __construct(protected Auditor $auditor)
    {
    }

    public function buscar(int $dependenciaId, string $cedula): ?Colaborador
    {
        return Colaborador::porDocumento($dependenciaId, $cedula);
    }

    /**
     * Las reglas del autorregistro, campo por campo bajo 'colaborador.*'.
     * Solo se aplican si la cédula es nueva: a quien ya existe no se le pide
     * ni se le cambia nada.
     */
    public function reglasDeRegistro(Componente $componente): array
    {
        $cargos = $componente->cargos();

        return [
            'colaborador' => ['required', 'array'],
            'colaborador.nombre' => ['required', 'string', 'max:255'],
            'colaborador.correo' => ['nullable', 'email', 'max:255'],
            'colaborador.telefono' => ['nullable', 'string', 'max:30'],
            'colaborador.entidad' => ['nullable', 'string', 'max:255'],
            'colaborador.cargo' => array_filter(['required', 'string', 'max:255', $cargos ? Rule::in($cargos) : null]),
            'colaborador.nodo_id' => [
                $componente->nodos()->activos()->exists() ? 'required' : 'nullable',
                Rule::exists('nodos', 'id')->where('componente_id', $componente->id)->where('activo', true),
            ],
        ];
    }

    /** Registra a quien llega por primera vez. Queda pendiente de que Coordinación lo verifique. */
    public function registrar(Componente $componente, string $cedula, array $datos): Colaborador
    {
        $colaborador = Colaborador::create([
            'dependencia_id' => $componente->dependencia_id,
            'documento' => $cedula,
            'nombre' => trim($datos['nombre']),
            'correo' => $datos['correo'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'entidad' => $datos['entidad'] ?? null,
            'cargo' => $datos['cargo'] ?? null,
            'componente_id' => $componente->id,
            'nodo_id' => $datos['nodo_id'] ?? null,
            'origen' => OrigenColaborador::Autoregistro,
            'activo' => true,
        ]);

        $this->auditor->registrar(
            AccionAuditoria::ColaboradorRegistrado,
            $colaborador,
            "{$colaborador->nombre} se registró por un enlace de {$componente->nombre}",
        );

        return $colaborador;
    }
}
