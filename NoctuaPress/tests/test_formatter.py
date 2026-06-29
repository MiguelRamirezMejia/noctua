"""
TEST MÓDULO 2: MOTOR DE FORMATEO
=================================

Script para probar la generación de PDF formateado.
Valida aplicación de estilos, márgenes, fuentes y paginación.
"""

import sys
from pathlib import Path
import json

# Agregar ruta del proyecto al path
sys.path.insert(0, str(Path(__file__).parent.parent))

from core.extractor import extraer_pdf
from NoctuaPress.core.formateador import formatear_pdf, PDFFormatter
from core.models import ConfiguracionFormateo, TamañoPagina, PosicionNumeracion


def crear_contenido_prueba():
    """Crea contenido estructurado para pruebas"""
    return [
        {
            "tipo": "capítulo",
            "título": "Capítulo 1: Introducción",
            "contenido": [
                {
                    "tipo": "párrafo",
                    "texto": "Este es el primer párrafo de introducción del documento. Aquí comienza la historia de nuestro manuscrito con una presentación clara y directa."
                },
                {
                    "tipo": "párrafo",
                    "texto": "Este es el segundo párrafo que continúa la introducción. Proporciona contexto adicional sobre el tema que se va a tratar en el resto del documento."
                },
                {
                    "tipo": "párrafo",
                    "texto": "Un tercer párrafo para completar la introducción. Aquí podemos añadir más detalles y establecer el tono general del documento."
                }
            ]
        },
        {
            "tipo": "capítulo",
            "título": "Capítulo 2: Desarrollo",
            "contenido": [
                {
                    "tipo": "párrafo",
                    "texto": "Párrafo del segundo capítulo. Este párrafo introduce el tema principal del capítulo de desarrollo."
                },
                {
                    "tipo": "párrafo",
                    "texto": "Otro párrafo con más información. Continuamos ampliando el contenido del segundo capítulo."
                },
                {
                    "tipo": "párrafo",
                    "texto": "Un párrafo más para el capítulo. Este proporciona información adicional sobre el desarrollo del tema."
                }
            ]
        }
    ]


def test_formateo_basico():
    """Test 1: Formateo básico con parámetros por defecto"""
    print("\n" + "="*60)
    print("TEST 1: FORMATEO BÁSICO (Parámetros por defecto)")
    print("="*60)
    
    contenido = crear_contenido_prueba()
    config = ConfiguracionFormateo()
    
    resultado = formatear_pdf(contenido, config, "output/test1_basico.pdf")
    
    assert resultado.exitoso, f"Formateo falló: {resultado.error}"
    assert resultado.pdf_bytes is not None, "PDF bytes vacío"
    assert len(resultado.pdf_bytes) > 0, "PDF generado está vacío"
    
    print(f"✓ PDF generado exitosamente")
    print(f"  Tamaño: {len(resultado.pdf_bytes)} bytes")
    print(f"  Fuente: {config.fuente_nombre}")
    print(f"  Tamaño fuente: {config.tamaño_fuente} pt")
    print(f"  Interlineado: {config.interlineado}x")
    print(f"  Márgenes: {config.margen_superior}cm (sup), {config.margen_inferior}cm (inf)")


def test_formateo_custom_margenes():
    """Test 2: Formateo con márgenes personalizados"""
    print("\n" + "="*60)
    print("TEST 2: MÁRGENES PERSONALIZADOS")
    print("="*60)
    
    contenido = crear_contenido_prueba()
    config = ConfiguracionFormateo(
        margen_superior=1.5,
        margen_inferior=1.5,
        margen_interior=2.5,
        margen_exterior=2.0,
    )
    
    resultado = formatear_pdf(contenido, config, "output/test2_margenes.pdf")
    
    assert resultado.exitoso, f"Formateo falló: {resultado.error}"
    
    print(f"✓ PDF con márgenes personalizados")
    print(f"  Superior: {config.margen_superior} cm")
    print(f"  Inferior: {config.margen_inferior} cm")
    print(f"  Interior (lomo): {config.margen_interior} cm")
    print(f"  Exterior: {config.margen_exterior} cm")


