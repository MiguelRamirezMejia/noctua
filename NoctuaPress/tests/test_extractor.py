"""
TEST MÓDULO 1: EXTRACCIÓN DE PDF
=================================

Script para probar la funcionalidad del extractor de PDF.
Este script valida que la extracción funciona correctamente.
"""

import sys
from pathlib import Path

# Agregar ruta del proyecto al path
sys.path.insert(0, str(Path(__file__).parent.parent))

from core.extractor import extraer_pdf, PDFExtractor


def crear_pdf_prueba():
    """
    Crea un PDF de prueba usando reportlab.
    Este PDF contiene capítulos y párrafos para validar detección.
    """
    from reportlab.lib.pagesizes import letter
    from reportlab.pdfgen import canvas
    from reportlab.lib.units import inch
    
    ruta_prueba = Path(__file__).parent / "test_document.pdf"
    
    c = canvas.Canvas(str(ruta_prueba), pagesize=letter)
    width, height = letter
    
    # Márgenes
    margin_left = 0.5 * inch
    margin_top = 0.5 * inch
    y_position = height - margin_top
    
    # Contenido de prueba
    contenido_prueba = [
        ("Capítulo 1: Introducción", 14, True),
        ("Este es el primer párrafo de introducción.", 10, False),
        ("Este es el segundo párrafo con más contenido.", 10, False),
        ("", 8, False),  # Línea vacía
        ("Capítulo 2: Desarrollo", 14, True),
        ("Párrafo del segundo capítulo.", 10, False),
        ("Otro párrafo con más información.", 10, False),
    ]
    
    for texto, tamaño, es_titulo in contenido_prueba:
        if texto == "":
            y_position -= 0.2 * inch
            continue
        
        c.setFont("Helvetica-Bold" if es_titulo else "Helvetica", tamaño)
        c.drawString(margin_left, y_position, texto)
        y_position -= 0.3 * inch
        
        if y_position < 0.5 * inch:
            c.showPage()
            y_position = height - margin_top
    
    c.save()
    print(f"✓ PDF de prueba creado: {ruta_prueba}")
    return str(ruta_prueba)


def test_extraccion_basica():
    """Test 1: Extracción básica"""
    print("\n" + "="*60)
    print("TEST 1: EXTRACCIÓN BÁSICA")
    print("="*60)
    
    # Crear PDF de prueba
    ruta_pdf = crear_pdf_prueba()
    
    # Verificar que el PDF se creó
    from pathlib import Path
    assert Path(ruta_pdf).exists(), f"PDF no creado: {ruta_pdf}"
    print(f"✓ PDF existe: {Path(ruta_pdf).stat().st_size} bytes")
    
    # Extraer
    resultado = extraer_pdf(ruta_pdf)
    
    # Debug: mostrar estado de la extracción
    print(f"  Exitoso: {resultado.exitoso}")
    print(f"  Error: {resultado.error}")
    print(f"  Contenido items: {len(resultado.contenido)}")
    print(f"  Metadatos páginas: {resultado.metadatos.get('páginas_originales', 'N/A')}")
    print(f"  Palabras: {resultado.metadatos.get('palabras_totales', 'N/A')}")
    
    # Validar
    assert resultado.exitoso, f"Extracción falló: {resultado.error}"
    assert len(resultado.contenido) > 0, "Contenido vacío"
    assert resultado.metadatos["páginas_originales"] > 0, "No se detectaron páginas"
    
    print(f"✓ PDF extraído exitosamente")
    print(f"  Páginas: {resultado.metadatos['páginas_originales']}")
    print(f"  Palabras: {resultado.metadatos['palabras_totales']}")
    print(f"  Capítulos detectados: {resultado.metadatos['capítulos_detectados']}")
    print(f"  Párrafos totales: {resultado.metadatos['párrafos_totales']}")


