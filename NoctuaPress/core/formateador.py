"""
MÓDULO 2: MOTOR DE FORMATEO
============================

Genera PDF formateado a partir de la lista de Bloque (Módulo 1).
Importa ConfiguracionFormateo desde core.models (fuente única).

Responsabilidad: List[Bloque] + ConfiguracionFormateo → PDF en bytes
"""

import io
import requests
from pathlib import Path
from typing import Dict, List, Optional

from reportlab.lib.pagesizes import letter, A4, legal
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import cm
from reportlab.lib.colors import black
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, PageBreak, KeepTogether
)
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

# Módulo 1
from .extractor import Bloque

# Modelos compartidos
from .models import ConfiguracionFormateo, ResultadoFormateo, TamañoPagina, PosicionNumeracion


# ---------------------------------------------------------------------------
# Catálogo de Google Fonts (TTF directo, sin woff2)
# ---------------------------------------------------------------------------

GOOGLE_FONTS_DISPONIBLES: Dict[str, str] = {
    "EB Garamond":       "https://github.com/google/fonts/raw/main/ofl/ebgaramond/EBGaramond-Regular.ttf",
    "Lora":              "https://github.com/google/fonts/raw/main/ofl/lora/Lora-Regular.ttf",
    "Merriweather":      "https://github.com/google/fonts/raw/main/ofl/merriweather/Merriweather-Regular.ttf",
    "Libre Baskerville": "https://github.com/google/fonts/raw/main/ofl/librebaskerville/LibreBaskerville-Regular.ttf",
    "Playfair Display":  "https://github.com/google/fonts/raw/main/ofl/playfairdisplay/PlayfairDisplay-Regular.ttf",
    "Crimson Text":      "https://github.com/google/fonts/raw/main/ofl/crimsontext/CrimsonText-Regular.ttf",
    "Cormorant Garamond":"https://github.com/google/fonts/raw/main/ofl/cormorantgaramond/CormorantGaramond-Regular.ttf",
    "Source Serif 4":    "https://github.com/google/fonts/raw/main/ofl/sourceserif4/SourceSerif4-Regular.ttf",
}

# Fuentes del sistema disponibles en ReportLab sin registro extra
FUENTES_SISTEMA: Dict[str, str] = {
    "Times New Roman": "Times-Roman",
    "Times Roman":     "Times-Roman",
    "Times-Roman":     "Times-Roman",
    "Helvetica":       "Helvetica",
    "Arial":           "Helvetica",
    "Courier":         "Courier",
    "Courier New":     "Courier",
}


# ---------------------------------------------------------------------------
# Gestor de fuentes
# ---------------------------------------------------------------------------

class GestorFuentes:
    """Registra fuentes del sistema o descarga/cachea Google Fonts."""

    def __init__(self, carpeta_cache: str = "assets/fonts"):
        self.carpeta_cache = Path(carpeta_cache)
        self.carpeta_cache.mkdir(parents=True, exist_ok=True)
        self._resueltas: Dict[str, str] = {}

    def resolver(self, nombre: str, es_google: bool = False) -> str:
        """Devuelve el nombre de fuente listo para ReportLab."""
        if nombre in self._resueltas:
            return self._resueltas[nombre]

        if es_google:
            nombre_rl = self._resolver_google(nombre)
        else:
            nombre_rl = FUENTES_SISTEMA.get(nombre, nombre)

        self._resueltas[nombre] = nombre_rl
        return nombre_rl

    def _resolver_google(self, nombre: str) -> str:
        if nombre not in GOOGLE_FONTS_DISPONIBLES:
            print(f"[Fuentes] '{nombre}' no en catálogo → Times-Roman")
            return "Times-Roman"

        archivo = nombre.replace(" ", "_") + ".ttf"
        ruta = self.carpeta_cache / archivo

        if not ruta.exists():
            url = GOOGLE_FONTS_DISPONIBLES[nombre]
            print(f"[Fuentes] Descargando {nombre}...")
            try:
                resp = requests.get(url, timeout=15)
                resp.raise_for_status()
                ruta.write_bytes(resp.content)
            except Exception as e:
                print(f"[Fuentes] Error: {e} → Times-Roman")
                return "Times-Roman"

        try:
            pdfmetrics.registerFont(TTFont(nombre, str(ruta)))
            return nombre
        except Exception as e:
            print(f"[Fuentes] No se pudo registrar {nombre}: {e} → Times-Roman")
            return "Times-Roman"


# ---------------------------------------------------------------------------
# Formateador principal
# ---------------------------------------------------------------------------

