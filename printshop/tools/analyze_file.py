#!/usr/bin/env python3
"""Count the pages of an uploaded file. Called by the PHP app:
    python3 tools/analyze_file.py <path> <extension>
Prints JSON like {"pages": 12}. Uses only the standard library (pypdf is used if installed,
which is more accurate for PDFs: pip install pypdf)."""
import json
import re
import sys
import zipfile


def pdf_pages(path):
    try:
        from pypdf import PdfReader  # optional, more accurate
        return len(PdfReader(path).pages)
    except Exception:
        pass
    with open(path, "rb") as fh:
        data = fh.read()
    counts = [int(m) for m in re.findall(rb"/Count\s+(\d+)", data)]
    if counts:
        return max(counts)
    return len(re.findall(rb"/Type\s*/Page[^s]", data))


def office_pages(path, tag):
    """DOCX stores <Pages>, PPTX stores <Slides> in docProps/app.xml."""
    try:
        with zipfile.ZipFile(path) as z:
            xml = z.read("docProps/app.xml").decode("utf-8", "ignore")
        m = re.search(rf"<{tag}>(\d+)</{tag}>", xml)
        if m:
            return int(m.group(1))
        if tag == "Slides":
            with zipfile.ZipFile(path) as z:
                return len([n for n in z.namelist() if re.match(r"ppt/slides/slide\d+\.xml$", n)])
    except Exception:
        pass
    return None


def main():
    if len(sys.argv) < 3:
        print(json.dumps({"error": "usage: analyze_file.py <path> <ext>"}))
        return 1
    path, ext = sys.argv[1], sys.argv[2].lower().lstrip(".")
    pages = None
    if ext == "pdf":
        pages = pdf_pages(path)
    elif ext == "docx":
        pages = office_pages(path, "Pages")
    elif ext == "pptx":
        pages = office_pages(path, "Slides")
    elif ext in ("jpg", "jpeg", "png"):
        pages = 1
    print(json.dumps({"pages": pages or 0}))
    return 0


if __name__ == "__main__":
    sys.exit(main())