def test_formateo_tipografia():
    """Test 3: Formateo con diferentes parámetros tipográficos"""
    print("\n" + "="*60)
    print("TEST 3: PARÁMETROS TIPOGRÁFICOS")
    print("="*60)
    
    contenido = crear_contenido_prueba()
    config = ConfiguracionFormateo(
        fuente_nombre="Times New Roman",
        tamaño_fuente=14,
        interlineado=1.8,
        sangria_parrafo=0.75,
        espaciado_entre_parrafos=0.4,
    )
    
    resultado = formatear_pdf(contenido, config, "output/test3_tipografia.pdf")
    
    assert resultado.exitoso, f"Formateo falló: {resultado.error}"
    
    print(f"✓ PDF con tipografía personalizada")
    print(f"  Fuente: {config.fuente_nombre}")
    print(f"  Tamaño: {config.tamaño_fuente} pt")
    print(f"  Interlineado: {config.interlineado}x")
    print(f"  Sangría párrafo: {config.sangria_parrafo} cm")
    print(f"  Espaciado párrafos: {config.espaciado_entre_parrafos} cm")


def test_formateo_sin_numeracion():
    """Test 4: Formateo sin numeración de páginas"""
    print("\n" + "="*60)
    print("TEST 4: SIN NUMERACIÓN DE PÁGINAS")
    print("="*60)
    
    contenido = crear_contenido_prueba()
    config = ConfiguracionFormateo(
        numerar_paginas=False
    )
    
    resultado = formatear_pdf(contenido, config, "output/test4_sin_numeracion.pdf")
    
    assert resultado.exitoso, f"Formateo falló: {resultado.error}"
    assert resultado.metadatos["numerar_paginas"] == False
    
    print(f"✓ PDF generado sin numeración de páginas")


def test_formateo_con_numeracion():
    """Test 5: Formateo con numeración en diferentes posiciones"""
    print("\n" + "="*60)
    print("TEST 5: NUMERACIÓN EN DIFERENTES POSICIONES")
    print("="*60)
    
    contenido = crear_contenido_prueba()
    
    posiciones = [
        PosicionNumeracion.ABAJO_CENTRO,
        PosicionNumeracion.ABAJO_EXTERIOR,
        PosicionNumeracion.ARRIBA_DERECHA,
    ]
    
    for i, posicion in enumerate(posiciones):
        config = ConfiguracionFormateo(
            numerar_paginas=True,
            posicion_numero=posicion,
            formato_numero=f"[{{numero}}]"
        )
        
        resultado = formatear_pdf(
            contenido, config, 
            f"output/test5_numero_{i+1}_{posicion.value}.pdf"
        )
        
        assert resultado.exitoso, f"Formateo falló: {resultado.error}"
        print(f"  ✓ Posición: {posicion.value}")


def test_formateo_tamaño_pagina():
    """Test 6: Diferentes tamaños de página"""
    print("\n" + "="*60)
    print("TEST 6: TAMAÑOS DE PÁGINA")
    print("="*60)
    
    contenido = crear_contenido_prueba()
    
    tamaños = [TamañoPagina.LETTER, TamañoPagina.A4]
    
    for i, tamaño in enumerate(tamaños):
        config = ConfiguracionFormateo(tamaño_pagina=tamaño)
        
        resultado = formatear_pdf(
            contenido, config,
            f"output/test6_pagina_{i+1}_{tamaño.value}.pdf"
        )
        
        assert resultado.exitoso, f"Formateo falló: {resultado.error}"
        print(f"  ✓ Tamaño: {tamaño.value}")


