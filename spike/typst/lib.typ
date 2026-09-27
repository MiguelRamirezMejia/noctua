// Spike Fase 0 — plantilla base Noctua.
// El contenido llega como datos JSON (AST normalizado), nunca como markup generado.

#let settings-default = (
  page-width: 139.7mm,
  page-height: 215.9mm,
  margin-top: 18mm,
  margin-bottom: 22mm,
  margin-inside: 20mm,
  margin-outside: 16mm,
  font: "Libertinus Serif",
  size: 11pt,
  leading: 0.62em,
  indent: 1.2em,
)

#let render-inline(n) = {
  if n.type == "text" {
    let c = [#n.text]
    for m in n.at("marks", default: ()) {
      if m == "bold" { c = strong(c) }
      else if m == "italic" { c = emph(c) }
      else if m == "smallcaps" { c = smallcaps(c) }
    }
    c
  } else if n.type == "footnote" {
    footnote(n.content.map(render-inline).join())
  }
}

// Ancla de sincronización: bloque → página y posición.
#let anchor(id) = context [#metadata((id: id, page: here().page(), y: here().position().y)) <blk>]

#let render-block(b) = {
  if b.type == "paragraph" {
    par[#anchor(b.id)#b.content.map(render-inline).join()]
  } else if b.type == "quote" {
    block(inset: (left: 1.5em, right: 1em), above: 1em, below: 1em)[
      #set text(size: 0.92em)
      #anchor(b.id)#b.blocks.map(render-block).join()
    ]
  } else if b.type == "scene_break" {
    block(above: 1.1em, below: 1.1em, width: 100%, align(center)[\* \* \*])
  }
}

#let chapter(ch) = {
  pagebreak(to: "odd", weak: true)
  counter(footnote).update(0)
  heading(level: 1, ch.title)
  ch.blocks.map(render-block).join()
  [#metadata(none) <chapter-end>]
}

// Página en blanco insertada por pagebreak(to: "odd"): capítulo terminó en p−1 y el siguiente abre en p+1.
#let is-blank(p) = (
  query(<chapter-end>).any(m => m.location().page() == p - 1)
    and query(heading.where(level: 1)).any(h => h.location().page() == p + 1)
)

#let book(meta: (:), settings: (:), body) = {
  let s = settings-default + settings
  set document(title: meta.at("title", default: none), author: meta.at("author", default: ()), date: none)
  set text(font: s.font, size: s.size, lang: "es", region: "CO", hyphenate: true,
    costs: (widow: 100%, orphan: 100%, hyphenation: 100%))
  set par(justify: true, leading: s.leading, spacing: s.leading, first-line-indent: s.indent)

  set page(
    width: s.page-width, height: s.page-height, binding: left,
    margin: (top: s.margin-top, bottom: s.margin-bottom, inside: s.margin-inside, outside: s.margin-outside),
    header: context {
      let p = here().page()
      let opens = query(heading.where(level: 1)).any(h => h.location().page() == p)
      if opens or is-blank(p) { return }
      set text(size: 0.8em)
      if calc.even(counter(page).get().first()) {
        smallcaps(meta.at("title", default: ""))
      } else {
        let prev = query(heading.where(level: 1).before(here()))
        if prev.len() > 0 { align(right, emph(prev.last().body)) }
      }
    },
    footer: context {
      if is-blank(here().page()) { return }
      set text(size: 0.8em)
      align(center, counter(page).display())
    },
  )

  show heading.where(level: 1): it => {
    v(28mm)
    align(center, text(size: 1.5em, weight: "regular", smallcaps(it.body)))
    v(12mm)
  }

  body
}
