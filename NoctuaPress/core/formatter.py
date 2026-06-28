"""
MÓDULO 2: MOTOR DE FORMATEO
============================

Genera PDF formateado a partir de contenido estructurado.
Aplica parámetros tipográficos y maneja Google Fonts.

Responsabilidad: Contenido estructurado + config → PDF listo para imprimir
"""

import os
import io
import requests
from pathlib import Path
from typing import Dict, List, Any, Optional, Tuple
from urllib.parse import quote

from reportlab.lib.pagesizes import letter, A4, legal
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm, mm
from reportlab.lib.colors import HexColor, black
from reportlab.pdfgen import canvas
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, Table, TableStyle
from reportlab.platypus.flowables import Flowable
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont

from .models import ConfiguracionFormateo, ResultadoFormateo, GOOGLE_FONTS_DISPONIBLES, TamañoPagina


class GestorFonts:
    """Gestor de fuentes del sistema y Google Fonts"""
    
    # Mapeo de nombres amigables a nombres de ReportLab
    FUENTES_SISTEMA = {
        "Times New Roman": "Times-Roman",
        "Helvetica": "Helvetica",
        "Courier": "Courier",
        "Symbol": "Symbol",
        "Zapf Dingbats": "ZapfDingbats",
        "Arial": "Helvetica",  # Fallback
        "Verdana": "Helvetica",  # Fallback
        "Georgia": "Times-Roman",  # Fallback
    }
    
    def __init__(self, carpeta_cache: str = "assets/fonts"):
        self.carpeta_cache = Path(carpeta_cache)
        self.carpeta_cache.mkdir(parents=True, exist_ok=True)
        self._fonts_registrados = {}
    
    def obtener_fuente(self, nombre_fuente: str, es_google: bool = False) -> str:
        """
        Obtiene el nombre de la fuente para usar en ReportLab.
        
        Args:
            nombre_fuente: Nombre de la fuente
            es_google: Si es True, intenta descargar de Google Fonts
            
        Returns:
            Nombre de fuente registrada en ReportLab
        """
        # Si ya está registrado, devolver
        if nombre_fuente in self._fonts_registrados:
            return self._fonts_registrados[nombre_fuente]
        
        if es_google:
            return self._obtener_google_font(nombre_fuente)
        else:
            # Convertir nombre amigable a nombre de ReportLab
            nombre_rl = self.FUENTES_SISTEMA.get(nombre_fuente, "Times-Roman")
            self._fonts_registrados[nombre_fuente] = nombre_rl
            return nombre_rl
    
    def _obtener_google_font(self, nombre_fuente: str) -> str:
        """Descarga y cachea Google Font"""
        
        # Verificar si está en la lista disponible
        if nombre_fuente not in GOOGLE_FONTS_DISPONIBLES:
            print(f"⚠ Google Font '{nombre_fuente}' no disponible, usando Times New Roman")
            return "Times-Roman"
        
        info_fuente = GOOGLE_FONTS_DISPONIBLES[nombre_fuente]
        url = info_fuente["url"]
        
        # Ruta local
        nombre_archivo = f"{nombre_fuente.replace(' ', '_')}.woff2"
        ruta_local = self.carpeta_cache / nombre_archivo
        
        # Si ya está descargado, registrar y devolver
        if ruta_local.exists():
            self._registrar_fuente_local(nombre_fuente, str(ruta_local))
            return nombre_fuente
        
        # Descargar
        print(f"⬇ Descargando Google Font: {nombre_fuente}...")
        try:
            response = requests.get(url, timeout=10)
            response.raise_for_status()
            
            ruta_local.write_bytes(response.content)
            print(f"✓ Descargado: {ruta_local}")
            
            self._registrar_fuente_local(nombre_fuente, str(ruta_local))
            return nombre_fuente
        
        except Exception as e:
            print(f"❌ Error descargando {nombre_fuente}: {e}")
            print(f"   Usando Times New Roman como fallback")
            return "Times-Roman"
    
    def _registrar_fuente_local(self, nombre: str, ruta: str):
        """Registra una fuente TTF/OTF con ReportLab"""
        try:
            # Intenta registrar solo si es un formato soportado
            if ruta.endswith(('.ttf', '.otf')):
                pdfmetrics.registerFont(TTFont(nombre, ruta))
        except Exception as e:
            print(f"Nota: No se pudo registrar fuente {nombre}: {e}")
        
        self._fonts_registrados[nombre] = nombre


