// Vista previa de un marco decorativo (SVG transparente del tamaño de la
// página), para superponerlo en el editor tal como saldrá impreso.
#import "marcos.typ": marco

#let data = json(sys.inputs.data)

#set document(date: none)
#set page(width: data.width_mm * 1mm, height: data.height_mm * 1mm, margin: 0pt, fill: none)

#marco(data.border, data.width_mm * 1mm, data.height_mm * 1mm)
