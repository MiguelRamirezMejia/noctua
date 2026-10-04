// Marcos decorativos alrededor de la página. Todo se dibuja como vector.
//
// marco(m, ancho, alto) devuelve el marco para una página de ancho × alto:
//   m.style      diseño (ver `estilos`)
//   m.color      color #rrggbb
//   m.width_pt   grosor del trazo, ya compensado por la escala de impresión
//   m.inset_mm   distancia al borde de la página, ya compensada
//   m.unit_mm    tamaño de los adornos (ondas, festón, greca…), ya compensado

#let estilos = ("linea", "doble", "clasico", "esquinas", "deco", "puntos", "ondas", "feston", "greca", "estrellas")

// Recorre el rectángulo del marco por sus cuatro lados con coordenadas
// (a lo largo, hacia dentro): así un adorno se diseña una vez, para el lado
// de arriba, y se repite girado en los otros tres.
#let lados(w, h) = (
  (largo: w, punto: (a, b) => (a, b)),
  (largo: h, punto: (a, b) => (w - b, a)),
  (largo: w, punto: (a, b) => (w - a, h - b)),
  (largo: h, punto: (a, b) => (b, h - a)),
)

// Cuántas repeticiones de un adorno de tamaño u caben en un largo, para que
// encajen exactas y las esquinas casen.
#let repeticiones(largo, u) = calc.max(1, int(calc.round(largo / u)))

#let estrella(centro, r, color) = {
  let puntos = range(10).map(i => {
    let ang = -90deg + i * 36deg
    let radio = if calc.even(i) { r } else { r * 0.42 }
    (centro.at(0) + radio * calc.cos(ang), centro.at(1) + radio * calc.sin(ang))
  })
  place(top + left, polygon(fill: color, stroke: none, ..puntos))
}

#let rombo(centro, r, color) = place(top + left, polygon(
  fill: color,
  stroke: none,
  (centro.at(0), centro.at(1) - r),
  (centro.at(0) + r, centro.at(1)),
  (centro.at(0), centro.at(1) + r),
  (centro.at(0) - r, centro.at(1)),
))

