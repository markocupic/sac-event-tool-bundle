// Seite einer Tour, einer allgemeinen Veranstaltung oder eines Kurses (Design «Wegweiser»)
// SAC Event Tool Bundle – genutzt von event_pdf.typ (ein Event) und course_booklet.typ (Kursheft).
// Die Daten eines Events kommen von EventPdfDataProvider (ergänzt von EventPdfGenerator).
// Texte werden als Strings eingesetzt, nie als Markup: Zeichen wie # * _ $ bleiben, was sie sind.

// Farben aus dem Design System «SAC Pilatus»
#let see = rgb("#026ca0")
#let see-strong = rgb("#01557e")
#let see-soft = rgb("#e1f0f8")
#let see-night = rgb("#0b2a3f")
#let ink = rgb("#13202c")
#let ink-muted = rgb("#52606e")
#let line-color = rgb("#d9e1e8")
#let wait = rgb("#a34d00")
#let wait-soft = rgb("#fdf0e0")
#let signal = rgb("#c8102e")
#let signal-soft = rgb("#fbe6e9")
#let signal-strong = rgb("#9e0c24")

#let display = (font: "Archivo", stretch: 87.5%)
#let body-font = "Source Sans 3"
#let margin-x = 30pt

// Grundeinstellungen für Text, für alle Dokumente
#let base-style(doc) = {
  set text(font: body-font, size: 10pt, fill: ink, lang: "de", region: "ch")
  set par(leading: 0.55em, justify: false)
  doc
}

// Mehrzeiliger Text aus der Datenbank: Zeilenumbrüche bleiben erhalten
#let lines(s) = s.split("\n").map(l => [#l]).join(linebreak())

#let has(v) = v != none and v != "" and v != ()

#let value(body, size: 12.5pt) = text(..display, weight: 700, size: size, number-type: "lining", number-width: "tabular", body)

#let number(n, color) = text(..display, weight: 800, size: 10pt, fill: color, number-type: "lining", number-width: "tabular",
  if n < 10 { "0" + str(n) } else { str(n) })

// Ein nummerierter Abschnitt wird nicht von einem Seitenumbruch getrennt: passt er nicht mehr
// auf die Seite, beginnt er auf der nächsten. Nur Abschnitte, die höher als `max-unbroken`
// sind (z. B. ein sehr langer Routenbeschrieb), dürfen umbrechen.
#let max-unbroken = 500pt

#let keep(below: 0pt, ..args) = {
  layout(size => {
    let height = measure(block(..args), width: size.width).height
    block(..args, breakable: height > max-unbroken)
  })
  v(below)
}

// Linien-Icon (24er-Raster wie die Icons des Design Systems) in der Akzentfarbe
#let line-icon(paths, color) = image(bytes(
  "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='" + color.to-hex() + "' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'>" + paths + "</svg>"
), format: "svg", width: 11pt)

#let download-paths = "<path d='M12 3v12'/><path d='M7 10l5 5 5-5'/><path d='M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'/>"
#let link-paths = "<path d='M14 4h6v6'/><path d='M20 4l-9 9'/><path d='M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4'/>"

// Rundes Portrait, ohne Foto die Initialen auf Nachtblau
#let portrait(i, size: 36pt) = if has(i.photo) {
  box(width: size, height: size, radius: 50%, clip: true,
    image(i.photo, width: size, height: size, fit: "cover"))
} else {
  box(width: size, height: size, radius: 50%, fill: see-night,
    align(center + horizon, text(..display, weight: 700, size: 11pt, fill: white, i.initials)))
}

// Wegweiser-Schild: läuft bis an den linken Seitenrand, Spitze rechts. Darunter ein nachtblauer
// Balken mit kleinem Abstand, sein rechtes Ende verlängert die untere Kante der Spitze.
// `body` ist der Inhalt des Schildes (weisse Schrift), `width` die Breite ab dem Seitenrand.
#let signpost(body, fill: see, width: auto) = context {
  let tip = 30pt
  let width = if width == auto { page.width - 2 * margin-x + margin-x - 26pt } else { width }
  let inner = block(width: width - tip - 24pt, body)
  let h = measure(inner).height + 44pt
  let bar-gap = 6pt
  let bar-height = 7.5pt
  let slope = tip / (h / 2)
  let bar-x(y) = width - tip - slope * y
  move(dx: -margin-x, block(width: width, height: h + bar-gap + bar-height, {
    place(top + left, polygon(
      fill: fill,
      (0pt, 0pt), (width - tip, 0pt), (width, h / 2), (width - tip, h), (0pt, h),
    ))
    place(top + left, dy: h + bar-gap, polygon(
      fill: see-night,
      (0pt, 0pt), (bar-x(bar-gap), 0pt), (bar-x(bar-gap + bar-height), bar-height), (0pt, bar-height),
    ))
    place(top + left, dx: margin-x, dy: 22pt, inner)
  }))
}

