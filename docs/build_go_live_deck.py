"""Build the 'Service Desk is live' announcement deck as a landscape PDF."""

from reportlab.lib.colors import HexColor, white
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfgen import canvas
from reportlab.platypus import Paragraph

W, H = landscape(A4)          # 842 x 595 pt

INK      = HexColor("#0F172A")   # slate-900
MUTED    = HexColor("#64748B")   # slate-500
LINE     = HexColor("#E2E8F0")   # slate-200
BRAND    = HexColor("#4F46E5")   # indigo-600
BRAND_LT = HexColor("#EEF2FF")   # indigo-50
GREEN    = HexColor("#16A34A")
AMBER    = HexColor("#D97706")
RED      = HexColor("#DC2626")
PANEL    = HexColor("#F8FAFC")

MARGIN = 54
SYSTEM = "Dewan Motors Service Desk"

c = canvas.Canvas("Service-Desk-Go-Live.pdf", pagesize=landscape(A4))
c.setTitle("Service Desk is Live - Dewan Motors")
c.setAuthor("Information Technology Department")
c.setSubject("Service desk go-live announcement and quick start guide")

page_no = [0]


def body_style(size=12, leading=17, color=INK, bold=False):
    return ParagraphStyle(
        "b",
        fontName="Helvetica-Bold" if bold else "Helvetica",
        fontSize=size,
        leading=leading,
        textColor=color,
    )


def para(text, x, y, width, style):
    """Draw a paragraph with its TOP edge at y. Returns the new y (bottom)."""
    p = Paragraph(text, style)
    _, h = p.wrap(width, H)
    p.drawOn(c, x, y - h)
    return y - h


def chrome(kicker):
    """Shared header/footer for content slides."""
    page_no[0] += 1

    c.setFillColor(BRAND)
    c.rect(0, H - 6, W, 6, stroke=0, fill=1)

    c.setFont("Helvetica-Bold", 8.5)
    c.setFillColor(BRAND)
    c.drawString(MARGIN, H - 34, kicker.upper())

    c.setFillColor(LINE)
    c.rect(MARGIN, 44, W - 2 * MARGIN, 0.7, stroke=0, fill=1)

    c.setFont("Helvetica", 8)
    c.setFillColor(MUTED)
    c.drawString(MARGIN, 30, SYSTEM)
    c.drawRightString(W - MARGIN, 30, str(page_no[0]))


def heading(text, y=H - 78, size=27):
    c.setFont("Helvetica-Bold", size)
    c.setFillColor(INK)
    c.drawString(MARGIN, y, text)
    return y


def subheading(text, y):
    c.setFont("Helvetica", 12.5)
    c.setFillColor(MUTED)
    c.drawString(MARGIN, y, text)
    return y


# --------------------------------------------------------------- slide 1: title
c.setFillColor(HexColor("#1E1B4B"))
c.rect(0, 0, W, H, stroke=0, fill=1)
c.setFillColor(BRAND)
c.rect(0, 0, 8, H, stroke=0, fill=1)

c.setFillColor(HexColor("#A5B4FC"))
c.setFont("Helvetica-Bold", 10)
c.drawString(MARGIN, H - 112, "INFORMATION TECHNOLOGY DEPARTMENT")

c.setFillColor(white)
c.setFont("Helvetica-Bold", 44)
c.drawString(MARGIN, H - 196, "The Service Desk is now live")

c.setFillColor(HexColor("#C7D2FE"))
c.setFont("Helvetica", 16)
c.drawString(MARGIN, H - 233, "Dewan Motors (Pvt) Limited")

c.setFillColor(HexColor("#818CF8"))
c.rect(MARGIN, H - 261, 90, 2.5, stroke=0, fill=1)

para(
    "From today, every IT request is raised, tracked and closed in one system. "
    "No more phone calls that get forgotten and no more requests lost in WhatsApp. "
    "This short guide tells you how to sign in and what to do.",
    MARGIN, H - 291, 560, body_style(13, 19, HexColor("#E0E7FF")),
)

