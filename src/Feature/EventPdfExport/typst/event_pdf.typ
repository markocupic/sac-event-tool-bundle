// Tour- und Kurs-PDF (Design «Wegweiser») – SAC Event Tool Bundle
// Ein Event, Daten aus data.json (EventPdfDataProvider, ergänzt von EventPdfGenerator).
// Das Layout steht in event_layout.typ.
// Kompilieren: typst compile --root <dir> --font-path fonts --ignore-system-fonts event_pdf.typ

#import "event_layout.typ": base-style, event-page

#let data = json("data.json")

#set document(title: data.title, author: data.sectionName)
#show: base-style

#event-page(data)
