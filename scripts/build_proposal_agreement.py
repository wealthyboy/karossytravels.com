from io import BytesIO
from pathlib import Path

from pypdf import PdfReader, PdfWriter
from reportlab.lib.colors import HexColor, white
from reportlab.lib.pagesizes import A4
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "docs/Karossy-Travels-Digital-Ecosystem-Proposal-Revised.pdf"
OUTPUT = ROOT / "docs/Karossy-Travels-Digital-Ecosystem-Proposal-Agreement.pdf"
ARIAL = "/System/Library/Fonts/Supplemental/Arial.ttf"
ARIAL_BOLD = "/System/Library/Fonts/Supplemental/Arial Bold.ttf"

NAVY = HexColor("#071A36")
BLUE = HexColor("#0B3A70")
MUTED = HexColor("#607087")
PALE = HexColor("#F1F6FB")
LINE = HexColor("#D6E1EC")
RED = HexColor("#ED1C24")


def schedule_page():
    stream = BytesIO()
    pdf = canvas.Canvas(stream, pagesize=A4)
    width, height = A4

    pdf.setFillColor(NAVY)
    pdf.setFont("ArialBold", 18)
    pdf.drawString(42, height - 54, "ACHU SYSTEMS")
    pdf.setFillColor(MUTED)
    pdf.setFont("ArialBold", 7)
    pdf.drawRightString(width - 42, height - 48, "EXECUTION SCHEDULE")
    pdf.setStrokeColor(LINE)
    pdf.line(42, height - 72, width - 42, height - 72)

    pdf.setFillColor(RED)
    pdf.setFont("ArialBold", 8)
    pdf.drawString(42, height - 105, "MEASURABLE WORKFLOW & TIMELINE")
    pdf.setFillColor(NAVY)
    pdf.setFont("ArialBold", 22)
    pdf.drawString(42, height - 134, "Six-week execution programme")
    pdf.setFillColor(MUTED)
    pdf.setFont("Arial", 9)
    pdf.drawString(42, height - 154, "The programme begins on the contract commencement date recorded on the acceptance page.")

    rows = [
        ("01", "Discovery & specification", "Approved backlog, user journeys, architecture, provider matrix", "Scope and solution blueprint signed off"),
        ("02", "UX/UI & platform foundation", "Responsive design system, core screens, environments, CI/CD", "Design review approved; staging available"),
        ("03", "Customer booking journeys", "Authentication, search, results, details, checkout workflows", "Core web journeys demonstrated on staging"),
        ("04", "Admin, payments & integrations", "Admin modules, Paystack/Flutterwave, supplier adapters", "Test transactions and API flows evidenced"),
        ("05", "Mobile, QA & content readiness", "Android/iOS builds, regression testing, content population", "UAT build issued; critical defects resolved"),
        ("06", "UAT, training & production launch", "UAT closure, staff training, deployment, handover pack", "Go-live checklist and acceptance completed"),
    ]

    y = height - 190
    for number, phase, deliverable, measure in rows:
        pdf.setFillColor(PALE)
        pdf.roundRect(42, y - 69, width - 84, 62, 6, stroke=0, fill=1)
        pdf.setFillColor(RED)
        pdf.setFont("ArialBold", 8)
        pdf.drawString(54, y - 25, f"WEEK {number}")
        pdf.setFillColor(NAVY)
        pdf.setFont("ArialBold", 10)
        pdf.drawString(104, y - 25, phase)
        pdf.setFillColor(MUTED)
        pdf.setFont("Arial", 7.5)
        pdf.drawString(104, y - 42, deliverable)
        pdf.setFillColor(BLUE)
        pdf.setFont("ArialBold", 7.2)
        pdf.drawString(104, y - 56, "MEASURE:")
        pdf.setFont("Arial", 7.2)
        pdf.drawString(148, y - 56, measure)
        y -= 71

    pdf.setFillColor(NAVY)
    pdf.setFont("ArialBold", 9)
    pdf.drawString(42, y - 7, "Governance and measurement")
    pdf.setFillColor(MUTED)
    pdf.setFont("Arial", 7.5)
    notes = [
        "• Weekly progress report: completed items, next actions, risks, decisions and evidence links.",
        "• Weekly review/demo with Karossy's authorized representative; feedback due within two business days.",
        "• A milestone is complete when its stated evidence is supplied and no critical acceptance issue remains.",
        "• Supplier certification delays or late credentials/content shift affected dates without reducing scope.",
    ]
    for note in notes:
        y -= 14
        pdf.drawString(42, y - 7, note)

    pdf.setFillColor(MUTED)
    pdf.setFont("Arial", 6.5)
    pdf.drawString(42, 30, "PRIVATE AND CONFIDENTIAL")
    pdf.drawRightString(width - 42, 30, "12")
    pdf.save()
    stream.seek(0)
    return PdfReader(stream).pages[0]


