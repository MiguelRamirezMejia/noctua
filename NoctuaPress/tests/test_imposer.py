"""
TEST MÓDULO 3: IMPOSICIÓN DE PDF
================================

Valida la generación de PDF cuadernillo a partir de un PDF fuente.
"""

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent.parent))

from core.imposer import PDFImposer


def crear_pdf_fuente(ruta: Path, paginas: int = 4):
    from reportlab.lib.pagesizes import letter
    from reportlab.pdfgen import canvas
    from reportlab.lib.units import inch

    ruta.parent.mkdir(parents=True, exist_ok=True)
    c = canvas.Canvas(str(ruta), pagesize=letter)

    for i in range(1, paginas + 1):
        c.setFont("Helvetica", 24)
        c.drawString(2 * inch, 7 * inch, f"Página fuente {i}")
        c.drawString(2 * inch, 6.5 * inch, f"Texto de prueba para página {i}")
        c.showPage()

    c.save()
    return str(ruta)


def test_imposicion_4_paginas():
    print("TEST: Imposición de PDF de 4 páginas")
    ruta_fuente = Path("output/test_imposer_source.pdf")
    ruta_salida = Path("output/test_imposer_result.pdf")

    crear_pdf_fuente(ruta_fuente, paginas=4)

    imposer = PDFImposer()
    resultado = imposer.imponer_pdf(str(ruta_fuente), str(ruta_salida))

    assert resultado.exitoso, f"Imposición falló: {resultado.error}"
    assert resultado.paginas_fuente == 4, "El PDF fuente debe tener 4 páginas"
    assert resultado.paginas_salida == 2, "El PDF impuesto debe tener 2 páginas de salida"
    assert ruta_salida.exists(), "El PDF de salida no se creó"
    assert len(resultado.pdf_bytes) > 0, "Bytes de salida vacíos"

    print(f"✓ Imposición exitosa: {resultado.paginas_salida} páginas de salida")


def test_imposicion_pagina_impar():
    print("TEST: Imposición de PDF de 5 páginas con páginas en blanco")
    ruta_fuente = Path("output/test_imposer_source_5.pdf")
    ruta_salida = Path("output/test_imposer_result_5.pdf")

    crear_pdf_fuente(ruta_fuente, paginas=5)

    imposer = PDFImposer()
    resultado = imposer.imponer_pdf(str(ruta_fuente), str(ruta_salida))

    assert resultado.exitoso, f"Imposición falló: {resultado.error}"
    assert resultado.paginas_fuente == 5, "El PDF fuente debe tener 5 páginas"
    assert resultado.paginas_salida == 4, "El PDF impuesto debe tener 4 páginas de salida"
    assert ruta_salida.exists(), "El PDF de salida no se creó"
    assert len(resultado.pdf_bytes) > 0, "Bytes de salida vacíos"

    print(f"✓ Imposición exitosa con página impar: {resultado.paginas_salida} páginas de salida")


def main():
    print("\n🧪 SUITE DE PRUEBAS - MÓDULO 3: IMPOSICIÓN DE PDF\n")
    try:
        test_imposicion_4_paginas()
        test_imposicion_pagina_impar()
        print("\n✅ TODOS LOS TESTS PASARON EXITOSAMENTE\n")
        return True
    except AssertionError as exc:
        print(f"\n❌ TEST FALLÓ: {exc}\n")
        return False
    except Exception as exc:
        print(f"\n❌ ERROR INESPERADO: {exc}\n")
        import traceback
        traceback.print_exc()
        return False


if __name__ == "__main__":
    exito = main()
    sys.exit(0 if exito else 1)
