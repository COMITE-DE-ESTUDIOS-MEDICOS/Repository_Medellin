<?php

namespace Tests\Feature\UsuariosHc;

use Tests\TestCase;

/**
 * DOC-26 (UHC-10, plan D10, PA-15): el campo de archivo con cámara puede ir sin
 * sello. Solo se revisa el HTML: sin data-sello, el script no estampa, no pide
 * la ubicación y vuelve a codificar la imagen. Eso se comprueba a mano (UHC-17).
 */
class UsuariosHcFotoCampoTest extends TestCase
{
    public function test_doc26_ca22_el_campo_de_foto_va_sin_sello(): void
    {
        // La foto de la persona: con cámara y :sello="null", sin data-sello.
        // Conserva el tope de resolución, que es lo que lleva a volver a codificar.
        $this->blade('<x-campo-archivo nombre="foto" camara formatos="imagenes" :sello="null" />')
            ->assertSee('data-campo-archivo', false)
            ->assertSee('data-camara', false)
            ->assertSee('data-lado-maximo="'.config('repositorio.imagen.lado_maximo').'"', false)
            ->assertDontSee('data-sello', false);
    }

    public function test_doc26_uhc10_los_demas_campos_con_camara_siguen_con_su_sello(): void
    {
        // Con un sello dado (turnos, derivaciones, supervisión).
        $this->blade('<x-campo-archivo nombre="foto" camara :galeria="false" formatos="publico" sello="Turno" />')
            ->assertSee('data-sello="Turno"', false);

        $this->blade('<x-campo-archivo nombre="evidencia1" camara formatos="imagenes" sello="Apoyo Territorial" />')
            ->assertSee('data-sello="Apoyo Territorial"', false);

        // Sin pasar el sello (el enlace de carga público): «Evidencia», como antes.
        $this->blade('<x-campo-archivo requerido varios con-nombres camara formatos="publico" />')
            ->assertSee('data-sello="Evidencia"', false);

        // Un sello vacío sigue sellando: el script le pone «Evidencia».
        $this->blade('<x-campo-archivo camara sello="" />')
            ->assertSee('data-sello=""', false);

        // Sin cámara no hay sello, como siempre.
        $this->blade('<x-campo-archivo requerido compacto />')
            ->assertSee('data-campo-archivo', false)
            ->assertDontSee('data-sello', false);
    }
}