class PDFFormatter:
    """
    Genera PDF formateado a partir de contenido estructurado.
    Aplica parámetros tipográficos y estilos.
    """
    
    # Mapeo de tamaños de página
    TAMAÑOS_PAGINA = {
        TamañoPagina.LETTER: letter,  # (612, 792) puntos
        TamañoPagina.A4: A4,  # (595, 842) puntos
        TamañoPagina.LEGAL: legal,  # (612, 1008) puntos
    }
    
    def __init__(self, config: ConfiguracionFormateo):
        self.config = config
        self.gestor_fonts = GestorFonts()
        self._pdf_buffer = io.BytesIO()
        self._elemento_contenido = []
    
    def formatear(self, contenido: List[Dict[str, Any]]) -> ResultadoFormateo:
        """
        Formatea contenido estructurado en PDF.
        
        Args:
            contenido: Lista de elementos (párrafos, capítulos) del Módulo 1
            
        Returns:
            ResultadoFormateo con PDF en bytes o error
        """
        try:
            # Validar contenido
            if not contenido:
                return ResultadoFormateo(
                    exitoso=False,
                    error="Contenido vacío"
                )
            
            # Obtener fuente
            fuente = self.gestor_fonts.obtener_fuente(
                self.config.fuente_nombre,
                self.config.fuente_es_google
            )
            
            # Procesar contenido en elementos Platypus
            self._construir_elementos(contenido, fuente)
            
            # Generar PDF
            tamaño_pag = self.TAMAÑOS_PAGINA[self.config.tamaño_pagina]
            
            doc = SimpleDocTemplate(
                self._pdf_buffer,
                pagesize=tamaño_pag,
                topMargin=self.config.margen_superior * cm,
                bottomMargin=self.config.margen_inferior * cm,
                leftMargin=self.config.margen_interior * cm,
                rightMargin=self.config.margen_exterior * cm,
            )
            
            # Agregar numeración de páginas
            if self.config.numerar_paginas:
                doc.onFirstPage = self._crear_footer_numero(1, fuente)
                doc.onLaterPages = self._crear_footer_numero(None, fuente)
            
            # Compilar documento
            doc.build(self._elemento_contenido)
            
            # Obtener bytes
            pdf_bytes = self._pdf_buffer.getvalue()
            
            # Metadatos
            metadatos = {
                "tamaño_bytes": len(pdf_bytes),
                "fuente": self.config.fuente_nombre,
                "tamaño_fuente": self.config.tamaño_fuente,
                "tamaño_pagina": self.config.tamaño_pagina.value,
                "numerar_paginas": self.config.numerar_paginas,
            }
            
            return ResultadoFormateo(
                exitoso=True,
                pdf_bytes=pdf_bytes,
                metadatos=metadatos
            )
        
        except Exception as e:
            return ResultadoFormateo(
                exitoso=False,
                error=f"Error durante formateo: {str(e)}"
            )
    
    def _construir_elementos(self, contenido: List[Dict], fuente: str):
        """Construye elementos Platypus desde contenido estructurado"""
        
        for elemento in contenido:
            if elemento["tipo"] == "capítulo":
                # Agregar nueva página para capítulo si está configurado
                if self.config.nueva_pagina_capitulo and len(self._elemento_contenido) > 0:
                    self._elemento_contenido.append(PageBreak())
                
                # Título del capítulo
                titulo = elemento["título"]
                tamaño_titulo = self.config.tamaño_titulo_capitulo or int(self.config.tamaño_fuente * 1.5)
                
                estilo_titulo = ParagraphStyle(
                    'TituloCapitulo',
                    fontName=fuente,
                    fontSize=tamaño_titulo,
                    textColor=black,
                    spaceAfter=self.config.espaciado_entre_capitulos * cm,
                    bold=self.config.negrilla_titulo,
                    alignment=0,  # 0=left, 1=center, 2=right
                )
                
                parrafo_titulo = Paragraph(titulo, estilo_titulo)
                self._elemento_contenido.append(parrafo_titulo)
                
                # Contenido dentro del capítulo
                for subelemento in elemento.get("contenido", []):
                    if subelemento["tipo"] == "párrafo":
                        self._agregar_parrafo(subelemento["texto"], fuente)
            
            elif elemento["tipo"] == "párrafo":
                self._agregar_parrafo(elemento["texto"], fuente)
    
    def _agregar_parrafo(self, texto: str, fuente: str):
        """Agrega un párrafo al documento"""
        
        estilo_parrafo = ParagraphStyle(
            'ParrafoNormal',
            fontName=fuente,
            fontSize=self.config.tamaño_fuente,
            leading=self.config.tamaño_fuente * self.config.interlineado,
            alignment=4,  # 4=justify
            spaceAfter=self.config.espaciado_entre_parrafos * cm,
            firstLineIndent=self.config.sangria_parrafo * cm,
        )
        
        # Crear párrafo
        parrafo = Paragraph(texto, estilo_parrafo)
        self._elemento_contenido.append(parrafo)
        self._elemento_contenido.append(Spacer(1, 0.1 * cm))
    
    def _crear_footer_numero(self, numero_fijo: Optional[int], fuente: str):
        """Crea función para agregar número de página en footer"""
        
        def footer_callback(canvas_obj, doc):
            numero = numero_fijo or doc.page
            texto_numero = self.config.formato_numero.format(numero=numero)
            
            # Calcular posición según configuración
            margen_bottom = 0.5 * cm
            width, height = doc.pagesize
            
            canvas_obj.setFont(fuente, self.config.tamaño_fuente - 2)
            
            # Determinar posición horizontal
            posicion = self.config.posicion_numero.value
            if 'izquierda' in posicion:
                x = 1 * cm
            elif 'derecha' in posicion:
                x = width - 1 * cm
            elif 'centro' in posicion:
                x = width / 2
            elif 'exterior' in posicion:
                # En página impar (izquierda), margen exterior es derecha
                x = width - self.config.margen_exterior * cm - 0.5 * cm if numero % 2 == 1 else self.config.margen_interior * cm + 0.5 * cm
            else:
                x = width / 2
            
            # Determinar posición vertical
            y = margen_bottom if 'abajo' in posicion else height - margen_bottom
            
            canvas_obj.drawString(x, y, texto_numero)
        
        return footer_callback
    
    def guardar(self, ruta_salida: str) -> bool:
        """
        Guarda el PDF a archivo.
        
        Args:
            ruta_salida: Ruta destino del archivo PDF
            
        Returns:
            True si fue exitoso, False en caso contrario
        """
        try:
            Path(ruta_salida).parent.mkdir(parents=True, exist_ok=True)
            
            with open(ruta_salida, 'wb') as f:
                f.write(self._pdf_buffer.getvalue())
            
            print(f"✓ PDF guardado: {ruta_salida}")
            return True
        
        except Exception as e:
            print(f"❌ Error guardando PDF: {e}")
            return False


# Función auxiliar para uso simple
def formatear_pdf(contenido: List[Dict], config: ConfiguracionFormateo, ruta_salida: Optional[str] = None) -> ResultadoFormateo:
    """
    Función de conveniencia para formatear PDF en una línea.
    
    Uso:
        resultado = formatear_pdf(contenido, config, "output.pdf")
        if resultado.exitoso:
            print("PDF creado exitosamente")
    """
    formatter = PDFFormatter(config)
    resultado = formatter.formatear(contenido)
    
    if resultado.exitoso and ruta_salida:
        formatter.guardar(ruta_salida)
    
    return resultado