stats = [("337", "staff accounts"), ("14", "locations"), ("18", "departments")]
sx = MARGIN
for value, label in stats:
    c.setFillColor(white)
    c.setFont("Helvetica-Bold", 26)
    c.drawString(sx, 118, value)
    c.setFillColor(HexColor("#A5B4FC"))
    c.setFont("Helvetica", 10)
    c.drawString(sx, 100, label)
    sx += 150

c.showPage()

# ------------------------------------------------------------ slide 2: sign in
chrome("Step 1")
heading("Signing in")
subheading("Your mobile number is your user name.", H - 104)

box_y, box_h = 232, 210
c.setFillColor(BRAND_LT)
c.roundRect(MARGIN, box_y, 360, box_h, 10, stroke=0, fill=1)

c.setFillColor(BRAND)
c.setFont("Helvetica-Bold", 9)
c.drawString(MARGIN + 26, box_y + box_h - 34, "YOUR CREDENTIALS")

rows = [("User name", "Your mobile number"), ("Password", "abcd1234")]
ry = box_y + box_h - 74
for label, value in rows:
    c.setFillColor(MUTED)
    c.setFont("Helvetica", 10)
    c.drawString(MARGIN + 26, ry, label)
    c.setFillColor(INK)
    c.setFont("Helvetica-Bold", 17)
    c.drawString(MARGIN + 26, ry - 24, value)
    ry -= 66

c.setFillColor(MUTED)
c.setFont("Helvetica", 9)
c.drawString(MARGIN + 26, box_y + 22, "Starting password only — you will replace it on first sign-in")

rx = MARGIN + 400
notes = [
    ("Enter it any way you like",
     "3018298234, 0301-829 8234 or +92 301 8298234 all work."),
    ("You will choose your own password at once",
     "The system asks for a new one the first time you sign in. Nobody else will know it."),
    ("You will only see your own company and location",
     "The two boxes at the top of the screen show which one you are viewing."),
    ("Forgot it later on?",
     "Contact IT. They cannot read your password, but they can issue you a new one."),
]
ny = box_y + box_h - 8
for title, detail in notes:
    c.setFillColor(BRAND)
    c.circle(rx + 4, ny - 6, 3.2, stroke=0, fill=1)
    ny = para(f"<b>{title}</b>", rx + 18, ny, 330, body_style(11.5, 15))
    ny = para(detail, rx + 18, ny - 3, 330, body_style(10.5, 14, MUTED))
    ny -= 16

c.showPage()

# ------------------------------------------------------ slide 3: raise a ticket
chrome("Step 2")
heading("Raising a ticket")
subheading("Anything broken, missing or needed from IT starts here.", H - 104)

steps = [
    ("Open Tickets", "Left-hand menu, then click New ticket."),
    ("Say what is wrong", "One clear line. \"Printer not printing, 2nd floor\" beats \"urgent\"."),
    ("Add the detail", "What you were doing, what happened, the error. Attach a photo if it helps."),
    ("Set the priority", "Honestly. It sets the deadline IT is measured against."),
    ("Create", "You get a ticket number. Quote it whenever you follow up."),
]

cw, gap = 138, 14
x = MARGIN
for i, (title, detail) in enumerate(steps, start=1):
    c.setFillColor(PANEL)
    c.roundRect(x, 212, cw, 172, 9, stroke=0, fill=1)
    c.setFillColor(BRAND)
    c.circle(x + 26, 356, 13, stroke=0, fill=1)
    c.setFillColor(white)
    c.setFont("Helvetica-Bold", 12)
    c.drawCentredString(x + 26, 352, str(i))

    y2 = para(f"<b>{title}</b>", x + 14, 326, cw - 28, body_style(12, 15))
    para(detail, x + 14, y2 - 8, cw - 28, body_style(9.8, 13, MUTED))
    x += cw + gap

c.setFillColor(HexColor("#FEF3C7"))
c.roundRect(MARGIN, 128, W - 2 * MARGIN, 52, 8, stroke=0, fill=1)
para(
    "<b>Not sure if it is a ticket?</b> A fault, a request or a question for IT is a ticket. "
    "Something that has to be built or changed in the software is a Development request.",
    MARGIN + 18, 166, W - 2 * MARGIN - 36, body_style(11, 15, HexColor("#92400E")),
)

