"""
MODELOS Y TIPOS PARA NOCTUA PRESS
===================================

Define estructuras de datos para configuración y parámetros.
"""

from dataclasses import dataclass, field
from typing import Literal, Optional
from enum import Enum


class TamañoPagina(str, Enum):
    """Tamaños de página soportados"""
    LETTER = "LETTER"  # 8.5 x 11 pulgadas
    A4 = "A4"  # 210 x 297 mm
    LEGAL = "LEGAL"  # 8.5 x 14 pulgadas


class PosicionNumeracion(str, Enum):
    """Posiciones para numeración de páginas"""
    ARRIBA_IZQUIERDA = "arriba_izquierda"
    ARRIBA_CENTRO = "arriba_centro"
    ARRIBA_DERECHA = "arriba_derecha"
    ABAJO_IZQUIERDA = "abajo_izquierda"
    ABAJO_CENTRO = "abajo_centro"
    ABAJO_DERECHA = "abajo_derecha"
    ABAJO_EXTERIOR = "abajo_exterior"  # Exterior del lomo
    ARRIBA_EXTERIOR = "arriba_exterior"


@dataclass
class ConfiguracionFormateo:
    """
    Parámetros tipográficos y de layout del documento.
    
    Todos los valores de longitud en unidades estándar:
    - Longitudes: centímetros (cm)
    - Tamaños de fuente: puntos (pt)
    - Sangrado: milímetros (mm)
    """
    
    # ===== TIPOGRAFÍA =====
    fuente_nombre: str = "Times New Roman"
    """Nombre de fuente del sistema o Google Fonts (ej: "Merriweather", "Lora")"""
    
    fuente_es_google: bool = False
    """Si True, se descargará de Google Fonts"""
    
    tamaño_fuente: int = 12
    """Tamaño base de fuente en puntos (pt)"""
    
    interlineado: float = 1.5
    """Multiplicador de interlineado (1.0 = simple, 1.5 = semidoble, 2.0 = doble)"""
    
    # ===== MÁRGENES (CM) =====
    margen_superior: float = 2.0
    """Margen superior de página (cm)"""
    
    margen_inferior: float = 2.0
    """Margen inferior de página (cm)"""
    
    margen_interior: float = 2.0
    """Margen interior (lado del lomo, izquierda en pág impar, derecha en par) (cm)"""
    
    margen_exterior: float = 1.5
    """Margen exterior (lado opuesto al lomo) (cm)"""
    
    # ===== PÁRRAFOS =====
    sangria_parrafo: float = 0.5
    """Sangría de primera línea de párrafo (cm)"""
    
    espaciado_entre_parrafos: float = 0.3
    """Espacio entre párrafos (cm)"""
    
    espaciado_entre_capitulos: float = 1.0
    """Espacio entre título de capítulo y primer párrafo (cm)"""
    
    # ===== PÁGINA =====
    tamaño_pagina: TamañoPagina = TamañoPagina.LETTER
    """Tamaño de página destino"""
    
    # ===== NUMERACIÓN DE PÁGINAS =====
    numerar_paginas: bool = True
    """Habilitar numeración de páginas"""
    
    posicion_numero: PosicionNumeracion = PosicionNumeracion.ABAJO_EXTERIOR
    """Posición de números de página"""
    
    formato_numero: str = "{numero}"
    """Formato del número de página (ej: "Pág. {numero}", "- {numero} -")"""
    
    # ===== IMPRESIÓN =====
    bleed: float = 0.0
    """Sangrado de impresión en milímetros (mm). 0 = sin sangrado"""
    
    cmyk: bool = False
    """Usar modelo de color CMYK (para impresión profesional)"""
    
    # ===== TÍTULOS DE CAPÍTULOS =====
    tamaño_titulo_capitulo: Optional[int] = None
    """Tamaño de títulos de capítulo en puntos. None = 1.5x tamaño base"""
    
    negrilla_titulo: bool = True
    """Mostrar títulos de capítulo en negrilla"""
    
    nueva_pagina_capitulo: bool = True
    """Iniciar cada capítulo en nueva página"""
    
    def __post_init__(self):
        """Validaciones después de inicialización"""
        if self.tamaño_fuente < 8 or self.tamaño_fuente > 72:
            raise ValueError("Tamaño de fuente debe estar entre 8 y 72 pt")
        
        if self.interlineado < 0.8 or self.interlineado > 3.0:
            raise ValueError("Interlineado debe estar entre 0.8 y 3.0")
        
        if self.margen_superior < 0.5 or self.margen_superior > 5.0:
            raise ValueError("Márgenes deben estar entre 0.5 y 5.0 cm")


