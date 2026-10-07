<?php

namespace App\Activity;

/**
 * Tipos de evento del registro de actividad, con su categoría para filtrar.
 */
enum ActivityType: string
{
    // Cuenta
    case Registered = 'cuenta.registro';
    case LoggedIn = 'cuenta.inicio_sesion';
    case LoggedOut = 'cuenta.cierre_sesion';
    case LoginFailed = 'cuenta.intento_fallido';
    case DemoStarted = 'cuenta.prueba_iniciada';
    case DemoDeleted = 'cuenta.prueba_borrada';
    case DemoExpired = 'cuenta.prueba_caducada';
    case AppearanceChanged = 'cuenta.apariencia';

    // Espacios de trabajo
    case WorkspaceCreated = 'espacio.creado';
    case WorkspaceSwitched = 'espacio.cambiado';
    case WorkspaceRenamed = 'espacio.renombrado';
    case MemberAdded = 'espacio.miembro_agregado';
    case MemberRoleChanged = 'espacio.miembro_rol';
    case MemberRemoved = 'espacio.miembro_quitado';

    // Libros
    case BookUploaded = 'libro.subido';
    case BookUploadFailed = 'libro.subida_fallida';
    case BookOpened = 'libro.abierto';
    case BookSaved = 'libro.guardado';
    case BookDeleted = 'libro.borrado';

    // Edición
    case TextMadeEditable = 'edicion.texto_editable';
    case ExactPreview = 'edicion.vista_exacta';

    // Impresión
    case BookPrinted = 'impresion.libro';
    case QuickImposition = 'impresion.rapida';
    case ImpositionDownloaded = 'impresion.descarga';
    case CalibrationDownloaded = 'impresion.calibracion';
    case TestDocumentDownloaded = 'impresion.documento_prueba';

    // Fuentes
    case FontUploaded = 'fuente.subida';
    case FontDeleted = 'fuente.borrada';

    // Otros
    case Error = 'error';
    case PageVisited = 'navegacion.visita';

    public function category(): string
    {
        return strstr($this->value, '.', true) ?: $this->value;
    }

    /**
     * @return array<string, string> categoría => nombre visible
     */
    public static function categories(): array
    {
        return [
            'cuenta' => 'Cuenta',
            'espacio' => 'Espacios',
            'libro' => 'Libros',
            'edicion' => 'Edición',
            'impresion' => 'Impresión',
            'fuente' => 'Fuentes',
            'error' => 'Errores',
            'navegacion' => 'Navegación',
        ];
    }
}