class PDFFormatter:
    """
    Convierte List[Bloque] + ConfiguracionFormateo en un PDF formateado.

    Lógica de construcción:
    - 'capitulo' + 'titulo_principal' siguiente → agrupados con KeepTogether
      (número de capítulo encima, título grande debajo, nunca separados)
    - 'titulo_principal' sin 'capitulo' previo → título de sección (Introducción, Índice…)
    - 'parrafo' → texto justificado con sangría
    - 'pagina_nueva' → ignorado (los saltos los controlan los capítulos)
    """

    TAMAÑOS_PAGINA = {
        TamañoPagina.LETTER: letter,
        TamañoPagina.A4:     A4,
        TamañoPagina.LEGAL:  legal,
    }

    def __init__(self, config: ConfiguracionFormateo):
        self.config  = config
        self.gestor  = GestorFuentes()

    def formatear(self, bloques: List[Bloque]) -> ResultadoFormateo:
        """
        Genera el PDF en memoria.

        Args:
            bloques: Lista de Bloque del Módulo 1.

        Returns:
            ResultadoFormateo con pdf_bytes o error.
        """
        try:
            if not bloques:
                return ResultadoFormateo(exitoso=False, error="Lista de bloques vacía")

            fuente = self.gestor.resolver(
                self.config.fuente_nombre,
                self.config.fuente_es_google
            )

            flowables = self._construir_flowables(bloques, fuente)

            buffer    = io.BytesIO()
            tamaño_pag = self.TAMAÑOS_PAGINA.get(self.config.tamaño_pagina, letter)

            doc = SimpleDocTemplate(
                buffer,
                pagesize     = tamaño_pag,
                topMargin    = self.config.margen_superior * cm,
                bottomMargin = self.config.margen_inferior * cm,
                leftMargin   = self.config.margen_interior * cm,
                rightMargin  = self.config.margen_exterior * cm,
            )

            numerador = self._crear_numerador(fuente, tamaño_pag)
            doc.build(flowables, onFirstPage=numerador, onLaterPages=numerador)

            return ResultadoFormateo(
                exitoso   = True,
                pdf_bytes = buffer.getvalue(),
                metadatos = {
                    "fuente":        fuente,
                    "tamaño_fuente": self.config.tamaño_fuente,
                    "tamaño_pagina": self.config.tamaño_pagina.value,
                    "tamaño_bytes":  len(buffer.getvalue()),
                }
            )

        except Exception as e:
            import traceback
            return ResultadoFormateo(
                exitoso=False,
                error=f"Error durante formateo: {e}\n{traceback.format_exc()}"
            )

    # ------------------------------------------------------------------
    # Construcción de flowables
    # ------------------------------------------------------------------

    def _construir_flowables(self, bloques: List[Bloque], fuente: str) -> list:
        cfg = self.config
        flowables = []

        # Estilos de párrafo base
        estilo_cuerpo = ParagraphStyle(
            name            = 'Cuerpo',
            fontName        = fuente,
            fontSize        = cfg.tamaño_fuente,
            leading         = cfg.tamaño_fuente * cfg.interlineado,
            alignment       = 4,      # justificado
            spaceAfter      = cfg.espaciado_entre_parrafos * cm,
            firstLineIndent = cfg.sangria_parrafo * cm,
        )
        estilo_sin_sangria = ParagraphStyle(
            name            = 'CuerpoSinSangria',
            parent          = estilo_cuerpo,
            firstLineIndent = 0,
        )

        i = 0
        primer_elemento = True   # controla si el próximo párrafo lleva sangría

        while i < len(bloques):
            bloque = bloques[i]

            # ── Marcadores de página: ignorar ─────────────────────────
            if bloque.tipo == 'pagina_nueva':
                i += 1
                continue

            # ── CAPÍTULO: buscar titulo_principal siguiente y agrupar ─
            if bloque.tipo == 'capitulo':
                if cfg.nueva_pagina_capitulo and not primer_elemento:
                    flowables.append(PageBreak())

                # Look-ahead: buscar el titulo_principal inmediato
                titulo_bloque = None
                j = i + 1
                j_titulo = None
                while j < len(bloques):
                    if bloques[j].tipo == 'pagina_nueva':
                        j += 1
                        continue
                    if bloques[j].tipo == 'titulo_principal':
                        titulo_bloque = bloques[j]
                        j_titulo = j
                    break

                grupo = self._flowables_encabezado_capitulo(
                    bloque.texto,
                    titulo_bloque.texto if titulo_bloque else None,
                    fuente
                )
                flowables.append(KeepTogether(grupo))

                i = (j_titulo + 1) if j_titulo is not None else (i + 1)
                primer_elemento = True   # primer párrafo del cap sin sangría
                continue

            # ── TÍTULO PRINCIPAL sin capítulo precedente ───────────────
            if bloque.tipo == 'titulo_principal':
                if cfg.nueva_pagina_capitulo and not primer_elemento:
                    flowables.append(PageBreak())
                flowables.append(self._flowable_titulo_seccion(bloque.texto, fuente))
                flowables.append(Spacer(1, cfg.espaciado_post_titulo * cm))
                i += 1
                primer_elemento = True
                continue

            # ── PÁRRAFO normal ─────────────────────────────────────────
            if bloque.tipo in ('parrafo', 'subtitulo', 'cita') or bloque.tipo:
                if bloque.texto.strip():
                    estilo = estilo_sin_sangria if primer_elemento else estilo_cuerpo
                    flowables.append(Paragraph(self._xml(bloque.texto), estilo))
                    primer_elemento = False
                i += 1
                continue

            i += 1

        return flowables

    def _flowables_encabezado_capitulo(
        self,
        numero: str,
        titulo: Optional[str],
        fuente: str
    ) -> list:
        """Genera el grupo visual número + título del capítulo."""
        cfg = self.config
        resultado = [Spacer(1, 1.5 * cm)]

        # Número del capítulo — pequeño, centrado
        estilo_num = ParagraphStyle(
            name      = 'NumCap',
            fontName  = fuente,
            fontSize  = cfg.tamaño_fuente * 0.9,
            leading   = cfg.tamaño_fuente * 0.9 * 1.2,
            alignment = 1,
            spaceAfter= 0.3 * cm,
        )
        resultado.append(Paragraph(self._xml(numero), estilo_num))

        # Título del capítulo — grande, centrado
        if titulo:
            tam = cfg.tamaño_titulo_capitulo or (cfg.tamaño_fuente * 1.8)
            estilo_tit = ParagraphStyle(
                name      = 'TitCap',
                fontName  = fuente,
                fontSize  = tam,
                leading   = tam * 1.2,
                alignment = 1,
                spaceAfter= cfg.espaciado_post_titulo * cm,
            )
            resultado.append(Paragraph(self._xml(titulo), estilo_tit))

        resultado.append(Spacer(1, cfg.espaciado_post_titulo * cm))
        return resultado

    def _flowable_titulo_seccion(self, texto: str, fuente: str) -> Paragraph:
        """Título de sección sin número (Introducción, Índice, Epílogo…)."""
        tam = self.config.tamaño_titulo_capitulo or (self.config.tamaño_fuente * 1.8)
        estilo = ParagraphStyle(
            name      = 'TitSec',
            fontName  = fuente,
            fontSize  = tam,
            leading   = tam * 1.2,
            alignment = 1,
            spaceAfter= 0.4 * cm,
        )
        return Paragraph(self._xml(texto), estilo)

    # ------------------------------------------------------------------
    # Numeración de páginas
    # ------------------------------------------------------------------

    def _crear_numerador(self, fuente: str, tamaño_pag: tuple):
        """Callback de ReportLab para dibujar el número de página."""
        cfg   = self.config
        ancho, alto = tamaño_pag

        def _cb(canvas_obj, doc):
            num = doc.page
            if not cfg.numerar_paginas or num < cfg.pagina_inicio_numeracion:
                return

            texto   = cfg.formato_numero.format(numero=num)
            tam_num = max(cfg.tamaño_fuente - 2, 8)
            canvas_obj.setFont(fuente, tam_num)

            pos = cfg.posicion_numero.value

            # Vertical
            y = (alto - cfg.margen_superior * cm + 0.4 * cm
                 if 'arriba' in pos
                 else cfg.margen_inferior * cm - 0.6 * cm)

            # Horizontal
            if 'izquierda' in pos:
                canvas_obj.drawString(cfg.margen_interior * cm, y, texto)
            elif 'derecha' in pos:
                canvas_obj.drawRightString(ancho - cfg.margen_exterior * cm, y, texto)
            elif 'centro' in pos:
                canvas_obj.drawCentredString(ancho / 2, y, texto)
            elif pos == 'exterior':
                if num % 2 == 0:   # página par → número a la izquierda
                    canvas_obj.drawString(cfg.margen_interior * cm, y, texto)
                else:              # página impar → número a la derecha
                    canvas_obj.drawRightString(ancho - cfg.margen_exterior * cm, y, texto)

        return _cb

    # ------------------------------------------------------------------
    # Utilidades
    # ------------------------------------------------------------------

    @staticmethod
    def _xml(texto: str) -> str:
        """Escapa caracteres XML para que ReportLab no falle."""
        return (texto
                .replace("&", "&amp;")
                .replace("<", "&lt;")
                .replace(">", "&gt;"))


# ---------------------------------------------------------------------------
# Función de conveniencia
# ---------------------------------------------------------------------------

def formatear_pdf(
    bloques: List[Bloque],
    config:  ConfiguracionFormateo,
    ruta_salida: Optional[str] = None
) -> ResultadoFormateo:
    """
    Formatea una lista de bloques a PDF.

    Uso:
        resultado = formatear_pdf(bloques, config, "output.pdf")
    """
    formatter = PDFFormatter(config)
    resultado  = formatter.formatear(bloques)

    if resultado.exitoso and ruta_salida:
        try:
            Path(ruta_salida).parent.mkdir(parents=True, exist_ok=True)
            Path(ruta_salida).write_bytes(resultado.pdf_bytes)
        except Exception as e:
            print(f"[formateador] Error guardando: {e}")

    return resultado