c.showPage()

# --------------------------------------------------- slide 4: priorities & SLA
chrome("Step 3")
heading("Priorities and deadlines")
subheading("The priority you pick decides when IT must respond.", H - 104)

headers = ["Priority", "First reply within", "Fixed within", "Use it when"]
data = [
    ("Critical", "1 hour", "8 hours", "Work has stopped for a team or a location.", RED),
    ("High", "4 hours", "24 hours", "You cannot do your job, but others can.", AMBER),
    ("Medium", "8 hours", "48 hours", "It slows you down; there is a way around it.", BRAND),
    ("Low", "24 hours", "120 hours", "A request or an improvement, nothing urgent.", MUTED),
]

col_x = [MARGIN, MARGIN + 130, MARGIN + 275, MARGIN + 395]
ty = 400

c.setFillColor(MUTED)
c.setFont("Helvetica-Bold", 8.5)
for i, head in enumerate(headers):
    c.drawString(col_x[i], ty, head.upper())

c.setFillColor(LINE)
c.rect(MARGIN, ty - 10, W - 2 * MARGIN, 0.7, stroke=0, fill=1)

ty -= 44
for name, reply, fix, when, colour in data:
    c.setFillColor(colour)
    c.roundRect(col_x[0], ty - 6, 66, 21, 4, stroke=0, fill=1)
    c.setFillColor(white)
    c.setFont("Helvetica-Bold", 10)
    c.drawCentredString(col_x[0] + 33, ty + 0.5, name)

    c.setFillColor(INK)
    c.setFont("Helvetica-Bold", 12)
    c.drawString(col_x[1], ty, reply)
    c.drawString(col_x[2], ty, fix)

    c.setFillColor(MUTED)
    c.setFont("Helvetica", 11)
    c.drawString(col_x[3], ty, when)

    c.setFillColor(LINE)
    c.rect(MARGIN, ty - 20, W - 2 * MARGIN, 0.5, stroke=0, fill=1)
    ty -= 52

para(
    "Every ticket shows a coloured label: <b>On track</b>, <b>At risk</b> when the deadline is close, "
    "and <b>Breached</b> once it has passed. Management sees these figures, so priorities set honestly "
    "are what keep the numbers meaningful.",
    MARGIN, 168, W - 2 * MARGIN, body_style(11, 15, MUTED),
)

c.showPage()

# ------------------------------------------------- slide 5: what happens next
chrome("Step 4")
heading("What happens after you raise it")
subheading("You are notified at every stage — watch the bell icon.", H - 104)

flow = [
    ("Open", "Ticket created.\nIT is notified at once.", BRAND),
    ("In Progress", "An IT engineer is\nassigned by name.", BRAND),
    ("Resolved", "The fix is recorded.\nYou are notified.", GREEN),
    ("Closed", "Done. Still broken?\nReply and it reopens.", INK),
]

bw, bgap = 168, 34
x = MARGIN + 8
for i, (state, detail, colour) in enumerate(flow):
    c.setFillColor(PANEL)
    c.roundRect(x, 244, bw, 140, 9, stroke=0, fill=1)
    c.setFillColor(colour)
    c.roundRect(x, 244, bw, 4, 2, stroke=0, fill=1)

    c.setFillColor(colour)
    c.setFont("Helvetica-Bold", 15)
    c.drawString(x + 18, 346, state)

    ly = 320
    for line in detail.split("\n"):
        c.setFillColor(MUTED)
        c.setFont("Helvetica", 10.5)
        c.drawString(x + 18, ly, line)
        ly -= 15

    if i < len(flow) - 1:
        ax = x + bw + bgap / 2
        c.setStrokeColor(HexColor("#CBD5E1"))
        c.setLineWidth(1.6)
        c.line(ax - 9, 314, ax + 7, 314)
        c.line(ax + 2, 309, ax + 7, 314)
        c.line(ax + 2, 319, ax + 7, 314)

    x += bw + bgap

