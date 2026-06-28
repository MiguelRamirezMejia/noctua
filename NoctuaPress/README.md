# 🎙️ NoctuaPress - Aplicación de Formateo de Libros

Herramienta portátil para convertir PDFs de manuscritos en libros formateados con imposición lista para imprimir.

## 📋 Estado del Proyecto

### ✅ Módulo 1: Extracción de PDF (EN DESARROLLO)
- ✓ Extractor de texto desde PDF
- ✓ Detección automática de capítulos
- ✓ Estructuración de párrafos
- ✓ Cálculo de metadatos
- 🔄 **PENDIENTE: Validación del usuario**

---

## 🚀 Instalación Rápida

### Prerrequisitos
- Python 3.11+
- pip

### Setup

```bash
# 1. Clonar/descargar el proyecto
cd NoctuaPress

# 2. Crear entorno virtual
python -m venv venv

# 3. Activar entorno virtual
# Windows:
venv\Scripts\activate
# macOS/Linux:
source venv/bin/activate

# 4. Instalar dependencias
pip install -r requirements.txt
```

---

## 🧪 Testing del Módulo 1

### Ejecutar Suite de Pruebas

```bash
# Desde la raíz del proyecto
python tests/test_extractor.py
```

### Qué Prueba el Script

1. **Extracción Básica**: Crea un PDF de prueba y lo extrae ✓
2. **Estructura del Contenido**: Verifica párrafos y capítulos ✓
3. **Metadatos**: Valida conteos de palabras, páginas, etc. ✓
4. **Manejo de Errores**: Verifica que captura errores correctamente ✓
5. **Exportación JSON**: Valida que la estructura es serializable ✓

### Salida Esperada

```
🧪 SUITE DE PRUEBAS - MÓDULO 1: EXTRACCIÓN DE PDF

============================================================
TEST 1: EXTRACCIÓN BÁSICA
============================================================
✓ PDF extraído exitosamente
  Páginas: 1
  Palabras: 45
  Capítulos detectados: 2
  Párrafos totales: 5

============================================================
TEST 2: ESTRUCTURA DEL CONTENIDO
============================================================

📋 Contenido extraído (2 elementos raíz):

1. [CAPÍTULO] Capítulo 1: Introducción
   1. Este es el primer párrafo de introducción....
   2. Este es el segundo párrafo con más contenido....

...

============================================================
✅ TODOS LOS TESTS PASARON EXITOSAMENTE
============================================================
```

---

## 📁 Estructura del Proyecto

```
NoctuaPress/
├── core/                    # Lógica de negocio (desacoplada)
│   ├── __init__.py
│   ├── extractor.py         # MÓDULO 1: Extracción PDF
│   ├── formatter.py         # MÓDULO 2: Motor de formateo (próx)
│   ├── imposer.py           # MÓDULO 3: Imposición automática (próx)
│   └── docx_exporter.py     # MÓDULO 4: Exportación DOCX (próx)
├── ui/                      # Interfaz PyQt6
│   ├── __init__.py
│   └── main_window.py       # MÓDULO 5: Interfaz (próx)
├── tests/                   # Tests de módulos
│   ├── test_extractor.py    # ✓ Pruebas Módulo 1
│   ├── test_formatter.py    # (próx)
│   └── ...
├── assets/                  # Recursos (iconos, fuentes)
├── requirements.txt         # Dependencias
└── README.md               # Este archivo
```

---

## 🔧 API del Módulo 1: Extractor

### Uso Básico

```python
from core.extractor import extraer_pdf

resultado = extraer_pdf("mi_manuscrito.pdf")

if resultado.exitoso:
    print(f"Palabras: {resultado.metadatos['palabras_totales']}")
    print(f"Capítulos: {resultado.metadatos['capítulos_detectados']}")
    
    # Acceder a contenido
    for elemento in resultado.contenido:
        if elemento["tipo"] == "capítulo":
            print(f"Capítulo: {elemento['título']}")
else:
    print(f"Error: {resultado.error}")
```

### Estructura de Salida

```python
ExtraccionResultado(
    exitoso=True,
    contenido=[
        {
            "tipo": "capítulo",
            "título": "Capítulo 1: Inicio",
            "contenido": [
                {
                    "tipo": "párrafo",
                    "texto": "Contenido del párrafo..."
                },
                ...
            ]
        },
        {
            "tipo": "párrafo",
            "texto": "Párrafo fuera de capítulo..."
        },
        ...
    ],
    metadatos={
        "archivo": "documento.pdf",
        "páginas_originales": 120,
        "palabras_totales": 45000,
        "capítulos_detectados": 5,
        "párrafos_totales": 250,
        "tamaño_bytes": 2048576,
        "encoding": "utf-8"
    }
)
```

### Características

✅ Extrae texto limpio preservando párrafos  
✅ Detecta automáticamente capítulos por patrones  
✅ Retorna estructura jerárquica  
✅ Calcula metadatos útiles  
✅ Manejo robusto de errores  
✅ Normaliza espacios y caracteres de control  

---

## 📝 Notas de Implementación

### Decisiones de Diseño

1. **Extracción de Párrafos**: Por líneas vacías (dobles \n\n)
2. **Detección de Capítulos**: Por regex contra patrones como "Capítulo X", "CHAPTER X"
3. **Limpieza**: Normalización de espacios, remoción de caracteres de control
4. **Estructura**: Retorno como dataclass + dicts para fácil serialización JSON

### Patrones de Capítulos Detectados

- `Capítulo 1`, `CAPÍTULO 1`
- `Chapter 1`, `CHAPTER 1`
- `Part 1`, `PART 1`
- `Parte 1`, `PARTE 1`

---

## ✅ Checklist de Validación

- [ ] Tests ejecutan sin errores
- [ ] Se crea PDF de prueba correctamente
- [ ] Se extraen capítulos detectados
- [ ] Se contabilizan palabras y párrafos
- [ ] Metadatos son precisos
- [ ] Manejo de errores funciona

---

## 🔜 Próximo: Módulo 2 - Motor de Formateo

Una vez confirmado que el Módulo 1 funciona:
- Tomar contenido extraído
- Aplicar parámetros tipográficos (fuente, tamaño, márgenes, etc.)
- Generar PDF formateado con ReportLab

---

**Versión**: 0.1-alpha  
**Última actualización**: Junio 2026  
**Estado**: Módulo 1 en validación
