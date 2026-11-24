<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * DOC-25: preguntas para Edwar que salieron al construir ACC-11 y ACC-13.
 * No fallan: salen como «incompletas» cada vez que se corre la suite, para
 * que no se pierdan. Cuando Edwar responda, se aplica la respuesta (spec,
 * plan o código) y se borra la prueba de esa pregunta; con las tres
 * respondidas, se borra el archivo.
 */
class PreguntasDoc25Test extends TestCase
{
    public function test_p04_administracion_recibe_los_avisos_manuales(): void
    {
        $this->markTestIncomplete(
            'Pregunta para Edwar (DOC-25, plan P-04): «Enviar aviso» llega hoy solo a los usuarios de los '
            .'componentes marcados; Administración, que no tiene componente, NO lo recibe (respuesta por '
            .'defecto del plan). ¿Debe recibirlo siempre? Si sí: App\Services\Avisos::destinatarios(), el '
            .'texto de admin/avisos/index.blade.php, el manual y las pruebas de AuditoriaYAvisosPorComponenteTest.'
        );
    }

    public function test_pa06_auditoria_de_coordinacion_sin_los_registros_del_sistema(): void
    {
        $this->markTestIncomplete(
            'Pregunta para Edwar (DOC-25, PA-06 frente a CA-03): Coordinación ve en la Auditoría solo las '
            .'acciones de los usuarios de su componente. Los registros sin usuario («Sistema»: marcaciones por '
            .'QR, cargas por enlace, cierres automáticos) quedan fuera, y son casi todo (en una base local: 412 '
            .'del Sistema, 35 de Administración, 0 de usuarios de Calle). Para Coordinación de Calle esto SÍ '
            .'cambia, y CA-03 dice que para Calle nada cambia. Mostrarlos le dejaría ver los de Básica, porque '
            .'auditorias no guarda el componente. ¿Se acepta así, o se agrega el componente a la auditoría (va contra D10)?'
        );
    }

    public function test_simulador_de_turnos_choca_con_el_cierre_por_horario(): void
    {
        $this->markTestIncomplete(
            'Para Edwar (turnos): «php artisan turnos:simular-dia 2026-10-20 --personas=60» se detiene en '
            .'MariaDB con TurnoCerrado («Ya registraste tu turno de hoy», Marcador.php:216, llamado desde '
            .'SimularDiaTurnos.php:114). Falla igual al repetirlo; con 5 personas en SQLite pasa. El comando no '
            .'cambia desde el 26-10 y las reglas de turno sí (DOC-21, cierre sin salida por horario). No es de '
            .'ACC-13 (solo le agregó los módulos al componente demo).'
        );
    }
}