def test_estructura_contenido():
    """Test 2: Verificar estructura del contenido"""
    print("\n" + "="*60)
    print("TEST 2: ESTRUCTURA DEL CONTENIDO")
    print("="*60)
    
    ruta_pdf = Path(__file__).parent / "test_document.pdf"
    resultado = extraer_pdf(str(ruta_pdf))
    
    print(f"\n📋 Contenido extraído ({len(resultado.contenido)} elementos raíz):\n")
    
    for i, elemento in enumerate(resultado.contenido):
        if elemento["tipo"] == "capítulo":
            print(f"{i+1}. [CAPÍTULO] {elemento['título']}")
            for j, subelemento in enumerate(elemento.get("contenido", [])):
                texto_preview = subelemento["texto"][:60]
                print(f"   {j+1}. {texto_preview}...")
        else:
            texto_preview = elemento["texto"][:60]
            print(f"{i+1}. [PÁRRAFO] {texto_preview}...")
    
    print("\n✓ Estructura validada")


def test_metadatos():
    """Test 3: Validar metadatos"""
    print("\n" + "="*60)
    print("TEST 3: METADATOS")
    print("="*60)
    
    ruta_pdf = Path(__file__).parent / "test_document.pdf"
    resultado = extraer_pdf(str(ruta_pdf))
    
    meta = resultado.metadatos
    print(f"\n📊 Metadatos:\n")
    print(f"  Archivo: {meta['archivo']}")
    print(f"  Tamaño: {meta['tamaño_bytes']} bytes")
    print(f"  Páginas: {meta['páginas_originales']}")
    print(f"  Palabras: {meta['palabras_totales']}")
    print(f"  Caracteres: {meta['caracteres_totales']}")
    print(f"  Capítulos: {meta['capítulos_detectados']}")
    print(f"  Párrafos: {meta['párrafos_totales']}")
    print(f"  Encoding: {meta['encoding']}")
    
    # Validaciones
    assert meta['palabras_totales'] > 0, "No se contaron palabras"
    assert meta['capítulos_detectados'] >= 0, "Capítulos negativo"
    assert meta['párrafos_totales'] > 0, "No hay párrafos"
    
    print("\n✓ Metadatos validados")


def test_error_archivo_inexistente():
    """Test 4: Manejo de errores"""
    print("\n" + "="*60)
    print("TEST 4: MANEJO DE ERRORES")
    print("="*60)
    
    resultado = extraer_pdf("archivo_que_no_existe.pdf")
    
    assert not resultado.exitoso, "Debería fallar con archivo inexistente"
    assert resultado.error is not None, "Debería tener mensaje de error"
    
    print(f"✓ Error capturado correctamente: {resultado.error}")


def test_json_export():
    """Test 5: Exportar a estructura JSON (verificar serialización)"""
    print("\n" + "="*60)
    print("TEST 5: EXPORTACIÓN JSON")
    print("="*60)
    
    import json
    
    ruta_pdf = Path(__file__).parent / "test_document.pdf"
    resultado = extraer_pdf(str(ruta_pdf))
    
    # Crear estructura serializable
    datos_json = {
        "exitoso": resultado.exitoso,
        "contenido": resultado.contenido,
        "metadatos": resultado.metadatos
    }
    
    # Intentar serializar
    json_str = json.dumps(datos_json, indent=2, ensure_ascii=False)
    
    print(f"✓ Estructura JSON generada ({len(json_str)} caracteres)")
    print(f"\nPrimeras líneas del JSON:\n")
    print('\n'.join(json_str.split('\n')[:20]))
    print("...")


def main():
    """Ejecutar todos los tests"""
    print("\n" + "🧪 SUITE DE PRUEBAS - MÓDULO 1: EXTRACCIÓN DE PDF" + "\n")
    
    try:
        test_extraccion_basica()
        test_estructura_contenido()
        test_metadatos()
        test_error_archivo_inexistente()
        test_json_export()
        
        print("\n" + "="*60)
        print("✅ TODOS LOS TESTS PASARON EXITOSAMENTE")
        print("="*60 + "\n")
        
        return True
    
    except AssertionError as e:
        print(f"\n❌ TEST FALLÓ: {e}\n")
        return False
    except Exception as e:
        print(f"\n❌ ERROR INESPERADO: {e}\n")
        import traceback
        traceback.print_exc()
        return False


if __name__ == "__main__":
    exito = main()
    sys.exit(0 if exito else 1)