@dataclass
class ResultadoFormateo:
    """Resultado de la operación de formateo"""
    
    exitoso: bool
    """Si el formateo fue exitoso"""
    
    pdf_bytes: Optional[bytes] = None
    """Contenido del PDF en bytes"""
    
    error: Optional[str] = None
    """Mensaje de error si no fue exitoso"""
    
    metadatos: dict = field(default_factory=dict)
    """Metadatos del PDF generado (páginas, tamaño, etc)"""


# LISTA DE GOOGLE FONTS DISPONIBLES (subset popular)
GOOGLE_FONTS_DISPONIBLES = {
    "Merriweather": {
        "url": "https://fonts.gstatic.com/s/merriweather/v30/u-4n4u4t-2-4nsUIEQHjIIty32Pr-xzOp0q-FPgIg.woff2",
        "familia": "serif",
        "peso": "normal"
    },
    "Lora": {
        "url": "https://fonts.gstatic.com/s/lora/v35/0QI6MX1D_JOuMw_U_fX0dDVV_MzlzbTc-gf73OAh7go.woff2",
        "familia": "serif",
        "peso": "normal"
    },
    "Playfair Display": {
        "url": "https://fonts.gstatic.com/s/playfairdisplay/v36/nuFiD-vYSZviVYUb_rj3ij__anPVPZpuIrKz0Zwytw.woff2",
        "familia": "serif",
        "peso": "normal"
    },
    "Crimson Text": {
        "url": "https://fonts.gstatic.com/s/crimsontext/v13/wlpVgwfS5wy3yChzIHMwkVkLIrc.woff2",
        "familia": "serif",
        "peso": "normal"
    },
    "Libre Baskerville": {
        "url": "https://fonts.gstatic.com/s/librebaskerville/v17/Lh68cXy6TfvdEvOHLr-SuvKqPEpQz--0HiKpDTk.woff2",
        "familia": "serif",
        "peso": "normal"
    },
    "Droid Serif": {
        "url": "https://fonts.gstatic.com/s/droidserif/v20/ga6iaw1J5X0T9RW6j9bBVFkLXy8.woff2",
        "familia": "serif",
        "peso": "normal"
    },
    "Noto Serif": {
        "url": "https://fonts.gstatic.com/s/notoserif/v21/ga6caw1J5X0T9RW6j9bBVVRESGwT.woff2",
        "familia": "serif",
        "peso": "normal"
    },
    "EB Garamond": {
        "url": "https://fonts.gstatic.com/s/ebgaramond/v25/jizYRExUiTo99ln6piQ3KmZKqUjwjkPxvpT87d8P37sFxA_Pg.woff2",
        "familia": "serif",
        "peso": "normal"
    },
    "Open Sans": {
        "url": "https://fonts.gstatic.com/s/opensans/v36/memSYaGs126MiZpBA-UvWbX5PgLi.woff2",
        "familia": "sans-serif",
        "peso": "normal"
    },
    "Roboto": {
        "url": "https://fonts.gstatic.com/s/roboto/v32/KFOmCnqEu92Fr1Me5Q6hovYkLdfyLl47.woff2",
        "familia": "sans-serif",
        "peso": "normal"
    },
}
