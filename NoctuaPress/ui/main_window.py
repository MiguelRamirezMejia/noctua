"""Interfaz completa para Noctua Press usando PyQt6."""

from pathlib import Path
from PyQt6 import QtCore, QtWidgets

try:
    from core.extractor import extraer_pdf
    from core.formateador import PDFFormatter
    from core.imposer import PDFImposer
    from core.models import ConfiguracionFormateo, PosicionNumeracion, TamañoPagina
except ModuleNotFoundError:
    from NoctuaPress.core.extractor import extraer_pdf
    from NoctuaPress.core.formateador import PDFFormatter
    from NoctuaPress.core.imposer import PDFImposer
    from NoctuaPress.core.models import ConfiguracionFormateo, PosicionNumeracion, TamañoPagina


class NoctuaPressWindow(QtWidgets.QMainWindow):
    def __init__(self) -> None:
        super().__init__()
        self.setWindowTitle("Noctua Press")
        self.resize(980, 760)
        self.setMinimumSize(900, 650)
        self._centrar_ventana()
        central_widget = QtWidgets.QWidget(self)
        self.setCentralWidget(central_widget)
        layout = QtWidgets.QVBoxLayout(central_widget)

        title = QtWidgets.QLabel("Noctua Press - Procesador de manuscritos")
        title.setStyleSheet("font-size: 18px; font-weight: 600;")
        layout.addWidget(title)
        subtitle = QtWidgets.QLabel("Selecciona un PDF, ajusta la configuración y genera un resultado listo para imprimir.")
        subtitle.setWordWrap(True)
        layout.addWidget(subtitle)

        input_group = QtWidgets.QGroupBox("Archivo de entrada")
        input_layout = QtWidgets.QHBoxLayout(input_group)
        self.input_path_edit = QtWidgets.QLineEdit()
        self.input_path_edit.setPlaceholderText("Ruta del PDF fuente")
        browse_button = QtWidgets.QPushButton("Explorar")
        browse_button.clicked.connect(self._seleccionar_pdf)
        input_layout.addWidget(self.input_path_edit)
        input_layout.addWidget(browse_button)
        layout.addWidget(input_group)

        output_group = QtWidgets.QGroupBox("Archivo de salida")
        output_layout = QtWidgets.QHBoxLayout(output_group)
        self.output_path_edit = QtWidgets.QLineEdit()
        self.output_path_edit.setPlaceholderText("Ruta del PDF resultante")
        output_layout.addWidget(self.output_path_edit)
        layout.addWidget(output_group)

        scroll_area = QtWidgets.QScrollArea()
        scroll_area.setWidgetResizable(True)
        layout.addWidget(scroll_area, stretch=1)
        config_container = QtWidgets.QWidget()
        scroll_area.setWidget(config_container)
        config_layout = QtWidgets.QVBoxLayout(config_container)
        self._build_config_section(config_layout)

        buttons_row = QtWidgets.QHBoxLayout()
        self.process_button = QtWidgets.QPushButton("Procesar PDF")
        self.process_button.clicked.connect(self._procesar_pdf)
        self.process_button.setDefault(True)
        reset_button = QtWidgets.QPushButton("Restaurar valores")
        reset_button.clicked.connect(self._restaurar_valores_por_defecto)
        buttons_row.addStretch()
        buttons_row.addWidget(reset_button)
        buttons_row.addWidget(self.process_button)
        layout.addLayout(buttons_row)

        self.log_edit = QtWidgets.QPlainTextEdit()
        self.log_edit.setReadOnly(True)
        self.log_edit.setPlaceholderText("Aquí aparecerán los mensajes del proceso...")
        layout.addWidget(self.log_edit)

        self.statusBar().showMessage("Listo")
        self._restaurar_valores_por_defecto()

    def _build_config_section(self, parent_layout):
        self.font_name_combo = self._crear_combo_editable([
            "Times-Roman", "Times New Roman", "Helvetica", "Arial", "Courier",
            "EB Garamond", "Lora", "Merriweather", "Libre Baskerville",
            "Playfair Display", "Crimson Text", "Cormorant Garamond",
        ])
        self.google_font_check   = QtWidgets.QCheckBox("Usar fuente de Google Fonts")
        self.font_size_spin      = self._spin_d(8, 72, 11.0, 1)
        self.line_spacing_spin   = self._spin_d(0.8, 3.0, 1.45, 2)
        self.margin_top_spin     = self._spin_d(0.5, 5.0, 2.0, 2)
        self.margin_bottom_spin  = self._spin_d(0.5, 5.0, 2.0, 2)
        self.margin_inner_spin   = self._spin_d(0.5, 5.0, 2.5, 2)
        self.margin_outer_spin   = self._spin_d(0.5, 5.0, 2.0, 2)
        self.paragraph_indent_spin  = self._spin_d(0.0, 2.0, 0.5, 2)
        self.paragraph_spacing_spin = self._spin_d(0.0, 2.0, 0.0, 2)
        self.chapter_spacing_spin   = self._spin_d(0.0, 3.0, 0.6, 2)

        self.page_size_combo = QtWidgets.QComboBox()
        self.page_size_combo.addItem("LETTER", TamañoPagina.LETTER)
        self.page_size_combo.addItem("A4",     TamañoPagina.A4)
        self.page_size_combo.addItem("LEGAL",  TamañoPagina.LEGAL)

        self.numbering_check = QtWidgets.QCheckBox("Numerar páginas")
        self.numbering_check.setChecked(True)

        self.numbering_position_combo = QtWidgets.QComboBox()
        self.numbering_position_combo.addItem("Abajo centro",    PosicionNumeracion.ABAJO_CENTRO)
        self.numbering_position_combo.addItem("Abajo exterior",  PosicionNumeracion.ABAJO_EXTERIOR)
        self.numbering_position_combo.addItem("Abajo izquierda", PosicionNumeracion.ABAJO_IZQUIERDA)
        self.numbering_position_combo.addItem("Abajo derecha",   PosicionNumeracion.ABAJO_DERECHA)
        self.numbering_position_combo.addItem("Arriba centro",   PosicionNumeracion.ARRIBA_CENTRO)
        self.numbering_position_combo.addItem("Arriba derecha",  PosicionNumeracion.ARRIBA_DERECHA)

        self.numbering_format_edit   = QtWidgets.QLineEdit("{numero}")
        self.chapter_title_size_spin = self._spin_d(0, 72, 0, 1)
        self.bold_title_check        = QtWidgets.QCheckBox("Títulos de capítulo en negrita")
        self.bold_title_check.setChecked(True)
        self.new_chapter_page_check  = QtWidgets.QCheckBox("Cada capítulo empieza en nueva página")
        self.new_chapter_page_check.setChecked(True)
        self.bleed_spin   = self._spin_d(0.0, 10.0, 0.0, 2)
        self.cmyk_check   = QtWidgets.QCheckBox("Usar CMYK")
        self.impose_check = QtWidgets.QCheckBox("Aplicar imposición de cuadernillo")
        self.impose_check.setChecked(True)

        self._grupo(parent_layout, "Tipografía", [
            ("Fuente", self.font_name_combo),
            ("", self.google_font_check),
            ("Tamaño de fuente (pt)", self.font_size_spin),
            ("Interlineado", self.line_spacing_spin),
        ])
        self._grupo(parent_layout, "Márgenes y párrafos", [
            ("Margen superior (cm)", self.margin_top_spin),
            ("Margen inferior (cm)", self.margin_bottom_spin),
            ("Margen interior (cm)", self.margin_inner_spin),
            ("Margen exterior (cm)", self.margin_outer_spin),
            ("Sangría de párrafo (cm)", self.paragraph_indent_spin),
            ("Espaciado entre párrafos (cm)", self.paragraph_spacing_spin),
            ("Espaciado post-título (cm)", self.chapter_spacing_spin),
        ])
        self._grupo(parent_layout, "Páginas y numeración", [
            ("Tamaño de página", self.page_size_combo),
            ("", self.numbering_check),
            ("Posición del número", self.numbering_position_combo),
            ("Formato del número", self.numbering_format_edit),
        ])
        self._grupo(parent_layout, "Capítulos e impresión", [
            ("Tamaño título capítulo (0=auto)", self.chapter_title_size_spin),
            ("", self.bold_title_check),
            ("", self.new_chapter_page_check),
            ("Sangrado/bleed (mm)", self.bleed_spin),
            ("", self.cmyk_check),
            ("", self.impose_check),
        ])

    def _grupo(self, parent, title, widgets):
        gb = QtWidgets.QGroupBox(title)
        fl = QtWidgets.QFormLayout(gb)
        for label, widget in widgets:
            fl.addRow(label, widget) if label else fl.addRow(widget)
        parent.addWidget(gb)

    def _crear_combo_editable(self, items):
        combo = QtWidgets.QComboBox()
        combo.setEditable(True)
        combo.addItems(items)
        return combo

    def _spin_d(self, mn, mx, val, dec):
        s = QtWidgets.QDoubleSpinBox()
        s.setRange(mn, mx)
        s.setDecimals(dec)
        s.setValue(val)
        return s

    def _restaurar_valores_por_defecto(self):
        d = ConfiguracionFormateo()
        self.font_name_combo.setEditText(d.fuente_nombre)
        self.google_font_check.setChecked(d.fuente_es_google)
        self.font_size_spin.setValue(d.tamaño_fuente)
        self.line_spacing_spin.setValue(d.interlineado)
        self.margin_top_spin.setValue(d.margen_superior)
        self.margin_bottom_spin.setValue(d.margen_inferior)
        self.margin_inner_spin.setValue(d.margen_interior)
        self.margin_outer_spin.setValue(d.margen_exterior)
        self.paragraph_indent_spin.setValue(d.sangria_parrafo)
        self.paragraph_spacing_spin.setValue(d.espaciado_entre_parrafos)
        self.chapter_spacing_spin.setValue(d.espaciado_post_titulo)
        self.page_size_combo.setCurrentIndex(0)
        self.numbering_check.setChecked(d.numerar_paginas)
        self.numbering_position_combo.setCurrentIndex(0)
        self.numbering_format_edit.setText(d.formato_numero)
        self.chapter_title_size_spin.setValue(d.tamaño_titulo_capitulo or 0)
        self.bold_title_check.setChecked(d.negrilla_titulo)
        self.new_chapter_page_check.setChecked(d.nueva_pagina_capitulo)
        self.bleed_spin.setValue(d.bleed)
        self.cmyk_check.setChecked(d.cmyk)
        self.impose_check.setChecked(True)

    def _build_config(self):
        tam_cap = self.chapter_title_size_spin.value()
        return ConfiguracionFormateo(
            fuente_nombre            = self.font_name_combo.currentText().strip() or "Times-Roman",
            fuente_es_google         = self.google_font_check.isChecked(),
            tamaño_fuente            = self.font_size_spin.value(),
            interlineado             = self.line_spacing_spin.value(),
            margen_superior          = self.margin_top_spin.value(),
            margen_inferior          = self.margin_bottom_spin.value(),
            margen_interior          = self.margin_inner_spin.value(),
            margen_exterior          = self.margin_outer_spin.value(),
            sangria_parrafo          = self.paragraph_indent_spin.value(),
            espaciado_entre_parrafos = self.paragraph_spacing_spin.value(),
            espaciado_post_titulo    = self.chapter_spacing_spin.value(),
            tamaño_pagina            = self.page_size_combo.currentData(),
            numerar_paginas          = self.numbering_check.isChecked(),
            posicion_numero          = self.numbering_position_combo.currentData(),
            formato_numero           = self.numbering_format_edit.text().strip() or "{numero}",
            bleed                    = self.bleed_spin.value(),
            cmyk                     = self.cmyk_check.isChecked(),
            tamaño_titulo_capitulo   = tam_cap if tam_cap > 0 else None,
            negrilla_titulo          = self.bold_title_check.isChecked(),
            nueva_pagina_capitulo    = self.new_chapter_page_check.isChecked(),
        )

    def _seleccionar_pdf(self):
        ruta, _ = QtWidgets.QFileDialog.getOpenFileName(
            self, "Seleccionar PDF", str(Path.home()), "Archivos PDF (*.pdf)"
        )
        if not ruta:
            return
        ruta_path = Path(ruta)
        self.input_path_edit.setText(str(ruta_path))
        self.output_path_edit.setText(str(ruta_path.with_suffix(".formateado.pdf")))
        self._log(f"PDF seleccionado: {ruta_path.name}")

    def _procesar_pdf(self):
        input_text = self.input_path_edit.text().strip()
        if not input_text:
            QtWidgets.QMessageBox.warning(self, "Falta el archivo", "Selecciona un PDF antes de procesar.")
            return
        input_path = Path(input_text)
        if not input_path.exists():
            QtWidgets.QMessageBox.critical(self, "Archivo inexistente", f"No se encontró: {input_path}")
            return

        output_path = Path(self.output_path_edit.text().strip() or str(input_path.with_suffix(".formateado.pdf")))
        output_path.parent.mkdir(parents=True, exist_ok=True)

        self.process_button.setEnabled(False)
        self.statusBar().showMessage("Procesando...")
        QtCore.QCoreApplication.processEvents()

        try:
            self._log(f"Extrayendo contenido de {input_path.name}...")
            res_ext = extraer_pdf(str(input_path))
            if not res_ext.exitoso:
                raise RuntimeError(res_ext.error or "Error en extracción")

            meta = res_ext.metadatos
            self._log(
                f"Extracción completada: "
                f"{meta.get('por_tipo', {}).get('capitulo', 0)} capítulos, "
                f"{meta.get('por_tipo', {}).get('parrafo', 0)} párrafos"
            )

            config = self._build_config()
            self._log(f"Aplicando configuración: fuente {config.fuente_nombre}, {config.tamaño_fuente} pt")

            formatter   = PDFFormatter(config)
            res_fmt     = formatter.formatear(res_ext.bloques)
            if not res_fmt.exitoso:
                raise RuntimeError(res_fmt.error or "Error en formateo")

            self._log(f"PDF formateado: {len(res_fmt.pdf_bytes):,} bytes")

            if self.impose_check.isChecked():
                self._log("Generando imposición de cuadernillo...")
                res_imp = PDFImposer().imponer_bytes(res_fmt.pdf_bytes, str(output_path))
                if not res_imp.exitoso:
                    raise RuntimeError(res_imp.error or "Error en imposición")
                self._log(f"Imposición lista: {res_imp.paginas_fuente} págs → {res_imp.paginas_salida} hojas")
            else:
                output_path.write_bytes(res_fmt.pdf_bytes)
                self._log("PDF guardado (sin imposición)")

            QtWidgets.QMessageBox.information(self, "Proceso completado", f"Archivo guardado en:\n{output_path}")

        except Exception as exc:
            self._log(f"Error inesperado: {exc}")
            QtWidgets.QMessageBox.critical(self, "Error", str(exc))
        finally:
            self.process_button.setEnabled(True)
            self.statusBar().showMessage("Listo")

    def _log(self, mensaje: str):
        self.log_edit.appendPlainText(mensaje)

    def _centrar_ventana(self):
        pantalla = QtWidgets.QApplication.primaryScreen()
        if pantalla is None:
            return
        geometria = pantalla.availableGeometry()
        x = (geometria.width() - self.width()) // 2
        y = (geometria.height() - self.height()) // 2
        self.setGeometry(x, y, self.width(), self.height())


def main():
    app = QtWidgets.QApplication.instance() or QtWidgets.QApplication([])
    app.setApplicationName("Noctua Press")
    app.setStyle("Fusion")
    window = NoctuaPressWindow()
    window.show()
    window.raise_()
    window.activateWindow()
    app.processEvents()
    app.exec()

if __name__ == "__main__":
    main()
