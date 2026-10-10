"""Writes a minimal one-page sample datasheet PDF (original content) for the demo."""
import os
out = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "palltheme-core", "assets", "demo", "datasheet-sample.pdf")
lines = ["TechNova Systems - Sample Product Datasheet", "", "This is demo content generated for the Palltheme demo import.",
         "Replace it with the manufacturer's datasheet for your product.", "", "CPU: 2 x Intel Xeon Gold 6430", "RAM: 128GB DDR5",
         "Storage: 2 x 1.92TB SSD", "Network: 2 x 25GbE", "Warranty: 3 Years"]
text = "BT /F1 14 Tf 60 780 Td 18 TL " + " ".join("(" + l.replace("(", "[").replace(")", "]") + ") '" for l in lines) + " ET"
objs = ["<< /Type /Catalog /Pages 2 0 R >>", "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
        f"<< /Length {len(text)} >>\nstream\n{text}\nendstream", "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"]
pdf = b"%PDF-1.4\n"; offs = []
for i, o in enumerate(objs, 1):
    offs.append(len(pdf)); pdf += f"{i} 0 obj\n{o}\nendobj\n".encode("latin-1")
x = len(pdf)
pdf += f"xref\n0 {len(objs)+1}\n0000000000 65535 f \n".encode() + b"".join(f"{o:010d} 00000 n \n".encode() for o in offs)
pdf += f"trailer\n<< /Size {len(objs)+1} /Root 1 0 R >>\nstartxref\n{x}\n%%EOF\n".encode()
open(out, "wb").write(pdf); print(out, len(pdf))