def test_formateo_validaciones():
    """Test 7: Validaciones de parámetros"""
    print("\n" + "="*60)
    print("TEST 7: VALIDACIONES")
    print("="*60)
    
    # Probar tamaño de fuente inválido
    try:
        config = ConfiguracionFormateo(tamaño_fuente=100)  # > 72 = error
        assert False, "Debería haber lanzado excepción"
    except ValueError as e:
        print(f"  ✓ Validación tamaño fuente: {str(e)[:50]}...")
    
    # Probar interlineado inválido
    try:
        config = ConfiguracionFormateo(interlineado=5.0)  # > 3.0 = error
        assert False, "Debería haber lanzado excepción"
    except ValueError as e:
        print(f"  ✓ Validación interlineado: {str(e)[:50]}...")
    
    # Contenido vacío
    config = ConfiguracionFormateo()
    resultado = formatear_pdf([], config)
    assert not resultado.exitoso, "Debería fallar con contenido vacío"
    print(f"  ✓ Validación contenido vacío capturada")


def test_metadatos():
    """Test 8: Metadatos del PDF generado"""
    print("\n" + "="*60)
    print("TEST 8: METADATOS")
    print("="*60)
    
    contenido = crear_contenido_prueba()
    config = ConfiguracionFormateo(
        fuente_nombre="Times New Roman",
        tamaño_fuente=12,
        tamaño_pagina=TamañoPagina.LETTER
    )
    
    resultado = formatear_pdf(contenido, config, "output/test8_metadatos.pdf")
    
    assert resultado.exitoso
    meta = resultado.metadatos
    
    print(f"  Tamaño PDF: {meta['tamaño_bytes']} bytes")
    print(f"  Fuente: {meta['fuente']}")
    print(f"  Tamaño fuente: {meta['tamaño_fuente']} pt")
    print(f"  Tamaño página: {meta['tamaño_pagina']}")
    print(f"  Numeración: {meta['numerar_paginas']}")
    
    assert meta['tamaño_bytes'] > 0
    assert meta['fuente'] == config.fuente_nombre


def test_integración_extractor_formatter():
    """Test 9: Integración Módulo 1 + Módulo 2"""
    print("\n" + "="*60)
    print("TEST 9: INTEGRACIÓN EXTRACTOR + FORMATTER")
    print("="*60)
    
    # Usar PDF de prueba del Módulo 1
    ruta_pdf_prueba = Path(__file__).parent / "test_document.pdf"
    
    if not ruta_pdf_prueba.exists():
        print("  ⚠ PDF de prueba no encontrado, saltando test de integración")
        return
    
    # Paso 1: Extraer con Módulo 1
    resultado_extraccion = extraer_pdf(str(ruta_pdf_prueba))
    
    assert resultado_extraccion.exitoso, "Extracción falló"
    contenido = resultado_extraccion.bloques
    
    # Paso 2: Formatear con Módulo 2
    config = ConfiguracionFormateo(
        tamaño_fuente=11,
        interlineado=1.6,
        margen_superior=2.0,
        margen_inferior=2.0,
    )
    
    resultado_formateo = formatear_pdf(
        contenido, config,
        "output/test9_integracion.pdf"
    )
    
    assert resultado_formateo.exitoso, f"Formateo falló: {resultado_formateo.error}"
    
    print(f"  ✓ PDF extraído de: {ruta_pdf_prueba.name}")
    print(f"  ✓ Contenido: {resultado_extraccion.metadatos['palabras_totales']} palabras")
    print(f"  ✓ PDF formateado: {len(resultado_formateo.pdf_bytes)} bytes")
    print(f"  ✓ Flujo completo exitoso")


def main():
    """Ejecutar todos los tests"""
    print("\n" + "🧪 SUITE DE PRUEBAS - MÓDULO 2: MOTOR DE FORMATEO" + "\n")
    
    # Crear carpeta de output
    Path("output").mkdir(exist_ok=True)
    
    try:
        test_formateo_basico()
        test_formateo_custom_margenes()
        test_formateo_tipografia()
        test_formateo_sin_numeracion()
        test_formateo_con_numeracion()
        test_formateo_tamaño_pagina()
        test_formateo_validaciones()
        test_metadatos()
        test_integración_extractor_formatter()
        
        print("\n" + "="*60)
        print("✅ TODOS LOS TESTS PASARON EXITOSAMENTE")
        print("="*60)
        print(f"\n📄 PDFs generados en: output/")
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
