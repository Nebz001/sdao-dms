<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Printable Form' }}</title>
    <style>
        @page { margin: 10mm 11mm; }

        body {
            margin: 0;
            font-family: "Helvetica", "DejaVu Sans", sans-serif;
            font-size: 7.5pt;
            line-height: 1.25;
            color: #000;
        }

        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        td { vertical-align: top; padding: 5pt 3pt; }

        {{--
            Every section is its own <table> for markup convenience, but the
            paper form is one continuous ruled block with no gaps. Each table
            gets a heavier "outer" border (1.5pt, via the table element
            itself, which wins the border-collapse conflict against the 0.75pt
            cell borders at that table's own edge); the negative margin pulls
            each table up so consecutive tables' outer edges overlap into one
            line instead of doubling. Exact paper-form weights are not
            recoverable from a scanned image (see OrganizationApplicationForm
            docblock) — 1.5pt/0.75pt approximates the observed ~2:1 ratio.
        --}}
        .bordered-table { border: 1.5pt solid #000; margin-top: -1.5pt; }
        .bordered-table td { border: 0.75pt solid #000; }

        .letterhead td { border: none; vertical-align: middle; padding: 3pt 3pt 6pt; text-align: left; }
        .letterhead .logo-cell { width: 11%; text-align: center; }
        .letterhead .logo-cell img { width: 9mm; height: 9mm; }
        .letterhead .org-line { font-size: 9pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3pt; }

        .title-bar-cell {
            background-color: #1a1a1a;
            color: #fff;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .form-code-cell { font-size: 7pt; text-align: center; white-space: nowrap; vertical-align: middle; width: 16.5%; }

        {{-- Casing is literal per section on the paper form (e.g. "1. Contact
             Information" vs "2. DETAILS OF ORGANIZATION") — never transform it here;
             each Blade view writes each label's exact casing. --}}
        .bar {
            background-color: #1a1a1a;
            color: #fff;
            font-weight: bold;
        }

        .label-col { background-color: #efefef; font-weight: bold; width: 23%; }

        .cb {
            display: inline-block;
            width: 6pt;
            height: 6pt;
            line-height: 6pt;
            border: 0.5pt solid #000;
            text-align: center;
            font-size: 5.5pt;
            font-weight: bold;
            margin-right: 2pt;
        }
        .opt { margin-right: 10pt; }
        .opt-row { margin-bottom: 2pt; }

        .sig-cell { height: 16mm; }
        .remarks-cell { height: 15mm; }

        .rule { border-bottom: 0.75pt solid #000; height: 14mm; margin: 0 2pt; }
        .rule-short { border-bottom: 0.75pt solid #000; height: 5mm; margin: 4pt 2pt 0; }

        .section { page-break-inside: avoid; }

        .instructions { font-size: 6.5pt; line-height: 1.35; padding: 3pt; }
        .instructions ol { margin: 2pt 0 0 12pt; padding: 0; }

        {{--
            Shared across Part 2's narrative-style forms (Activity Proposal's
            proposal narrative, plus any future free-text form) — a page
            forced onto its own sheet, numbered section headings (I., II.,
            III. …), lettered sub-items (a., b., c. …), and small italic
            placeholder/instruction text.
        --}}
        .page-break { page-break-before: always; }
        .h2 { font-weight: bold; font-size: 9pt; margin-top: 8pt; margin-bottom: 2pt; }
        .indent { margin-left: 10pt; }
        .muted-note { font-style: italic; color: #444; }
    </style>
</head>
<body>
    @yield('content')
</body>
</html>
