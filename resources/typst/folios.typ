// Capa de folios: una página transparente por página del libro, del mismo
// tamaño que el PDF de origen, con el número colocado según la configuración.
#let data = json(sys.inputs.data)
#let m = data.margin_mm * 1mm

#set document(date: none)
#set page(width: data.width_mm * 1mm, height: data.height_mm * 1mm, margin: 0pt, fill: none)
#set text(font: data.font, size: data.size_pt * 1pt, fallback: false, number-type: "lining")

#for p in range(1, data.count + 1) {
  let folio = data.folios.at(str(p), default: none)
  if folio != none {
    let recto = calc.odd(p)
    let side = if recto { right } else { left }
    let dx = if recto { -m } else { m }
    if data.position == "bottom-center" {
      place(bottom + center, dy: -m, folio)
    } else if data.position == "bottom-outer" {
      place(bottom + side, dx: dx, dy: -m, folio)
    } else {
      place(top + side, dx: dx, dy: m, folio)
    }
  }
  if p < data.count { pagebreak() }
}
