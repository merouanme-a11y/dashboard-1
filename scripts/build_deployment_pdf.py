from __future__ import annotations

import textwrap
from pathlib import Path


PAGE_WIDTH = 595
PAGE_HEIGHT = 842
LEFT = 50
RIGHT = 50
TOP = 60
BOTTOM = 55
FONT_SIZE = 11
LEADING = 15
TITLE_SIZE = 18
TITLE_LEADING = 24
MAX_CHARS = 92


def escape_pdf_text(value: str) -> str:
    return value.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")


def normalize_line(line: str) -> str:
    return line.replace("\t", "    ").rstrip()


def markdown_to_lines(text: str) -> list[tuple[str, str]]:
    lines: list[tuple[str, str]] = []
    in_code = False

    for raw in text.splitlines():
        line = normalize_line(raw)

        if line.startswith("```"):
            in_code = not in_code
            if not in_code:
                lines.append(("blank", ""))
            continue

        if in_code:
            if line:
                lines.append(("code", line))
            else:
                lines.append(("blank", ""))
            continue

        if not line:
            lines.append(("blank", ""))
            continue

        if line.startswith("# "):
            lines.append(("title", line[2:].strip()))
            continue

        if line.startswith("## "):
            lines.append(("heading", line[3:].strip()))
            continue

        if line.startswith("- "):
            wrapped = textwrap.wrap(
                line[2:].strip(),
                width=MAX_CHARS - 3,
                break_long_words=False,
                break_on_hyphens=False,
            ) or [""]
            lines.append(("bullet", wrapped[0]))
            for extra in wrapped[1:]:
                lines.append(("bullet_cont", extra))
            continue

        if line[:3].isdigit() and line[1:3] == ". ":
            prefix, content = line.split(". ", 1)
            wrapped = textwrap.wrap(
                content.strip(),
                width=MAX_CHARS - len(prefix) - 2,
                break_long_words=False,
                break_on_hyphens=False,
            ) or [""]
            lines.append(("number", f"{prefix}. {wrapped[0]}"))
            indent = " " * (len(prefix) + 2)
            for extra in wrapped[1:]:
                lines.append(("number_cont", f"{indent}{extra}"))
            continue

        wrapped = textwrap.wrap(
            line,
            width=MAX_CHARS,
            break_long_words=False,
            break_on_hyphens=False,
        ) or [""]
        for part in wrapped:
            lines.append(("text", part))

    return lines


def build_pages(lines: list[tuple[str, str]]) -> list[list[tuple[str, str]]]:
    pages: list[list[tuple[str, str]]] = [[]]
    y = PAGE_HEIGHT - TOP

    def line_height(kind: str) -> int:
        if kind == "title":
            return TITLE_LEADING
        if kind in {"heading"}:
            return 19
        if kind == "blank":
            return 8
        return LEADING

    for item in lines:
        kind, _ = item
        needed = line_height(kind)
        if y - needed < BOTTOM:
            pages.append([])
            y = PAGE_HEIGHT - TOP
        pages[-1].append(item)
        y -= needed

    return pages


def page_stream(page: list[tuple[str, str]]) -> bytes:
    y = PAGE_HEIGHT - TOP
    parts: list[str] = ["BT", "/F1 11 Tf", "0 g"]

    for kind, text in page:
        if kind == "blank":
            y -= 8
            continue

        if kind == "title":
            parts.append(f"/F2 {TITLE_SIZE} Tf")
            parts.append(f"1 0 0 1 {LEFT} {y} Tm")
            parts.append(f"({escape_pdf_text(text)}) Tj")
            y -= TITLE_LEADING
            parts.append(f"/F1 {FONT_SIZE} Tf")
            continue

        if kind == "heading":
            parts.append(f"/F2 13 Tf")
            parts.append(f"1 0 0 1 {LEFT} {y} Tm")
            parts.append(f"({escape_pdf_text(text)}) Tj")
            y -= 19
            parts.append(f"/F1 {FONT_SIZE} Tf")
            continue

        x = LEFT
        if kind == "bullet":
            text = f"- {text}"
        elif kind == "bullet_cont":
            x = LEFT + 14
        elif kind == "number_cont":
            x = LEFT + 14

        parts.append(f"1 0 0 1 {x} {y} Tm")
        parts.append(f"({escape_pdf_text(text)}) Tj")
        y -= LEADING

    parts.append("ET")
    return "\n".join(parts).encode("latin-1", errors="replace")


def build_pdf(text: str, output_path: Path) -> None:
    lines = markdown_to_lines(text)
    pages = build_pages(lines)

    objects: list[bytes] = []

    def add_object(content: bytes) -> int:
        objects.append(content)
        return len(objects)

    font1 = add_object(b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>")
    font2 = add_object(b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>")

    page_ids: list[int] = []
    content_ids: list[int] = []

    pages_placeholder = add_object(b"<<>>")

    for page in pages:
        stream = page_stream(page)
        content = (
            f"<< /Length {len(stream)} >>\nstream\n".encode("latin-1")
            + stream
            + b"\nendstream"
        )
        content_id = add_object(content)
        content_ids.append(content_id)

        page_obj = (
            f"<< /Type /Page /Parent {pages_placeholder} 0 R /MediaBox [0 0 {PAGE_WIDTH} {PAGE_HEIGHT}] "
            f"/Resources << /Font << /F1 {font1} 0 R /F2 {font2} 0 R >> >> /Contents {content_id} 0 R >>"
        ).encode("latin-1")
        page_id = add_object(page_obj)
        page_ids.append(page_id)

    kids = " ".join(f"{page_id} 0 R" for page_id in page_ids)
    objects[pages_placeholder - 1] = (
        f"<< /Type /Pages /Count {len(page_ids)} /Kids [{kids}] >>".encode("latin-1")
    )

    catalog = add_object(f"<< /Type /Catalog /Pages {pages_placeholder} 0 R >>".encode("latin-1"))

    output_path.parent.mkdir(parents=True, exist_ok=True)

    with output_path.open("wb") as fh:
        fh.write(b"%PDF-1.4\n%\xe2\xe3\xcf\xd3\n")
        offsets = [0]
        for index, obj in enumerate(objects, start=1):
            offsets.append(fh.tell())
            fh.write(f"{index} 0 obj\n".encode("latin-1"))
            fh.write(obj)
            fh.write(b"\nendobj\n")

        xref_pos = fh.tell()
        fh.write(f"xref\n0 {len(objects) + 1}\n".encode("latin-1"))
        fh.write(b"0000000000 65535 f \n")
        for offset in offsets[1:]:
            fh.write(f"{offset:010d} 00000 n \n".encode("latin-1"))

        trailer = (
            f"trailer\n<< /Size {len(objects) + 1} /Root {catalog} 0 R >>\n"
            f"startxref\n{xref_pos}\n%%EOF\n"
        )
        fh.write(trailer.encode("latin-1"))


def main() -> None:
    root = Path(__file__).resolve().parent.parent
    source = root / "docs" / "DEPLOIEMENT_PROD_CPANEL.md"
    output = root / "docs" / "DEPLOIEMENT_PROD_CPANEL.pdf"
    build_pdf(source.read_text(encoding="utf-8"), output)
    print(output)


if __name__ == "__main__":
    main()