// Eine Seite (oder mehrere) für ein Event
#let event-page(data) = {
  // Akzentfarbe: Touren blau (see), Kurse rot (signal), wie im Design System
  let is-course = data.variant == "course"
  let accent = if is-course { signal } else { see }
  let accent-strong = if is-course { signal-strong } else { see-strong }
  let accent-soft = if is-course { signal-soft } else { see-soft }

  let label(s) = text(size: 8.5pt, weight: 700, tracking: 0.08em, fill: accent-strong, upper(s))

  let section-title(n, title, color: ink, number-color: accent) = block(below: 8pt, sticky: true, {
    number(n, number-color)
    h(7pt)
    text(..display, weight: 700, size: 13.5pt, fill: color, title)
  })

  // Profil als Tabelle: pro Tag eine Zeile mit Aufstieg (↑) und Abstieg (↓)
  let profile-table(rows) = {
    let has-days = rows.any(r => has(r.day))
    let cell(arrow, meters, time) = if has(meters) or has(time) {
      text(..display, weight: 600, size: 9.5pt, fill: accent, baseline: -1pt, arrow)
      h(3pt)
      if has(meters) { value(size: 11.5pt, meters) }
      if has(time) { h(5pt); text(size: 9pt, fill: ink-muted, time) }
    }
    grid(
      columns: if has-days { (auto, auto, auto) } else { (auto, auto) },
      column-gutter: 18pt,
      row-gutter: 7pt,
      align: horizon,
      ..rows.map(r => {
        let cells = (
          cell("↑", r.ascent, r.ascentTime),
          cell("↓", r.descent, r.descentTime),
        )
        if has-days { (text(..display, weight: 700, size: 10pt, fill: ink-muted, r.day),) + cells } else { cells }
      }).flatten(),
    )
  }

  // Ein Link oder Download: QR-Code, Icon und Titel, alles anklickbar
  let link-item(item, icon, info: none) = grid(
    columns: (44pt, 1fr),
    column-gutter: 10pt,
    align: horizon,
    link(item.url, image(item.qr, width: 44pt)),
    link(item.url, grid(
      columns: (11pt, 1fr),
      column-gutter: 6pt,
      move(dy: 1pt, icon),
      {
        set par(leading: 0.5em)
        text(weight: 700, fill: accent, item.title)
        if has(info) [ #text(size: 9pt, fill: ink-muted)[(#info)]]
      },
    )),
  )

  // Seitenleiste mit den Eckdaten
  let sidebar = block(width: 100%, fill: accent-soft, radius: 12pt, inset: (x: 15pt, y: 18pt), breakable: true, {
    set par(leading: 0.45em, spacing: 0.6em)
    let item(title, body) = block(below: 17pt, {
      label(title)
      v(5pt)
      body
    })

    if data.period.len() > 0 { item(data.dateLabel, value(data.period.join(linebreak()))) }
    if has(data.courseType) { item("Kursart", text(weight: 600, data.courseType)) }
    if has(data.courseLevel) { item("Kursstufe", data.courseLevel) }

    if data.difficulties.len() > 0 or data.suitableForBeginners {
      item("Schwierigkeit", {
        stack(spacing: 5pt, ..data.difficulties.map(d => {
          box(fill: accent, radius: 4pt, inset: (x: 5pt, y: 2.5pt),
            text(..display, weight: 700, size: 9.5pt, fill: white, d.label))
          if has(d.title) { h(5pt); text(size: 10pt, d.title) }
        }))
        if data.suitableForBeginners {
          v(4pt)
          text(size: 9.5pt, fill: accent-strong, weight: 600)[Für Anfänger geeignet]
        }
      })
    }

    if has(data.ascent) or has(data.descent) or has(data.ascentTime) or has(data.descentTime) {
      item("Profil gesamt", grid(columns: (1fr, 1fr), column-gutter: 6pt, row-gutter: 10pt,
        if has(data.ascent) [#value(size: 15pt)[↑ #data.ascent] \ #v(3pt) #text(size: 8.5pt, fill: ink-muted)[Hm Aufstieg]],
        if has(data.descent) [#value(size: 15pt)[↓ #data.descent] \ #v(3pt) #text(size: 8.5pt, fill: ink-muted)[Hm Abstieg]],
        if has(data.ascentTime) [#value(size: 15pt)[#data.ascentTime] \ #v(3pt) #text(size: 8.5pt, fill: ink-muted)[h Aufstieg]],
        if has(data.descentTime) [#value(size: 15pt)[#data.descentTime] \ #v(3pt) #text(size: 8.5pt, fill: ink-muted)[h Abstieg]],
      ))
    }

    if has(data.location) { item("Ausgangspunkt", lines(data.location)) }
    if has(data.meetingPoint) { item("Zeit und Treffpunkt", lines(data.meetingPoint)) }
    if has(data.journey) { item("Anreise mit", lines(data.journey)) }
    if data.organizers.len() > 0 { item("Organisierende Gruppe", data.organizers.join(linebreak())) }
  })

  // Hauptspalte: nummerierte Abschnitte
  let main = {
    set par(leading: 0.65em, spacing: 1em)
    let n = 0

    for s in data.sections {
      n += 1
      keep(below: 22pt, {
        section-title(n, s.title)
        if s.at("profile", default: ()).len() > 0 {
          profile-table(s.profile)
        } else {
          lines(s.text)
        }
      })
    }

    if data.instructors.len() > 0 {
      n += 1
      keep(below: 22pt, {
        section-title(n, data.instructorsTitle)
        grid(columns: (1fr, 1fr), column-gutter: 14pt, row-gutter: 14pt, ..data.instructors.map(i => grid(
          columns: (36pt, 1fr),
          column-gutter: 9pt,
          portrait(i),
          {
            set par(leading: 0.55em)
            text(weight: 700, i.name)
            if has(i.qualification) [\ #text(size: 9pt, fill: ink-muted, i.qualification)]
            for c in i.contact [\ #c]
          },
        )))
        if has(data.contactHint) {
          v(8pt)
          text(size: 9pt, fill: ink-muted, data.contactHint)
        }
      })
    }

    if data.links.len() > 0 {
      n += 1
      keep(below: 22pt, {
        section-title(n, "Links")
        stack(spacing: 12pt, ..data.links.map(l => link-item(l, line-icon(link-paths, accent))))
      })
    }

    if has(data.downloads) {
      n += 1
      keep(below: 22pt, {
        section-title(n, "Downloads / Weitere Informationen")
        if has(data.downloads.hint) {
          text(size: 9pt, fill: ink-muted, data.downloads.hint)
        }
        stack(spacing: 12pt, ..data.downloads.items.map(d => link-item(d, line-icon(download-paths, accent), info: d.info)))
      })
    }

    if has(data.registration) and data.registration.lines.len() > 0 {
      n += 1
      keep(width: 100%, fill: wait-soft, radius: 8pt, inset: (x: 14pt, y: 13pt), {
        section-title(n, data.registration.title, color: wait, number-color: wait)
        grid(
          columns: if has(data.qr) { (1fr, 62pt) } else { (1fr,) },
          column-gutter: 12pt,
          data.registration.lines.map(l => lines(l)).join(parbreak()),
          if has(data.qr) {
            block(fill: white, radius: 6pt, inset: 5pt, image(data.qr, width: 52pt))
            v(2pt)
            align(center, text(size: 8pt, fill: wait, data.onlineLabel))
          },
        )
      })
    }
  }

  set page(
    paper: "a4",
    margin: (left: margin-x, right: margin-x, top: 28pt, bottom: 54pt),
    background: none,
    footer: context {
      set text(size: 8.5pt, fill: ink-muted)
      line(length: 100%, stroke: 0.75pt + line-color)
      v(-2pt)
      grid(
        columns: (1fr, auto),
        [#data.sectionName · #data.url],
        [Stand #data.generatedAt · Seite #counter(page).display() / #counter(page).final().first()],
      )
    },
  )

  // Für das Inhaltsverzeichnis des Kurshefts und die Lesezeichen im PDF (unsichtbar)
  [#metadata((title: data.title, period: data.period, group: data.at("tocGroup", default: none))) <event>]
  {
    show heading: none
    heading(level: 1, outlined: false, bookmarked: true, data.title)
  }

  // Kopf: Logo und Eventnummer
  grid(
    columns: (1fr, auto),
    align: (left + horizon, right + horizon),
    image(data.logo, height: 36pt),
    text(size: 9.5pt, fill: ink-muted)[
      #data.sectionName \
      #text(..display, weight: 700, fill: ink, number-type: "lining")[#data.numberLabel #data.eventNumber]
    ],
  )

  v(16pt)

  signpost(fill: accent, {
    set text(fill: white)
    set par(spacing: 0pt)
    text(size: 9.5pt, weight: 700, tracking: 0.08em, upper(data.kicker.join("  ·  ")))
    v(9pt)
    par(leading: 12pt, text(..display, weight: 800, size: 34pt, tracking: -0.02em, number-type: "lining", data.title))
    if has(data.teaser) {
      // Abstand ab der Grundlinie des Titels, Unterlängen (g, p) inklusive
      v(18pt)
      par(leading: 5pt, text(size: 12pt, data.teaser))
    }
  })

  // Abgesagt oder verschoben: Hinweis unter dem Titel
  if has(data.eventState) {
    v(8pt)
    block(width: 100%, fill: signal-soft, radius: 6pt, inset: (x: 12pt, y: 8pt),
      text(weight: 700, fill: signal, data.eventState))
  }

  v(22pt)

  // Hauptspalte links, Seitenleiste mit den Eckdaten rechts
  grid(
    columns: (1fr, 165pt),
    column-gutter: 22pt,
    main,
    sidebar,
  )
}
