"""
MÓDULO 1: EXTRACCIÓN DE TEXTO DE PDF
=====================================

Extrae texto de archivos PDF preservando estructura de párrafos y capítulos.
Usa análisis de coordenadas Y y tamaños de fuente para detectar la estructura real
del documento, en lugar de depender solo del texto plano.

Responsabilidad: Convertir PDF crudo → texto limpio y estructurado
Desacoplado de la interfaz para migración futura a FastAPI.
"""

import pdfplumber
import re
from pathlib import Path
from typing import Dict, List, Any, Optional, Tuple
from dataclasses import dataclass, field


# ---------------------------------------------------------------------------
# Modelos de datos
# ---------------------------------------------------------------------------

@dataclass
class Bloque:
    """Representa un bloque de contenido extraído (párrafo, título, etc.)"""
    tipo: str           # 'titulo_principal', 'subtitulo', 'capitulo', 'parrafo', 'cita', 'pagina_nueva'
    texto: str
    pagina_origen: int  # Número de página (base 1)
    tamaño_fuente: float = 0.0  # Tamaño de fuente dominante del bloque


@dataclass
class ExtraccionResultado:
    """Resultado estructurado de la extracción"""
    bloques: List[Bloque] = field(default_factory=list)
    metadatos: Dict[str, Any] = field(default_factory=dict)
    exitoso: bool = False
    error: Optional[str] = None


# ---------------------------------------------------------------------------
# Extractor principal
# ---------------------------------------------------------------------------

