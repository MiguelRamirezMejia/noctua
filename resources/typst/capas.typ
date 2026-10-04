// Capas que Noctua añade a cada página del libro, del mismo tamaño que la
// página de origen. Se compila dos veces:
//   capa = "debajo": fondos de color (van detrás de la página original).
//   capa = "encima": cuadros de texto, rectángulos y folios.
#let data = json(sys.inputs.data)
#let capa = sys.inputs.capa

#set document(date: none)
#set page(width: data.width_mm * 1mm, height: data.height_mm * 1mm, margin: 0pt, fill: none)
#set text(lang: "es", fallback: true)

#let halign(a) = if a == "center" { center } else if a == "right" { right } else { left }
#let valign(a) = if a == "middle" { horizon } else if a == "bottom" { bottom } else { top }

#let spans(el) = {
  for s in el.spans {
    let style = (
      weight: if s.bold { "bold" } else { "regular" },
      style: if s.italic { "italic" } else { "normal" },
      fill: rgb(if s.color != none { s.color } else { el.color }),
    )
    // Los saltos de línea del editor son saltos de línea dentro del párrafo.
    let partes = s.text.split("\n")
    for (i, parte) in partes.enumerate() {
      if i > 0 { linebreak() }
      if parte != "" { text(..style, parte) }
    }
  }
}

#let texto(el) = place(top + left, dx: el.x * 1mm, dy: el.y * 1mm, block(
  width: el.width * 1mm,
  height: el.height * 1mm,
  fill: if el.fill != none { rgb(el.fill) } else { none },
  clip: false,
  align(halign(el.align) + valign(el.valign), {
    set text(font: el.font, size: el.size_pt * 1pt, top-edge: "ascender", bottom-edge: "descender")
    // Interlineado como en CSS: distancia entre líneas = line_height × cuerpo.
    set par(leading: calc.max(0pt, (el.line_height - 1.15) * el.size_pt * 1pt), justify: el.align == "justify", first-line-indent: 0pt, spacing: 0pt)
    spans(el)
  }),
))

#let rectangulo(el) = place(top + left, dx: el.x * 1mm, dy: el.y * 1mm, rect(
  width: el.width * 1mm,
  height: el.height * 1mm,
  stroke: none,
  fill: rgb(el.fill).transparentize((1 - el.opacity) * 100%),
))

#let folio(p, texto-folio) = {
  let f = data.folios
  let m = f.margin_mm * 1mm
  let recto = calc.odd(p)
  let lado = if recto { right } else { left }
  let dx = if recto { -m } else { m }
  set text(font: f.font, size: f.size_pt * 1pt, number-type: "lining")
  if f.position == "bottom-center" {
    place(bottom + center, dy: -m, texto-folio)
  } else if f.position == "bottom-outer" {
    place(bottom + lado, dx: dx, dy: -m, texto-folio)
  } else {
    place(top + lado, dx: dx, dy: m, texto-folio)
  }
}

#for (i, pagina) in data.pages.enumerate() {
  let p = i + 1
  if capa == "debajo" {
    if pagina.background != none and pagina.background.mode == "behind" {
      place(top + left, rect(width: 100%, height: 100%, stroke: none, fill: rgb(pagina.background.color)))
    }
  } else {
    // Los elementos acompañan al contenido de la página: se escalan y colocan
    // dentro de los márgenes del libro, igual que la página original.
    let f = pagina.frame
    place(top + left, dx: f.x_mm * 1mm, dy: f.y_mm * 1mm, scale(
      x: f.scale * 100%,
      y: f.scale * 100%,
      origin: top + left,
      reflow: false,
      block(width: data.width_mm * 1mm, height: data.height_mm * 1mm, {
        for el in pagina.elements {
          if el.type == "text" { texto(el) } else if el.type == "rect" { rectangulo(el) }
        }
      }),
    ))
    // El folio va en la página completa, en el margen.
    if pagina.folio != none { folio(p, pagina.folio) }
  }
  if p < data.pages.len() { pagebreak() }
}
