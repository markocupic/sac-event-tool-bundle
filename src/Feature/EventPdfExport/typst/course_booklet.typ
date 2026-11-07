// Kursheft (Kursprogramm eines Jahres) – SAC Event Tool Bundle
// Titelblatt, Inhaltsverzeichnis (nach Kursart gruppiert) und alle Kurse im Design «Wegweiser».
// Daten aus data.json (WorkshopBookletGenerator / EventPdfGenerator::generateBooklet()).
// Kompilieren: typst compile --root <dir> --font-path fonts --ignore-system-fonts course_booklet.typ

#import "event_layout.typ": *

#let data = json("data.json")

#set document(title: data.title, author: data.sectionName)
#show: base-style

// Titelblatt: Foto oben, rotes Wegweiser-Schild mit dem Titel, darunter die Kursarten und
// unten rechts das Logo (auf dem weissen Seitengrund, wie im Design System verlangt)
#page(margin: 0pt, footer: none, {
  let photo-height = 560pt

  if has(data.cover) {
    place(top + left, image(data.cover, width: 100%, height: photo-height, fit: "cover"))
  } else {
    place(top + left, rect(width: 100%, height: photo-height, fill: see-night))
  }

  // Schild überlappt die Unterkante des Fotos
  place(top + left, dx: margin-x, dy: photo-height - 92pt, signpost(fill: signal, width: 470pt, {
    set text(fill: white)
    set par(spacing: 0pt)
    text(size: 10.5pt, weight: 700, tracking: 0.08em, upper(data.sectionName))
    v(10pt)
    text(..display, weight: 800, size: 46pt, tracking: -0.02em, number-type: "lining", data.title)
    v(14pt)
    text(size: 12.5pt)[#data.courseCount Kurse · Ausgabe vom #data.issuedAt]
  }))

  // Die Kursarten (Zwischentitel des Inhaltsverzeichnisses)
  let groups = data.events.map(e => e.at("tocGroup", default: none)).filter(g => has(g)).dedup()
  if groups.len() > 0 {
    // Rechts bleibt Platz für das Logo
    place(top + left, dx: margin-x, dy: photo-height + 84pt, block(width: 100% - 2 * margin-x - 150pt, {
      text(size: 8.5pt, weight: 700, tracking: 0.08em, fill: signal-strong, upper("Kursarten"))
      v(6pt)
      set par(leading: 0.5em)
      // Jede Kursart bleibt auf einer Zeile, umgebrochen wird nur zwischen den Kursarten
      set par(leading: 0.6em)
      groups.map(g => box(text(..display, weight: 700, size: 15pt, fill: ink, g))).join(text(size: 15pt, fill: signal)[#h(6pt)·#h(6pt)])
    }))
  }

  place(bottom + right, dx: -margin-x, dy: -64pt, image(data.logo, height: 56pt))

  place(bottom + left, dx: margin-x, dy: -28pt, block(width: 100% - 2 * margin-x, {
    set text(size: 9pt, fill: ink-muted)
    line(length: 100%, stroke: 0.75pt + line-color)
    v(4pt)
    grid(columns: (1fr, auto), [#data.sectionName · #data.website], [Ausgabe vom #data.issuedAt])
  }))
})

// Inhaltsverzeichnis: alle Kurse nach Kursart, mit Datum und Seitenzahl (anklickbar)
#page(
  margin: (left: margin-x, right: margin-x, top: 40pt, bottom: 54pt),
  footer: context {
    set text(size: 8.5pt, fill: ink-muted)
    line(length: 100%, stroke: 0.75pt + line-color)
    v(-2pt)
    grid(
      columns: (1fr, auto),
      [#data.sectionName · #data.website],
      [Seite #counter(page).display() / #counter(page).final().first()],
    )
  },
  {
    text(size: 9.5pt, weight: 700, tracking: 0.08em, fill: signal-strong, upper(data.title))
    v(4pt)
    text(..display, weight: 800, size: 34pt, tracking: -0.02em)[Inhalt]
    v(18pt)

    context {
      let events = query(<event>)
      let group = none

      for e in events {
        let m = e.value
        let loc = e.location()

        // Neue Kursart: Zwischentitel
        if m.group != group {
          group = m.group
          block(above: 18pt, below: 8pt, sticky: true, {
            text(..display, weight: 700, size: 13.5pt, fill: signal, if has(group) { group } else { "Weitere Kurse" })
            v(-6pt)
            line(length: 100%, stroke: 0.75pt + line-color)
          })
        }

        // Mehrere einzelne Kurstage: nur der erste, dann «und weitere»
        let period = if m.period.len() == 0 {
          ""
        } else if m.period.len() == 2 and m.period.first().ends-with("–") {
          m.period.join(" ")
        } else if m.period.len() > 1 {
          m.period.first() + " und weitere"
        } else {
          m.period.first()
        }

        link(loc, block(below: 8pt, breakable: false, grid(
          columns: (128pt, 1fr, 22pt),
          column-gutter: 10pt,
          align: (left + bottom, left + bottom, right + bottom),
          text(..display, weight: 600, size: 9.5pt, fill: ink-muted, number-type: "lining", period),
          {
            text(weight: 600, m.title)
            box(width: 1fr, repeat(text(fill: ink-muted.lighten(40%))[.#h(2.5pt)]))
          },
          text(..display, weight: 700, size: 10pt, number-type: "lining", str(loc.page())),
        )))
      }
    }
  },
)

// Die Kurse, jeder beginnt auf einer neuen Seite
#for e in data.events {
  event-page(e)
}
