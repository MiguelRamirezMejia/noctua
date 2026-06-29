"""
MODELOS Y TIPOS PARA NOCTUA PRESS
===================================

Define estructuras de datos compartidas entre todos los módulos.
Este archivo es la única fuente de verdad para ConfiguracionFormateo.

Importar desde aquí en la UI y en todos los módulos core.
"""

from dataclasses import dataclass, field
from typing import Optional
from enum import Enum


class TamañoPagina(str, Enum):
    """Tamaños de página soportados."""
    LETTER = "letter"
    A4     = "a4"
    LEGAL  = "legal"


class PosicionNumeracion(str, Enum):
    """Posiciones para numeración de páginas."""
    ARRIBA_IZQUIERDA = "arriba_izquierda"
    ARRIBA_CENTRO    = "arriba_centro"
    ARRIBA_DERECHA   = "arriba_derecha"
    ABAJO_IZQUIERDA  = "abajo_izquierda"
    ABAJO_CENTRO     = "abajo_centro"
    ABAJO_DERECHA    = "abajo_derecha"
    ABAJO_EXTERIOR   = "exterior"   # Alterna izq/der según página par/impar
    ARRIBA_EXTERIOR  = "arriba_exterior"


@dataclass
class ConfiguracionFormateo:
    """
    Parámetros tipográficos y de layout del documento.
    Única fuente de verdad — importar desde core.models en todos los módulos.

    Unidades:
      - Longitudes : centímetros (cm)
      - Fuentes    : puntos (pt)
      - Bleed      : milímetros (mm)
    """

    # ── Tipografía ──────────────────────────────────────────────────────
    fuente_nombre: str   = "Times-Roman"
    fuente_es_google: bool = False

    tamaño_fuente: float = 11.0       # pt, texto de cuerpo
    interlineado:  float = 1.45       # multiplicador (1.0 = simple)

    # ── Márgenes (cm) ───────────────────────────────────────────────────
    margen_superior:  float = 2.0
    margen_inferior:  float = 2.0
    margen_interior:  float = 2.5    # lado del lomo
    margen_exterior:  float = 2.0

    # ── Párrafos ─────────────────────────────────────────────────────────
    sangria_parrafo:           float = 0.5    # cm, primera línea
    espaciado_entre_parrafos:  float = 0.0    # cm extra entre párrafos

    # ── Capítulos ────────────────────────────────────────────────────────
    nueva_pagina_capitulo:  bool  = True
    espaciado_post_titulo:  float = 0.6   # cm entre título y primer párrafo
    negrilla_titulo:        bool  = True
    tamaño_titulo_capitulo: Optional[float] = None  # None = auto (1.8× cuerpo)

    # ── Página ───────────────────────────────────────────────────────────
    tamaño_pagina: TamañoPagina = TamañoPagina.LETTER

    # ── Numeración ───────────────────────────────────────────────────────
    numerar_paginas:           bool             = True
    posicion_numero:           PosicionNumeracion = PosicionNumeracion.ABAJO_CENTRO
    formato_numero:            str              = "{numero}"
    pagina_inicio_numeracion:  int              = 1

    # ── Impresión ────────────────────────────────────────────────────────
    bleed: float = 0.0    # mm de sangrado
    cmyk:  bool  = False  # color CMYK para impresión profesional

    # ── Compatibilidad: alias para campo renombrado ───────────────────────
    @property
    def espaciado_entre_capitulos(self) -> float:
        """Alias de compatibilidad → espaciado_post_titulo."""
        return self.espaciado_post_titulo

    @espaciado_entre_capitulos.setter
    def espaciado_entre_capitulos(self, value: float):
        self.espaciado_post_titulo = value

    def __post_init__(self):
        # Normalizar nombre de fuente: "Times New Roman" → "Times-Roman"
        _mapa = {
            "Times New Roman": "Times-Roman",
            "Times Roman":     "Times-Roman",
            "Arial":           "Helvetica",
            "Courier New":     "Courier",
        }
        self.fuente_nombre = _mapa.get(self.fuente_nombre, self.fuente_nombre)

        # Normalizar tamaño de página a minúsculas (la UI puede pasar "LETTER")
        if isinstance(self.tamaño_pagina, str):
            self.tamaño_pagina = TamañoPagina(self.tamaño_pagina.lower())

        # Normalizar posición de número
        if isinstance(self.posicion_numero, str):
            try:
                self.posicion_numero = PosicionNumeracion(self.posicion_numero)
            except ValueError:
                self.posicion_numero = PosicionNumeracion.ABAJO_CENTRO

        # Validaciones básicas
        if not (8 <= self.tamaño_fuente <= 72):
            raise ValueError(f"Tamaño de fuente fuera de rango: {self.tamaño_fuente}")
        if not (0.8 <= self.interlineado <= 3.0):
            raise ValueError(f"Interlineado fuera de rango: {self.interlineado}")


@dataclass
class ResultadoFormateo:
    """Resultado de la operación de formateo."""
    exitoso:   bool
    pdf_bytes: Optional[bytes] = None
    error:     Optional[str]   = None
    metadatos: dict            = field(default_factory=dict)
