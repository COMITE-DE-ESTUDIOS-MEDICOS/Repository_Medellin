# Proyecto: Documenta

> **Estado:** borrador para aprobar por Edwar (Dueño de Producto).

## Qué es

Documenta es el sistema de información del **Comité de Estudios Médicos** para la
dependencia de **Inclusión Social** (Medellín). Empezó como repositorio documental y hoy
cubre también la operación de campo: turnos, evidencias y derivaciones. Está pensado para
que otras dependencias (Salud Mental ya está creada, inactiva) entren sin tocar código.
*(F-01, F-02)*

## El problema que resuelve

| Problema | Antes | Con Documenta | Fuente |
|---|---|---|---|
| Se pierden versiones | Carpetas compartidas, correos y USB; alguien guarda encima | Cada carga es una versión nueva, con autor y comentario | F-01 |
| No hay rastro | No se sabe quién descargó, retiró o subió qué | Auditoría de cada acción, con IP y navegador | F-01 |
| Recibir de fuera es un desorden | Contratistas y entidades mandan por correo, sin constancia | Enlaces de carga con vencimiento y cadena de custodia | F-01 |
| Turnos sin control | Formularios sueltos; no se sabe quién está en turno ni quién faltó | Marcación por QR y cédula, con foto y ubicación; aviso si alguien no llega | F-03, F-04 |
| Horas para nómina a mano | Se arman planillas a mano | Reporte de turnos en Excel con horas trabajadas, extra y resumen contra 42 h | F-04, F-05 |
| Derivaciones sin seguimiento | Hoja de cálculo; no se sabe qué está sin responder | Casos con estado, contador de tiempo, avisos y trazabilidad | D-16 a D-20 |

## Alcance

**Dentro** (construido):
1. Repositorio documental: carpetas, versiones, tipos, etiquetas, custodia, copiar y mover
   por lotes, comentarios, navegación entre archivos, inactivación con motivo.
2. Recepción externa: enlaces de carga (con o sin cédula), fotos con sello, cargas por tandas.
3. Usuarios por dependencia con cuatro roles, Authentik, auditoría, avisos por la campana.
4. Turnos de personas de campo: enlaces y QR, marcación, novedades, correcciones,
   turnos automáticos, ausencias, catálogo de la malla, reporte para Nómina.
5. Derivaciones y respuesta territorial: formulario por cédula, estado pendiente /
   solucionada, avisos, trazabilidad y reporte con fotos.
6. Manual de usuario en la aplicación, exportable a PDF.

**Fuera, a propósito** *(F-01, F-02)*: edición de documentos en línea, chat interno, firmas
digitales, flujos de aprobación de documentos, recuperación de contraseña por correo,
registro público de usuarios, cálculo de salarios y valores en pesos (D-11).

**Por especificar** (ver `../../README.md`): jornada esperada por tipo de turno, planilla
mensual con códigos para Nómina, recargos RN / RFD / RFN.

## Equipo

| Persona | Papel |
|---|---|
| Edwar (Jorge Eduardo Muñoz Quintero) | Desarrollador y **Dueño de Producto**: aprueba specs y tareas |
| José Amaya | Desarrollador |
| Claude | Asistente de desarrollo: redacta, programa y prueba; no aprueba ni publica |

Una misma persona puede cumplir varios papeles; eso no se reporta como problema.

## Ambientes

| Ambiente | Rama | Dónde |
|---|---|---|
| Local | `eduDev`, `josedev` | Laragon en Windows |
| Pruebas | `develop` | documenta.botenterprise.com.co |
| Producción | `main` | documenta.comitedeestudiosmedicos.com.co (VPS compartido con SISPAM) |

El despliegue lo hace GitHub Actions (`.github/workflows/deploy.yml` + `bin/deploy.sh`).

## Métricas de base

[completar: la constitución pide una línea base medible. Candidatos: horas al mes armando
la planilla de turnos a mano; derivaciones respondidas en más de 3 h; documentos perdidos o
duplicados al mes antes de Documenta.]
