"""
TEST DE DIAGNÓSTICO: Ver qué extrae pdfplumber
"""

import pdfplumber
from pathlib import Path

ruta_pdf = Path("tests/test_document.pdf")

if not ruta_pdf.exists():
    print("El PDF no existe, creando...")
    from reportlab.lib.pagesizes import letter
    from reportlab.pdfgen import canvas
    from reportlab.lib.units import inch
    
    c = canvas.Canvas(str(ruta_pdf), pagesize=letter)
    width, height = letter
    
    margin_left = 0.5 * inch
    margin_top = 0.5 * inch
    y_position = height - margin_top
    
    contenido_prueba = [
        ("Capítulo 1: Introducción", 14, True),
        ("Este es el primer párrafo de introducción.", 10, False),
        ("Este es el segundo párrafo con más contenido.", 10, False),
        ("", 8, False),
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
    print(f"PDF creado: {ruta_pdf}")

# Abrir y extraer con pdfplumber
print(f"\n📖 Abriendo: {ruta_pdf}\n")

with pdfplumber.open(str(ruta_pdf)) as pdf:
    print(f"Páginas: {len(pdf.pages)}")
    
    for i, page in enumerate(pdf.pages):
        print(f"\n--- Página {i+1} ---")
        texto = page.extract_text()
        print(f"Texto extraído:\n{repr(texto)}")
        print(f"Largo: {len(texto) if texto else 0} caracteres")
