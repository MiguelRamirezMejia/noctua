// 16 páginas media carta con líneas de referencia a 10 mm del borde izquierdo y superior.
#set page(width: 139.7mm, height: 215.9mm, margin: 0pt)
#for i in range(1, 17) {
  place(top + left, dx: 10mm, line(start: (0pt, 20mm), end: (0pt, 200mm), stroke: 0.2pt))
  place(top + left, dy: 10mm, line(start: (20mm, 0pt), end: (120mm, 0pt), stroke: 0.2pt))
  place(center + horizon, text(size: 90pt, font: "Libertinus Serif", str(i)))
  if i < 16 { pagebreak() }
}
