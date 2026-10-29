#!/usr/bin/env python3
"""Write a flat, shields.io-style SVG badge to stdout.

Usage: badge.py <label> <message> [color]
If color is omitted and message is a percentage, the color is picked from it.
"""
import sys
from html import escape

COLORS = {
    "brightgreen": "#4c1", "green": "#97ca00", "yellowgreen": "#a4a61d",
    "yellow": "#dfb317", "orange": "#fe7d37", "red": "#e05d44", "blue": "#007ec6",
}


def color_for(message: str) -> str:
    try:
        pct = float(message.rstrip("%"))
    except ValueError:
        return "blue"
    for limit, name in ((90, "brightgreen"), (80, "green"), (70, "yellowgreen"), (60, "yellow"), (50, "orange")):
        if pct >= limit:
            return name
    return "red"


def width(text: str) -> int:
    return int(len(text) * 6.5) + 10


def main() -> None:
    label, message = sys.argv[1], sys.argv[2]
    color = COLORS.get(sys.argv[3] if len(sys.argv) > 3 else color_for(message), "#007ec6")
    lw, mw = width(label), width(message)
    total = lw + mw
    label_e, message_e = escape(label), escape(message)
    print(f"""<svg xmlns="http://www.w3.org/2000/svg" width="{total}" height="20" role="img" aria-label="{label_e}: {message_e}">
<title>{label_e}: {message_e}</title>
<linearGradient id="s" x2="0" y2="100%"><stop offset="0" stop-color="#bbb" stop-opacity=".1"/><stop offset="1" stop-opacity=".1"/></linearGradient>
<clipPath id="r"><rect width="{total}" height="20" rx="3" fill="#fff"/></clipPath>
<g clip-path="url(#r)"><rect width="{lw}" height="20" fill="#555"/><rect x="{lw}" width="{mw}" height="20" fill="{color}"/><rect width="{total}" height="20" fill="url(#s)"/></g>
<g fill="#fff" text-anchor="middle" font-family="Verdana,Geneva,DejaVu Sans,sans-serif" font-size="11">
<text x="{lw / 2}" y="15" fill="#010101" fill-opacity=".3">{label_e}</text><text x="{lw / 2}" y="14">{label_e}</text>
<text x="{lw + mw / 2}" y="15" fill="#010101" fill-opacity=".3">{message_e}</text><text x="{lw + mw / 2}" y="14">{message_e}</text>
</g>
</svg>""")


if __name__ == "__main__":
    main()
