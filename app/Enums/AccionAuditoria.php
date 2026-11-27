<?php

namespace App\Enums;

enum AccionAuditoria: string
{
    case Ingreso = 'ingreso';
    case Salida = 'salida';
    case DocumentoCreado = 'documento.creado';
    case DocumentoActualizado = 'documento.actualizado';
    case DocumentoVersionSubida = 'documento.version_subida';
    case DocumentoDescargado = 'documento.descargado';
    case DocumentoInactivado = 'documento.inactivado';
    case DocumentoReactivado = 'documento.reactivado';
    case DocumentoCopiado = 'documento.copiado';
    case CarpetaCreada = 'carpeta.creada';
    case CarpetaActualizada = 'carpeta.actualizada';
    case CarpetaInactivada = 'carpeta.inactivada';
    case CarpetaReactivada = 'carpeta.reactivada';
    case CarpetaDescargada = 'carpeta.descargada';
    case CarpetaCopiada = 'carpeta.copiada';
    case UsuarioCreado = 'usuario.creado';
    case UsuarioActualizado = 'usuario.actualizado';
    case UsuarioDesactivado = 'usuario.desactivado';
    case AuthentikAprovisionado = 'authentik.aprovisionado';
    case AuthentikFallo = 'authentik.fallo';
    case RecepcionRecibida = 'recepcion.recibida';
    case RecepcionArchivada = 'recepcion.archivada';
    case RecepcionDescartada = 'recepcion.descartada';
    case RecepcionReasignada = 'recepcion.reasignada';
    case EnlaceCreado = 'enlace.creado';
    case EnlaceRevocado = 'enlace.revocado';
    case EnlaceFechaEnvioCorregida = 'enlace.fecha_envio_corregida';

