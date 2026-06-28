"""
MÓDULO 1: EXTRACCIÓN DE TEXTO DE PDF
=====================================

Extrae texto de archivos PDF preservando estructura de párrafos y capítulos.
Retorna un objeto estructurado con metadatos.

Responsabilidad: Convertir PDF crudo → texto limpio y estructurado
"""

import pdfplumber
import re
from pathlib import Path
from typing import Dict, List, Any, Optional
from dataclasses import dataclass
import chardet


@dataclass
class ExtraccionResultado:
    """Resultado estructurado de la extracción"""
    contenido: List[Dict[str, Any]]
    metadatos: Dict[str, Any]
    exitoso: bool
    error: Optional[str] = None


class PDFExtractor:
    """
    Extrae texto de PDFs manteniendo estructura de párrafos y capítulos.
    """
    
    # Patrones para detectar capítulos
    PATRONES_CAPITULO = [
        r'^Capítulo\s+(\d+)',
        r'^CAPÍTULO\s+(\d+)',
        r'^Chapter\s+(\d+)',
        r'^CHAPTER\s+(\d+)',
        r'^Part\s+(\d+)',
        r'^PART\s+(\d+)',
        r'^Parte\s+(\d+)',
        r'^PARTE\s+(\d+)',
    ]
    
    def __init__(self):
        self.encoding_detectado = 'utf-8'
    
    def extraer(self, ruta_pdf: str) -> ExtraccionResultado:
        """
        Extrae texto completo del PDF.
        
        Args:
            ruta_pdf: Ruta absoluta al archivo PDF
            
        Returns:
            ExtraccionResultado con estructura de contenido y metadatos
        """
        try:
            ruta = Path(ruta_pdf)
            
            if not ruta.exists():
                return ExtraccionResultado(
                    contenido=[],
                    metadatos={},
                    exitoso=False,
                    error=f"Archivo no encontrado: {ruta_pdf}"
                )
            
            if not ruta.suffix.lower() == '.pdf':
                return ExtraccionResultado(
                    contenido=[],
                    metadatos={},
                    exitoso=False,
                    error=f"Archivo no es PDF: {ruta.suffix}"
                )
            
            # Abrir PDF y extraer texto
            with pdfplumber.open(ruta_pdf) as pdf:
                texto_completo = self._extraer_texto_pdf(pdf)
                num_paginas = len(pdf.pages)
            
            # Procesar texto
            contenido = self._procesar_contenido(texto_completo)
            
            # Calcular metadatos
            metadatos = self._calcular_metadatos(
                contenido=contenido,
                texto_original=texto_completo,
                num_paginas=num_paginas,
                ruta_archivo=ruta_pdf
            )
            
            return ExtraccionResultado(
                contenido=contenido,
                metadatos=metadatos,
                exitoso=True
            )
        
        except Exception as e:
            return ExtraccionResultado(
                contenido=[],
                metadatos={},
                exitoso=False,
                error=f"Error durante extracción: {str(e)}"
            )
    
    def _extraer_texto_pdf(self, pdf) -> str:
        """Extrae texto crudo de todas las páginas del PDF"""
        texto_paginas = []
        
        for pagina in pdf.pages:
            texto = pagina.extract_text()
            if texto:
                texto_paginas.append(texto)
        
        # Unir con separador de página para contexto
        texto_completo = "\n\n--- [SALTO DE PÁGINA] ---\n\n".join(texto_paginas)
        return texto_completo
    
    def _procesar_contenido(self, texto_crudo: str) -> List[Dict[str, Any]]:
        """
        Procesa texto crudo y lo estructura en párrafos y capítulos.
        
        Lógica:
        1. Normalizar espacios en blanco
        2. Dividir por capítulos primero (separador principal)
        3. Luego dividir cada capítulo en párrafos
        4. Retornar estructura jerárquica
        """
        contenido = []
        
        # Paso 1: Normalizar espacios
        texto_limpio = self._normalizar_espacios(texto_crudo)
        
        # Paso 2: Dividir por saltos de página
        partes_pagina = texto_limpio.split("--- [SALTO DE PÁGINA] ---")
        texto_unificado = "\n\n".join(partes_pagina)
        
        # Paso 3: PRIMERO dividir por capítulos (buscar líneas que sean títulos de capítulo)
        # Dividir en líneas y buscar líneas que sean capítulos
        lineas = texto_unificado.split('\n')
        
        bloques = []  # Lista de (es_capitulo, contenido)
        bloque_actual = []
        
        for linea in lineas:
            linea = linea.strip()
            if not linea:
                continue
            
            # Verificar si esta línea es un título de capítulo
            es_capitulo, titulo = self._detectar_capitulo(linea)
            
            if es_capitulo:
                # Si hay bloque anterior, guardarlo
                if bloque_actual:
                    bloques.append((False, '\n'.join(bloque_actual)))
                    bloque_actual = []
                
                # Guardar el capítulo
                bloques.append((True, linea))
            else:
                bloque_actual.append(linea)
        
        # Guardar último bloque
        if bloque_actual:
            bloques.append((False, '\n'.join(bloque_actual)))
        
        # Paso 4: Procesar bloques para construir estructura jerárquica
        capitulo_actual = None
        contenido_capitulo_actual = []
        
        for es_capitulo, texto_bloque in bloques:
            if es_capitulo:
                # Es un capítulo
                # Guardar capítulo anterior si existe
                if capitulo_actual is not None:
                    contenido.append({
                        "tipo": "capítulo",
                        "título": capitulo_actual,
                        "contenido": contenido_capitulo_actual
                    })
                
                # Iniciar nuevo capítulo
                capitulo_actual = texto_bloque
                contenido_capitulo_actual = []
            
            else:
                # Es contenido (párrafos)
                # Dividir en párrafos por líneas en blanco
                parrafos_bloque = re.split(r'\n\n+', texto_bloque)
                
                for parrafo_texto in parrafos_bloque:
                    parrafo_texto = parrafo_texto.strip()
                    if not parrafo_texto:
                        continue
                    
                    elemento = {
                        "tipo": "párrafo",
                        "texto": parrafo_texto
                    }
                    
                    if capitulo_actual is not None:
                        contenido_capitulo_actual.append(elemento)
                    else:
                        contenido.append(elemento)
        
        # Guardar último capítulo si existe
        if capitulo_actual is not None and contenido_capitulo_actual:
            contenido.append({
                "tipo": "capítulo",
                "título": capitulo_actual,
                "contenido": contenido_capitulo_actual
            })
        
        return contenido
    
    def _normalizar_espacios(self, texto: str) -> str:
        """
        Normaliza espacios en blanco del texto.
        
        - Elimina espacios múltiples en líneas
        - Preserva párrafos (líneas en blanco)
        - Remueve caracteres de control
        """
        # Reemplazar múltiples espacios con uno
        texto = re.sub(r' +', ' ', texto)
        
        # Reemplazar múltiples saltos de línea con doble salto (párrafo)
        texto = re.sub(r'\n\n\n+', '\n\n', texto)
        
        # Remover espacios al inicio/fin de líneas
        lineas = [linea.strip() for linea in texto.split('\n')]
        texto = '\n'.join(lineas)
        
        # Remover caracteres de control (excepto \n)
        texto = ''.join(char if char.isprintable() or char == '\n' else '' for char in texto)
        
        return texto
    
    def _detectar_capitulo(self, texto: str) -> tuple[bool, Optional[str]]:
        """
        Detecta si un párrafo es título de capítulo.
        
        Returns:
            (es_capítulo, título_capítulo)
        """
        # Limitar búsqueda a primeras líneas (títulos suelen ser cortos)
        primera_linea = texto.split('\n')[0].strip()
        
        if len(primera_linea) > 100:
            return False, None
        
        # Probar patrones
        for patron in self.PATRONES_CAPITULO:
            if re.match(patron, primera_linea, re.IGNORECASE):
                return True, primera_linea
        
        return False, None
    
    def _calcular_metadatos(
        self,
        contenido: List[Dict],
        texto_original: str,
        num_paginas: int,
        ruta_archivo: str
    ) -> Dict[str, Any]:
        """Calcula metadatos de la extracción"""
        
        # Contar palabras y caracteres
        palabras = len(texto_original.split())
        caracteres = len(texto_original)
        
        # Contar capítulos y párrafos
        capitulos = sum(1 for item in contenido if item["tipo"] == "capítulo")
        parrafos_totales = sum(
            1 for item in contenido if item["tipo"] == "párrafo"
        )
        
        # Contar párrafos dentro de capítulos
        for item in contenido:
            if item["tipo"] == "capítulo":
                parrafos_totales += len(item.get("contenido", []))
        
        return {
            "archivo": Path(ruta_archivo).name,
            "ruta_completa": str(ruta_archivo),
            "páginas_originales": num_paginas,
            "palabras_totales": palabras,
            "caracteres_totales": caracteres,
            "capítulos_detectados": capitulos,
            "párrafos_totales": parrafos_totales,
            "encoding": self.encoding_detectado,
            "tamaño_bytes": Path(ruta_archivo).stat().st_size
        }


# Función auxiliar para uso simple
def extraer_pdf(ruta_pdf: str) -> ExtraccionResultado:
    """
    Función de conveniencia para extraer PDF en una línea.
    
    Uso:
        resultado = extraer_pdf("documento.pdf")
        if resultado.exitoso:
            print(resultado.contenido)
    """
    extractor = PDFExtractor()
    return extractor.extraer(ruta_pdf)