#let marco(m, ancho, alto) = {
  let c = rgb(m.color)
  let s = m.width_pt * 1pt
  let inset = m.inset_mm * 1mm
  let u = m.unit_mm * 1mm
  let w = ancho - 2 * inset
  let h = alto - 2 * inset
  let trazo = (paint: c, thickness: s, cap: "round", join: "round")

  let dibujo = if m.style == "linea" {
    rect(width: w, height: h, stroke: trazo)
  } else if m.style == "doble" {
    // Gruesa por fuera, fina por dentro.
    let g = calc.max(u * 0.35, 2.5 * s)
    place(top + left, rect(width: w, height: h, stroke: (..trazo, thickness: s * 1.8)))
    place(top + left, dx: g, dy: g, rect(width: w - 2 * g, height: h - 2 * g, stroke: (..trazo, thickness: s * 0.6)))
  } else if m.style == "clasico" {
    // Doble línea fina con un rombo en cada esquina y en el centro de cada lado.
    let g = calc.max(u * 0.4, 3 * s)
    place(top + left, rect(width: w, height: h, stroke: trazo))
    place(top + left, dx: g, dy: g, rect(width: w - 2 * g, height: h - 2 * g, stroke: (..trazo, thickness: s * 0.6)))
    let r = g * 0.9
    for (x, y) in ((g / 2, g / 2), (w - g / 2, g / 2), (w - g / 2, h - g / 2), (g / 2, h - g / 2), (w / 2, g / 2), (w / 2, h - g / 2), (g / 2, h / 2), (w - g / 2, h / 2)) {
      rombo((x, y), r, c)
    }
  } else if m.style == "esquinas" {
    // Solo las esquinas: escuadras con un remate en espiral.
    let l = calc.min(w, h) * 0.16
    let r = u * 0.28
    // En la esquina de salida de cada lado, «hacia dentro» es justo la
    // dirección del lado anterior: (a, b) y (b, a) son los dos brazos.
    for lado in lados(w, h) {
      let p = lado.punto
      place(top + left, curve(
        stroke: trazo,
        curve.move(p(l, 0pt)),
        curve.line(p(0pt, 0pt)),
        curve.line(p(0pt, l)),
      ))
      // Segunda escuadra, más corta y hacia dentro.
      let d = u * 0.45
      place(top + left, curve(
        stroke: (..trazo, thickness: s * 0.7),
        curve.move(p(l * 0.6, d)),
        curve.line(p(d, d)),
        curve.line(p(d, l * 0.6)),
      ))
      // Remate: un rizo hacia dentro al final de cada brazo.
      place(top + left, curve(
        stroke: trazo,
        curve.move(p(l, 0pt)),
        curve.cubic(p(l + r, 0pt), p(l + r, r * 1.6), p(l, r * 1.6)),
        curve.cubic(p(l - r * 0.6, r * 1.6), p(l - r * 0.6, r * 0.6), p(l, r * 0.7)),
      ))
      place(top + left, curve(
        stroke: trazo,
        curve.move(p(0pt, l)),
        curve.cubic(p(0pt, l + r), p(r * 1.6, l + r), p(r * 1.6, l)),
        curve.cubic(p(r * 1.6, l - r * 0.6), p(r * 0.6, l - r * 0.6), p(r * 0.7, l)),
      ))
      rombo(p(d * 1.9, d * 1.9), r * 0.6, c)
    }
  } else if m.style == "deco" {
    // Art déco: línea exterior fina y una interior con esquinas escalonadas.
    let g = calc.max(u * 0.35, 2.5 * s)
    let e = u * 0.35
    place(top + left, rect(width: w, height: h, stroke: (..trazo, thickness: s * 0.6)))
    let x0 = g
    let y0 = g
    let x1 = w - g
    let y1 = h - g
    place(top + left, polygon(
      stroke: trazo,
      fill: none,
      (x0 + 2 * e, y0), (x1 - 2 * e, y0), (x1 - 2 * e, y0 + e), (x1 - e, y0 + e), (x1 - e, y0 + 2 * e), (x1, y0 + 2 * e),
      (x1, y1 - 2 * e), (x1 - e, y1 - 2 * e), (x1 - e, y1 - e), (x1 - 2 * e, y1 - e), (x1 - 2 * e, y1),
      (x0 + 2 * e, y1), (x0 + 2 * e, y1 - e), (x0 + e, y1 - e), (x0 + e, y1 - 2 * e), (x0, y1 - 2 * e),
      (x0, y0 + 2 * e), (x0 + e, y0 + 2 * e), (x0 + e, y0 + e), (x0 + 2 * e, y0 + e),
    ))
    // Abanicos de líneas en el centro de cada lado.
    for lado in lados(w, h) {
      let p = lado.punto
      let mitad = lado.largo / 2
      for k in (-2, -1, 0, 1, 2) {
        place(top + left, curve(
          stroke: (..trazo, thickness: s * 0.6),
          curve.move(p(mitad + k * e * 0.6, g)),
          curve.line(p(mitad + k * e * 1.4, g + e * 1.6)),
        ))
      }
    }
  } else if m.style == "puntos" {
    let paso = calc.max(u * 0.5, 4 * s)
    let r = calc.max(s, 0.35mm)
    for lado in lados(w, h) {
      let n = repeticiones(lado.largo, paso)
      for i in range(n) {
        let (x, y) = (lado.punto)(i * lado.largo / n, 0pt)
        place(top + left, dx: x - r, dy: y - r, circle(radius: r, fill: c, stroke: none))
      }
    }
  } else if m.style == "ondas" {
    let amp = u * 0.22
    for lado in lados(w, h) {
      let p = lado.punto
      let n = repeticiones(lado.largo, u)
      let paso = lado.largo / n
      let tramos = ()
      for i in range(n) {
        let a = i * paso
        tramos.push(curve.quad(p(a + paso / 4, -amp * 2), p(a + paso / 2, 0pt)))
        tramos.push(curve.quad(p(a + paso * 3 / 4, amp * 2), p(a + paso, 0pt)))
      }
      place(top + left, curve(stroke: trazo, curve.move(p(0pt, 0pt)), ..tramos))
    }
    let g = u * 0.45
    place(top + left, dx: g, dy: g, rect(width: w - 2 * g, height: h - 2 * g, stroke: (..trazo, thickness: s * 0.5)))
  } else if m.style == "feston" {
    // Medios círculos hacia fuera sobre una línea, como un mantel de encaje.
    for lado in lados(w, h) {
      let p = lado.punto
      let n = repeticiones(lado.largo, u * 0.6)
      let paso = lado.largo / n
      let alto-arco = paso / 2 * 1.33
      let tramos = ()
      for i in range(n) {
        let a = i * paso
        tramos.push(curve.cubic(p(a, -alto-arco), p(a + paso, -alto-arco), p(a + paso, 0pt)))
      }
      place(top + left, curve(stroke: trazo, curve.move(p(0pt, 0pt)), ..tramos))
      // Un punto dentro de cada arco.
      for i in range(n) {
        let (x, y) = p((i + 0.5) * paso, -paso * 0.28)
        let r = calc.max(s * 0.8, paso * 0.07)
        place(top + left, dx: x - r, dy: y - r, circle(radius: r, fill: c, stroke: none))
      }
    }
    place(top + left, rect(width: w, height: h, stroke: (..trazo, thickness: s * 0.6)))
  } else if m.style == "greca" {
    // Greca griega (meandro) en una banda; cuadros en las esquinas.
    let b = u * 0.8
    place(top + left, rect(width: w, height: h, stroke: (..trazo, thickness: s * 0.6)))
    place(top + left, dx: b, dy: b, rect(width: w - 2 * b, height: h - 2 * b, stroke: (..trazo, thickness: s * 0.6)))
    let q = b / 5
    for lado in lados(w, h) {
      let p = lado.punto
      let disponible = lado.largo - 2 * b
      let n = repeticiones(disponible, b)
      let paso = disponible / n
      let k = paso / 5
      for i in range(n) {
        let a = b + i * paso
        place(top + left, curve(
          stroke: trazo,
          curve.move(p(a, b - q)),
          curve.line(p(a + k, b - q)),
          curve.line(p(a + k, q)),
          curve.line(p(a + 4 * k, q)),
          curve.line(p(a + 4 * k, 3 * q)),
          curve.line(p(a + 2 * k, 3 * q)),
          curve.line(p(a + 2 * k, 2 * q)),
          curve.line(p(a + 3 * k, 2 * q)),
        ))
        place(top + left, curve(stroke: trazo, curve.move(p(a + k, b - q)), curve.line(p(a + paso, b - q))))
      }
      // Cuadro de esquina con un punto.
      let (x, y) = p(0pt, 0pt)
      let (x2, y2) = p(b, b)
      let cx = (x + x2) / 2
      let cy = (y + y2) / 2
      place(top + left, dx: cx - q * 1.5, dy: cy - q * 1.5, rect(width: 3 * q, height: 3 * q, stroke: trazo))
      place(top + left, dx: cx - q * 0.5, dy: cy - q * 0.5, rect(width: q, height: q, fill: c, stroke: none))
    }
  } else if m.style == "estrellas" {
    // Línea fina con estrellas en las esquinas y estrellitas a lo largo.
    let r = u * 0.45
    place(top + left, rect(width: w, height: h, stroke: (..trazo, thickness: s * 0.6, dash: "dashed")))
    for lado in lados(w, h) {
      let p = lado.punto
      estrella(p(0pt, 0pt), r, c)
      let n = repeticiones(lado.largo, u * 2.2)
      for i in range(1, n) {
        estrella(p(i * lado.largo / n, 0pt), r * 0.4, c)
      }
    }
  }

  place(top + left, dx: inset, dy: inset, block(width: w, height: h, dibujo))
}