def acceptance_overlay(width, height):
    stream = BytesIO()
    pdf = canvas.Canvas(stream, pagesize=(width, height))

    # Replace the previously-added signature area with agreement particulars.
    pdf.setFillColor(white)
    pdf.rect(36, 94, width - 72, 166, stroke=0, fill=1)
    pdf.setFillColor(RED)
    pdf.setFont("ArialBold", 7.5)
    pdf.drawString(45, 242, "AGREEMENT PARTICULARS")
    pdf.setFillColor(NAVY)
    pdf.setFont("ArialBold", 8)
    pdf.drawString(45, 222, "CONTRACT COMMENCEMENT DATE")
    pdf.setStrokeColor(MUTED)
    pdf.line(188, 220, 365, 220)
    pdf.setFillColor(MUTED)
    pdf.setFont("Arial", 6.5)
    pdf.drawString(188, 225, "To be completed by the parties")
    pdf.setFillColor(NAVY)
    pdf.setFont("Arial", 7.2)
    pdf.drawString(45, 204, "The six-week delivery programme and included support period are measured from the agreed commencement date.")

    columns = [
        (45, "FOR ACHU SYSTEMS", "Jacob Atam · Lead Developer"),
        (315, "FOR KAROSSY TRAVELS & TOURS LIMITED", "Authorized Representative"),
    ]
    for x, heading, representative in columns:
        pdf.setFillColor(NAVY)
        pdf.setFont("ArialBold", 7.2)
        pdf.drawString(x, 181, heading)
        pdf.setStrokeColor(MUTED)
        pdf.line(x, 148, x + 225, 148)
        pdf.line(x, 112, x + 105, 112)
        pdf.line(x + 120, 112, x + 225, 112)
        pdf.setFillColor(MUTED)
        pdf.setFont("Arial", 6.2)
        pdf.drawString(x, 153, "Signature")
        pdf.drawString(x, 117, "Name")
        pdf.drawString(x + 120, 117, "Date")
        pdf.setFillColor(NAVY)
        pdf.setFont("Arial", 7)
        pdf.drawString(x, 135, representative)

    # The inserted schedule makes the acceptance page page 13.
    pdf.setFillColor(white)
    pdf.rect(width - 65, 96, 30, 20, stroke=0, fill=1)
    pdf.setFillColor(MUTED)
    pdf.setFont("Arial", 6.5)
    pdf.drawRightString(width - 42, 109, "13")
    pdf.save()
    stream.seek(0)
    return PdfReader(stream).pages[0]


def commercial_validity_overlay(width, height):
    stream = BytesIO()
    pdf = canvas.Canvas(stream, pagesize=(width, height))
    prefix = "This commercial proposal is valid for thirty (30) days from "
    x = 45.4 + pdfmetrics.stringWidth(prefix, "Arial", 9.7)
    pdf.setFillColor(white)
    pdf.rect(x - 1, 145, 65, 16, stroke=0, fill=1)
    pdf.setFillColor(NAVY)
    pdf.setFont("Arial", 9.7)
    pdf.drawString(x, 154.4, "24 July 2026.")
    pdf.save()
    stream.seek(0)
    return PdfReader(stream).pages[0]


def main():
    pdfmetrics.registerFont(TTFont("Arial", ARIAL))
    pdfmetrics.registerFont(TTFont("ArialBold", ARIAL_BOLD))
    reader = PdfReader(SOURCE)
    writer = PdfWriter()

    for index, page in enumerate(reader.pages[:-1]):
        if index == 9:
            page.merge_page(commercial_validity_overlay(float(page.mediabox.width), float(page.mediabox.height)))
        writer.add_page(page)

    writer.add_page(schedule_page())
    acceptance = reader.pages[-1]
    acceptance.merge_page(acceptance_overlay(float(acceptance.mediabox.width), float(acceptance.mediabox.height)))
    writer.add_page(acceptance)
    writer.add_metadata({
        "/Title": "Karossy Travels Digital Ecosystem Proposal Agreement",
        "/Author": "Achu Systems",
        "/Subject": "₦6,500,000 proposal agreement with measurable six-week execution schedule",
    })

    with OUTPUT.open("wb") as destination:
        writer.write(destination)
    print(OUTPUT)


if __name__ == "__main__":
    main()
