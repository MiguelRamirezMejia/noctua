"""
MÓDULO 3: IMPOSICIÓN AUTOMÁTICA
================================

Reordena un PDF formateado para impresión en cuadernillo.
Permite generar hojas carta con 2 páginas por cara y 4 páginas por hoja.
"""

import io
from dataclasses import dataclass
from pathlib import Path
from typing import Optional, List, Tuple

from pypdf import PdfReader, PdfWriter, PageObject

from .models import TamañoPagina


@dataclass
class ResultadoImposicion:
    exitoso: bool
    """Indica si la imposición fue exitosa."""
    pdf_bytes: Optional[bytes] = None
    """Bytes del PDF impuesto."""
    paginas_fuente: int = 0
    """Cantidad de páginas originales del PDF fuente."""
    paginas_salida: int = 0
    """Cantidad de páginas del PDF con imposición."""
    error: Optional[str] = None
    """Mensaje de error en caso de fallo."""


class PDFImposer:
    """Clase para reordenar PDF a formato de cuadernillo."""

    TAMAÑOS_PAGINA = {
        TamañoPagina.LETTER: (612, 792),
        TamañoPagina.A4: (595, 842),
        TamañoPagina.LEGAL: (612, 1008),
    }

    def __init__(self, tamaño_salida: TamañoPagina = TamañoPagina.LETTER):
        self.tamaño_salida = tamaño_salida
        self.ancho_salida, self.alto_salida = self.TAMAÑOS_PAGINA[self.tamaño_salida]

    def imponer_pdf(self, ruta_entrada: str, ruta_salida: Optional[str] = None) -> ResultadoImposicion:
        """Genera el PDF impuesto desde un archivo de entrada."""
        try:
            reader = PdfReader(ruta_entrada)
            paginas_fuente = len(reader.pages)
            orden = self._calcular_orden_imposicion(paginas_fuente)

            writer = PdfWriter()
            for pagina_1, pagina_2 in orden:
                page = self._crear_pagina_impuesta(reader, pagina_1, pagina_2)
                writer.add_page(page)

            output_bytes = self._escribir_bytes(writer)

            if ruta_salida:
                Path(ruta_salida).parent.mkdir(parents=True, exist_ok=True)
                with open(ruta_salida, 'wb') as f:
                    f.write(output_bytes)

            return ResultadoImposicion(
                exitoso=True,
                pdf_bytes=output_bytes,
                paginas_fuente=paginas_fuente,
                paginas_salida=len(orden)
            )

        except Exception as e:
            return ResultadoImposicion(
                exitoso=False,
                error=f"Error durante imposición: {e}"
            )

    def imponer_bytes(self, pdf_bytes: bytes, ruta_salida: Optional[str] = None) -> ResultadoImposicion:
        """Genera el PDF impuesto a partir de bytes de PDF."""
        try:
            reader = PdfReader(stream=pdf_bytes)
            paginas_fuente = len(reader.pages)
            orden = self._calcular_orden_imposicion(paginas_fuente)

            writer = PdfWriter()
            for pagina_1, pagina_2 in orden:
                page = self._crear_pagina_impuesta(reader, pagina_1, pagina_2)
                writer.add_page(page)

            output_bytes = self._escribir_bytes(writer)

            if ruta_salida:
                Path(ruta_salida).parent.mkdir(parents=True, exist_ok=True)
                with open(ruta_salida, 'wb') as f:
                    f.write(output_bytes)

            return ResultadoImposicion(
                exitoso=True,
                pdf_bytes=output_bytes,
                paginas_fuente=paginas_fuente,
                paginas_salida=len(orden)
            )
        except Exception as e:
            return ResultadoImposicion(
                exitoso=False,
                error=f"Error durante imposición: {e}"
            )

    def _calcular_orden_imposicion(self, total_paginas: int) -> List[Tuple[Optional[int], Optional[int]]]:
        """Calcula la secuencia de páginas para la imposión de cuadernillo."""
        if total_paginas == 0:
            return []

        total_4 = ((total_paginas + 3) // 4) * 4
        paginas = [i + 1 if i < total_paginas else None for i in range(total_4)]
        orden: List[Tuple[Optional[int], Optional[int]]] = []
        hojas = total_4 // 4

        for i in range(hojas):
            izquierda_frente = paginas[total_4 - 1 - 2 * i]
            derecha_frente = paginas[2 * i]
            izquierda_reverso = paginas[2 * i + 1]
            derecha_reverso = paginas[total_4 - 2 - 2 * i]

            orden.append((izquierda_frente, derecha_frente))
            orden.append((izquierda_reverso, derecha_reverso))

        return orden

    def _crear_pagina_impuesta(self, reader: PdfReader, pagina_1: Optional[int], pagina_2: Optional[int]) -> PageObject:
        """Crea una página de salida con dos páginas fuente impuestas."""
        page_out = PageObject.create_blank_page(width=self.ancho_salida, height=self.alto_salida)

        self._merge_pagina_en_espacio(reader, page_out, pagina_1, 0)
        self._merge_pagina_en_espacio(reader, page_out, pagina_2, self.ancho_salida / 2)

        return page_out

    def _merge_pagina_en_espacio(self, reader: PdfReader, page_out: PageObject, pagina_num: Optional[int], offset_x: float):
        """Escala y posiciona una página fuente en la mitad izquierda o derecha."""
        if pagina_num is None:
            return

        pagina_fuente = reader.pages[pagina_num - 1]
        ancho_fuente = float(pagina_fuente.mediabox.width)
        alto_fuente = float(pagina_fuente.mediabox.height)

        target_width = self.ancho_salida / 2
        target_height = self.alto_salida
        escala = min(target_width / ancho_fuente, target_height / alto_fuente)

        pagina_fuente.scale_by(escala)

        ancho_escalado = float(pagina_fuente.mediabox.width)
        alto_escalado = float(pagina_fuente.mediabox.height)

        x_offset = offset_x + max((target_width - ancho_escalado) / 2, 0)
        y_offset = max((target_height - alto_escalado) / 2, 0)

        page_out.merge_translated_page(pagina_fuente, x_offset, y_offset)

    def _escribir_bytes(self, writer: PdfWriter) -> bytes:
        buffer = io.BytesIO()
        writer.write(buffer)
        return buffer.getvalue()


# Función conveniente

def imponer_pdf(ruta_entrada: str, ruta_salida: Optional[str] = None, tamaño_salida: TamañoPagina = TamañoPagina.LETTER) -> ResultadoImposicion:
    imponer = PDFImposer(tamaño_salida=tamaño_salida)
    return imponer.imponer_pdf(ruta_entrada, ruta_salida)
