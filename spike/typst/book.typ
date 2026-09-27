// Compilación completa del libro (PDF final).
#import "lib.typ": *
#let meta = json("/data/meta.json")
#let index = json("/data/book.json")
#show: book.with(meta: meta)
#for f in index.chapters { chapter(json(f)) }