    // Turnos. Todas de una vez, para que nadie más tenga que tocar este archivo.
    case ColaboradorRegistrado = 'colaborador.registrado';
    case ColaboradorActualizado = 'colaborador.actualizado';
    case ColaboradorVerificado = 'colaborador.verificado';
    case TurnoEntrada = 'turno.entrada';
    case TurnoSalida = 'turno.salida';
    case TurnoCorreccion = 'turno.correccion';
    case TurnoCierreAutomatico = 'turno.cierre_automatico';
    case EnlaceTurnoCreado = 'enlace_turno.creado';
    case EnlaceTurnoRevocado = 'enlace_turno.revocado';
    case DerivacionRegistrada = 'derivacion.registrada';
    case DerivacionActualizada = 'derivacion.actualizada';
    case DerivacionRespondida = 'derivacion.respondida';
    case SupervisionRegistrada = 'supervision.registrada';
    case DerivacionEliminada = 'derivacion.eliminada';
    case CasoCreado = 'caso.creado';
    case CasoConsultado = 'caso.consultado';
    case CasoAtendido = 'caso.atendido';
    case CasoCorregidoContingencia = 'caso.contingencia';
    case ComponenteGuardado = 'componente.guardado';
    case NodoGuardado = 'nodo.guardado';
    case NovedadRevisada = 'turno.novedad_revisada';
    case DocumentoAbierto = 'documento.abierto';
    case DocumentoComentado = 'documento.comentado';
    case CasoComentado = 'caso.comentado';
    case CasoComentarioBorrado = 'caso.comentario_borrado';
    case CarpetaCustodia = 'carpeta.custodia';
    case GrupoCustodiaGuardado = 'grupo_custodia.guardado';
    case AvisoEnviado = 'aviso.enviado';
    case UsuarioHcCreado = 'usuario_hc.creado';
    case UsuarioHcActualizado = 'usuario_hc.actualizado';
    case UsuarioHcConsultado = 'usuario_hc.consultado';
    case UsuarioHcFotoVista = 'usuario_hc.foto_vista';
    case CasoCctvRegistrado = 'caso_cctv.registrado';
    case CasoCctvCorregido = 'caso_cctv.corregido';
    case CasoCctvConsultado = 'caso_cctv.consultado';
    case CasoCctvAtendido = 'caso_cctv.atendido';
    case CasoCctvCerrado = 'caso_cctv.cerrado';
    case CasoCctvAnulado = 'caso_cctv.anulado';
    case CasoCctvArchivoAbierto = 'caso_cctv.archivo_abierto';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Ingreso => 'Inició sesión',
            self::Salida => 'Cerró sesión',
            self::DocumentoCreado => 'Cargó un documento',
            self::DocumentoActualizado => 'Actualizó los datos de un documento',
            self::DocumentoVersionSubida => 'Subió una versión nueva',
            self::DocumentoDescargado => 'Descargó un documento',
            self::DocumentoInactivado => 'Inactivó un documento',
            self::DocumentoReactivado => 'Reactivó un documento',
            self::DocumentoCopiado => 'Copió un documento a otra carpeta',
            self::CarpetaCreada => 'Creó una carpeta',
            self::CarpetaActualizada => 'Actualizó una carpeta',
            self::CarpetaInactivada => 'Inactivó una carpeta',
            self::CarpetaReactivada => 'Reactivó una carpeta',
            self::CarpetaDescargada => 'Descargó una carpeta completa',
            self::CarpetaCopiada => 'Copió una carpeta',
            self::UsuarioCreado => 'Creó un usuario',
            self::UsuarioActualizado => 'Actualizó un usuario',
            self::UsuarioDesactivado => 'Desactivó un usuario',
            self::AuthentikAprovisionado => 'Cambió el acceso en Authentik',
            self::AuthentikFallo => 'Falló una operación con Authentik',
            self::RecepcionRecibida => 'Recibió un archivo por un enlace de carga',
            self::RecepcionArchivada => 'Archivó un recibido en el repositorio',
            self::RecepcionDescartada => 'Descartó un recibido',
            self::RecepcionReasignada => 'Reasignó un recibido a otra bandeja',
            self::EnlaceCreado => 'Creó un enlace de carga',
            self::EnlaceRevocado => 'Revocó un enlace de carga',
            self::EnlaceFechaEnvioCorregida => 'Corrigió la fecha de envío de un enlace',
            self::ColaboradorRegistrado => 'Registró una persona de campo',
            self::ColaboradorActualizado => 'Actualizó una persona de campo',
            self::ColaboradorVerificado => 'Verificó una persona de campo',
            self::TurnoEntrada => 'Marcó entrada de turno',
            self::TurnoSalida => 'Marcó salida de turno',
            self::TurnoCorreccion => 'Corrigió una marcación de turno',
            self::TurnoCierreAutomatico => 'Cerró un turno sin salida',
            self::EnlaceTurnoCreado => 'Creó un enlace de turno',
            self::EnlaceTurnoRevocado => 'Revocó un enlace de turno',
            self::DerivacionRegistrada => 'Registró una derivación',
            self::DerivacionActualizada => 'Corrigió una derivación',
            self::DerivacionRespondida => 'Respondió una derivación',
            self::SupervisionRegistrada => 'Registró una supervisión territorial',
            self::DerivacionEliminada => 'Eliminó una derivación',
            self::CasoCreado => 'Creó un caso de derivación',
            self::CasoConsultado => 'Consultó un caso desde el enlace',
            self::CasoAtendido => 'Atendió un caso de derivación',
            self::CasoCorregidoContingencia => 'Corrigió horas o cierre de un caso (contingencia)',
            self::ComponenteGuardado => 'Creó o cambió un componente',
            self::NodoGuardado => 'Creó o cambió un nodo',
            self::NovedadRevisada => 'Aprobó o rechazó una novedad de turno',
            self::DocumentoAbierto => 'Abrió un documento custodiado',
            self::DocumentoComentado => 'Comentó un documento',
            self::CasoComentado => 'Comentó un caso de derivación',
            self::CasoComentarioBorrado => 'Borró un comentario de un caso',
            self::CarpetaCustodia => 'Cambió la custodia de una carpeta',
            self::GrupoCustodiaGuardado => 'Creó o cambió un grupo de custodia',
            self::AvisoEnviado => 'Envió un aviso por la campana',
            self::UsuarioHcCreado => 'Creó un Usuario HC',
            self::UsuarioHcActualizado => 'Corrigió un Usuario HC',
            self::UsuarioHcConsultado => 'Abrió la caracterización de un Usuario HC',
            self::UsuarioHcFotoVista => 'Vio la foto de un Usuario HC',
            self::CasoCctvRegistrado => 'Registró un caso de Seguridad CCTV',
            self::CasoCctvCorregido => 'Corrigió un caso de Seguridad CCTV',
            self::CasoCctvConsultado => 'Consultó un caso de Seguridad CCTV desde el enlace',
            self::CasoCctvAtendido => 'Atendió un caso de Seguridad CCTV',
            self::CasoCctvCerrado => 'Cerró un caso de Seguridad CCTV',
            self::CasoCctvAnulado => 'Anuló un caso de Seguridad CCTV',
            self::CasoCctvArchivoAbierto => 'Abrió una imagen de un caso de Seguridad CCTV',
        };
    }
}