class PDFExtractor:
    """
    Extrae texto de PDFs manteniendo estructura real de párrafos y capítulos.

    Estrategia:
    1. Por cada página, extraer palabras con coordenadas (x, y) y tamaño de fuente.
    2. Agrupar palabras en líneas según proximidad vertical (tolerancia ~2pt).
    3. Detectar saltos de párrafo cuando el gap entre líneas supera 1.5× el interlineado normal.
    4. Clasificar cada bloque según tamaño de fuente dominante.
    5. Devolver lista plana de Bloque, ordenada como aparece en el libro.
    """

    # Multiplicador: si el gap entre líneas supera esto × interlineado → nuevo párrafo
    UMBRAL_PARRAFO = 1.45

    # Tamaño mínimo de fuente para considerar un bloque como título principal
    TAMAÑO_TITULO_PRINCIPAL = 16.0

    # Tamaño mínimo para subtítulo de capítulo (ej: "Kevin Mitnick: El Fantasma...")
    TAMAÑO_SUBTITULO = 14.0

    # Patrones de texto que identifican encabezados de capítulo aunque vengan en fuente pequeña
    # (ej: "CAPÍTULO I", "EPÍLOGO")
    PATRONES_CAPITULO = re.compile(
        r'^(CAPÍTULO|CAPITULO|CAPÍTULO|CHAPTER|PARTE|PART|EPÍLOGO|EPILOGO|PRÓLOGO|PROLOGO)\b',
        re.IGNORECASE
    )

    def __init__(self):
        pass

    # ------------------------------------------------------------------
    # API pública
    # ------------------------------------------------------------------

    def extraer(self, ruta_pdf: str) -> ExtraccionResultado:
        """
        Punto de entrada principal.

        Args:
            ruta_pdf: Ruta absoluta o relativa al archivo PDF.

        Returns:
            ExtraccionResultado con lista de bloques estructurados y metadatos.
        """
        ruta = Path(ruta_pdf)

        # Validaciones básicas
        if not ruta.exists():
            return ExtraccionResultado(
                exitoso=False,
                error=f"Archivo no encontrado: {ruta_pdf}"
            )
        if ruta.suffix.lower() != '.pdf':
            return ExtraccionResultado(
                exitoso=False,
                error=f"El archivo no es un PDF: {ruta.suffix}"
            )

        try:
            bloques: List[Bloque] = []

            with pdfplumber.open(str(ruta)) as pdf:
                num_paginas = len(pdf.pages)

                for num_pag, pagina in enumerate(pdf.pages, start=1):
                    # Marcar inicio de nueva página (útil para imposición y DOCX)
                    if num_pag > 1:
                        bloques.append(Bloque(
                            tipo='pagina_nueva',
                            texto='',
                            pagina_origen=num_pag
                        ))

                    # Extraer bloques de esta página
                    bloques_pagina = self._extraer_bloques_pagina(pagina, num_pag)
                    bloques.extend(bloques_pagina)

            metadatos = self._calcular_metadatos(bloques, num_paginas, ruta_pdf)

            return ExtraccionResultado(
                bloques=bloques,
                metadatos=metadatos,
                exitoso=True
            )

        except Exception as e:
            return ExtraccionResultado(
                exitoso=False,
                error=f"Error durante extracción: {str(e)}"
            )

    # ------------------------------------------------------------------
    # Extracción por página
    # ------------------------------------------------------------------

    def _extraer_bloques_pagina(self, pagina, num_pag: int) -> List[Bloque]:
        """
        Extrae bloques de texto de una página usando coordenadas.

        Flujo:
        1. Obtener palabras con posición y fuente.
        2. Agrupar en líneas por coordenada Y.
        3. Agrupar líneas en párrafos por gap vertical.
        4. Clasificar cada párrafo según tamaño de fuente.
        """
        # Obtener palabras con metadatos extendidos (incluye tamaño de fuente)
        palabras = pagina.extract_words(
            extra_attrs=['size'],      # Incluir tamaño de fuente por palabra
            keep_blank_chars=False,
            use_text_flow=False        # Respetar orden visual, no stream del PDF
        )

        if not palabras:
            return []

        # Paso 1: Agrupar palabras en líneas por coordenada Y (tolerancia 2pt)
        lineas = self._agrupar_en_lineas(palabras)

        if not lineas:
            return []

        # Paso 2: Calcular el interlineado normal de esta página
        interlineado = self._calcular_interlineado(lineas)

        # Paso 3: Agrupar líneas en párrafos por gap vertical
        grupos_lineas = self._agrupar_en_parrafos(lineas, interlineado)

        # Paso 4: Convertir cada grupo en un Bloque clasificado
        bloques = []
        for grupo in grupos_lineas:
            bloque = self._grupo_a_bloque(grupo, num_pag)
            if bloque:
                bloques.append(bloque)

        return bloques

    def _agrupar_en_lineas(self, palabras: List[Dict]) -> List[Dict]:
        """
        Agrupa palabras en líneas basándose en su coordenada Y ('top').
        Palabras dentro de 2pt de diferencia vertical se consideran la misma línea.

        Returns:
            Lista de dicts con:
            - 'y': coordenada Y representativa de la línea
            - 'texto': texto concatenado de la línea
            - 'tamaño_fuente': tamaño de fuente promedio de la línea
            - 'palabras': lista de palabras originales
        """
        if not palabras:
            return []

        # Ordenar por Y primero, luego por X dentro de cada línea
        palabras_ordenadas = sorted(palabras, key=lambda w: (round(w['top'], 0), w['x0']))

        lineas = []
        linea_actual = [palabras_ordenadas[0]]

        for palabra in palabras_ordenadas[1:]:
            y_actual = palabra['top']
            y_linea = linea_actual[-1]['top']

            if abs(y_actual - y_linea) <= 2.0:
                # Misma línea
                linea_actual.append(palabra)
            else:
                # Nueva línea — guardar la anterior
                lineas.append(self._consolidar_linea(linea_actual))
                linea_actual = [palabra]

        # Guardar última línea
        if linea_actual:
            lineas.append(self._consolidar_linea(linea_actual))

        return lineas

    def _consolidar_linea(self, palabras: List[Dict]) -> Dict:
        """Consolida una lista de palabras en un dict de línea."""
        texto = ' '.join(w['text'] for w in palabras)
        y = sum(w['top'] for w in palabras) / len(palabras)

        # Tamaño de fuente: usar el más frecuente (moda)
        tamaños = [w.get('size', 11.0) for w in palabras if w.get('size')]
        tamaño = max(set(tamaños), key=tamaños.count) if tamaños else 11.0

        return {
            'y': y,
            'texto': texto,
            'tamaño_fuente': tamaño,
            'palabras': palabras
        }

    def _calcular_interlineado(self, lineas: List[Dict]) -> float:
        """
        Calcula el interlineado normal de la página midiendo gaps entre líneas consecutivas.
        Usa la mediana para ignorar gaps grandes (entre párrafos) y pequeños atípicos.
        """
        if len(lineas) < 2:
            return 17.0  # Valor razonable por defecto para 11pt

        gaps = []
        for i in range(1, len(lineas)):
            gap = lineas[i]['y'] - lineas[i-1]['y']
            if gap > 0:
                gaps.append(gap)

        if not gaps:
            return 17.0

        # Mediana de los gaps más pequeños (interlineado real, sin párrafos)
        gaps_ordenados = sorted(gaps)
        # Tomar el tercio inferior para evitar contaminar con gaps de párrafo
        n = max(1, len(gaps_ordenados) // 3)
        interlineado = sum(gaps_ordenados[:n]) / n

        return max(interlineado, 10.0)  # Mínimo 10pt

    def _agrupar_en_parrafos(self, lineas: List[Dict], interlineado: float) -> List[List[Dict]]:
        """
        Agrupa líneas en párrafos detectando gaps verticales mayores al umbral.
        Un gap > UMBRAL_PARRAFO × interlineado indica inicio de nuevo párrafo.
        """
        if not lineas:
            return []

        umbral = interlineado * self.UMBRAL_PARRAFO

        grupos = []
        grupo_actual = [lineas[0]]

        for i in range(1, len(lineas)):
            gap = lineas[i]['y'] - lineas[i-1]['y']

            if gap > umbral:
                # Gap grande → nuevo párrafo
                grupos.append(grupo_actual)
                grupo_actual = [lineas[i]]
            else:
                grupo_actual.append(lineas[i])

        if grupo_actual:
            grupos.append(grupo_actual)

        return grupos

    def _grupo_a_bloque(self, lineas_grupo: List[Dict], num_pag: int) -> Optional[Bloque]:
        """
        Convierte un grupo de líneas en un Bloque clasificado.

        Clasificación por tamaño de fuente dominante y patrones de texto:
        - titulo_principal : fuente ≥ 16pt  (portada, título del libro)
        - subtitulo        : fuente ≥ 14pt  (subtítulo de capítulo como "Kevin Mitnick: El Fantasma...")
        - capitulo         : fuente < 14pt pero texto coincide con PATRONES_CAPITULO (ej: "CAPÍTULO I")
        - cita             : fuente menor al cuerpo (ej: 10pt en un doc de 11pt)
        - parrafo          : texto normal de cuerpo
        """
        if not lineas_grupo:
            return None

        # Reconstruir texto del bloque uniendo líneas con espacio
        # Detectar si las líneas forman un párrafo continuo o son independientes
        texto_bloque = self._reconstruir_texto(lineas_grupo)

        if not texto_bloque.strip():
            return None

        # Tamaño de fuente dominante del bloque
        todos_tamaños = [l['tamaño_fuente'] for l in lineas_grupo]
        tamaño_dominante = max(set(todos_tamaños), key=todos_tamaños.count)

        # Clasificar
        primera_linea = lineas_grupo[0]['texto'].strip()

        if tamaño_dominante >= self.TAMAÑO_TITULO_PRINCIPAL:
            tipo = 'titulo_principal'

        elif tamaño_dominante >= self.TAMAÑO_SUBTITULO:
            tipo = 'subtitulo'

        elif self.PATRONES_CAPITULO.match(primera_linea):
            tipo = 'capitulo'

        else:
            tipo = 'parrafo'

        return Bloque(
            tipo=tipo,
            texto=texto_bloque.strip(),
            pagina_origen=num_pag,
            tamaño_fuente=tamaño_dominante
        )

    def _reconstruir_texto(self, lineas: List[Dict]) -> str:
        """
        Reconstruye el texto de un párrafo desde sus líneas.

        Lógica de unión:
        - Si una línea termina con guion de partición → unir sin espacio.
        - Si una línea termina con punto, signo de cierre, comilla → nueva línea en resultado
          (puede ser fin de oración pero dentro del mismo bloque, se une con espacio).
        - En general: unir con espacio (flujo de párrafo continuo).
        """
        if not lineas:
            return ''

        if len(lineas) == 1:
            return lineas[0]['texto']

        partes = []
        for i, linea in enumerate(lineas):
            texto = linea['texto'].strip()
            if not texto:
                continue

            if i < len(lineas) - 1:
                # Verificar si la línea termina con guion de partición de palabra
                if texto.endswith('-'):
                    # Unir sin espacio y sin el guion (era partición tipográfica)
                    partes.append(texto[:-1])
                else:
                    partes.append(texto + ' ')
            else:
                partes.append(texto)

        return ''.join(partes).strip()

    # ------------------------------------------------------------------
    # Metadatos
    # ------------------------------------------------------------------

    def _calcular_metadatos(
        self,
        bloques: List[Bloque],
        num_paginas: int,
        ruta_archivo: str
    ) -> Dict[str, Any]:
        """Calcula estadísticas y metadatos de la extracción."""

        # Filtrar bloques de contenido real (sin marcadores de página)
        bloques_contenido = [b for b in bloques if b.tipo != 'pagina_nueva']

        # Contar por tipo
        conteo_tipos: Dict[str, int] = {}
        for b in bloques_contenido:
            conteo_tipos[b.tipo] = conteo_tipos.get(b.tipo, 0) + 1

        # Texto total para estadísticas
        texto_total = ' '.join(b.texto for b in bloques_contenido)
        palabras_total = len(texto_total.split())
        caracteres_total = len(texto_total)

        return {
            'archivo': Path(ruta_archivo).name,
            'ruta_completa': str(ruta_archivo),
            'páginas_originales': num_paginas,
            'bloques_totales': len(bloques_contenido),
            'palabras_totales': palabras_total,
            'caracteres_totales': caracteres_total,
            'por_tipo': conteo_tipos,
            'tamaño_bytes': Path(ruta_archivo).stat().st_size,
        }


# ---------------------------------------------------------------------------
# Función de conveniencia para uso simple
# ---------------------------------------------------------------------------

def extraer_pdf(ruta_pdf: str) -> ExtraccionResultado:
    """
    Función de conveniencia para extraer un PDF en una sola línea.

    Uso:
        resultado = extraer_pdf("manuscrito.pdf")
        if resultado.exitoso:
            for bloque in resultado.bloques:
                print(bloque.tipo, bloque.texto[:60])
    """
    extractor = PDFExtractor()
    return extractor.extraer(ruta_pdf)
