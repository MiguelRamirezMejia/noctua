// Compilación aislada de un capítulo (preview incremental).
// typst compile --root .. --input chapter=/data/ch-01.json --input start=1 chapter.typ
#import "lib.typ": *
#let meta = json("/data/meta.json")
#show: book.with(meta: meta)
#counter(page).update(int(sys.inputs.at("start", default: "1")))
#chapter(json(sys.inputs.chapter))