para(
    "<b>You can comment on your ticket at any time</b> — to add information or to ask where it has reached. "
    "Once it is resolved you can rate it out of five. That rating is how IT knows whether the job was "
    "actually done to your satisfaction.",
    MARGIN, 196, W - 2 * MARGIN, body_style(11.5, 16),
)

c.showPage()

# ------------------------------------------------------- slide 6: who does what
chrome("Roles")
heading("Who does what")
subheading("Your role decides what appears in your menu.", H - 104)

roles = [
    ("Every employee", "Raises tickets and development requests, and follows their own."),
    ("Department heads", "The above, plus creating and assigning tasks in their department."),
    ("IT support staff", "Work the tickets assigned to them and record the fix."),
    ("IT managers", "Assign tickets to engineers, resolve, close and report."),
    ("DGM and management", "See everything across companies and locations; assign tasks."),
]

ry = 400
for title, detail in roles:
    c.setFillColor(BRAND_LT)
    c.roundRect(MARGIN, ry - 12, 190, 32, 6, stroke=0, fill=1)
    c.setFillColor(BRAND)
    c.setFont("Helvetica-Bold", 11.5)
    c.drawString(MARGIN + 14, ry - 1, title)

    c.setFillColor(INK)
    c.setFont("Helvetica", 11.5)
    c.drawString(MARGIN + 212, ry - 1, detail)
    ry -= 46

c.setFillColor(PANEL)
c.roundRect(MARGIN, 130, W - 2 * MARGIN, 62, 8, stroke=0, fill=1)
para(
    "<b>Tasks are not tickets.</b> A ticket is something broken or needed from IT. A task is a piece of work "
    "given to you by your head of department or by management, with a due date. Both appear in the left menu.",
    MARGIN + 18, 178, W - 2 * MARGIN - 36, body_style(11.5, 16),
)

c.showPage()

# ------------------------------------------------------------- slide 7: closing
chrome("Help")
heading("Everything else is in the system")

para(
    "Sign in and open <b>User guide</b> in the left-hand menu. It is written for your role — it shows only "
    "the steps you can actually perform, lists the IT team for your company with their mobile numbers, "
    "and answers the questions people ask most.",
    MARGIN, H - 128, 470, body_style(13, 19),
)

c.setFillColor(PANEL)
c.roundRect(MARGIN, 212, 470, 150, 9, stroke=0, fill=1)
c.setFillColor(MUTED)
c.setFont("Helvetica-Bold", 8.5)
c.drawString(MARGIN + 22, 336, "IT MANAGERS")

cy = 306
for name, phone in [("Muhammad Sahal Qasim", "0311 8711062"),
                    ("Muhammad Rizwan Jaffar", "0345 8206666")]:
    c.setFillColor(INK)
    c.setFont("Helvetica-Bold", 13)
    c.drawString(MARGIN + 22, cy, name)
    c.setFillColor(MUTED)
    c.setFont("Helvetica", 11)
    c.drawString(MARGIN + 22, cy - 17, phone + "   ·   Dewan Centre H.O")
    cy -= 54

c.setFillColor(HexColor("#1E1B4B"))
c.roundRect(MARGIN + 500, 212, W - MARGIN - (MARGIN + 500), 150, 9, stroke=0, fill=1)
para(
    "Please raise a ticket first.",
    MARGIN + 524, 336, 220, body_style(15, 20, white, bold=True),
)
para(
    "It gives your request a number, a deadline and a record. "
    "Phone only when it is genuinely urgent.",
    MARGIN + 524, 302, 220, body_style(11, 15, HexColor("#C7D2FE")),
)

c.setFillColor(BRAND)
c.rect(MARGIN, 150, 44, 2.5, stroke=0, fill=1)
c.setFillColor(MUTED)
c.setFont("Helvetica", 11)
c.drawString(MARGIN, 126, "Information Technology Department  ·  Dewan Motors (Pvt) Limited")

c.showPage()
c.save()
print("written: Service-Desk-Go-Live.pdf")
