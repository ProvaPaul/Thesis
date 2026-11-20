"""
Draws a Data-in-Brief-style running header banner (logo.png) for main.tex,
in the spirit of the journal's own article template header. This is an
original recreation (colours + layout only) -- it does not reproduce
Elsevier's actual trademarked logo artwork, which is not available in any
local file and was not downloaded from anywhere.
"""
import os
from PIL import Image, ImageDraw, ImageFont

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
FONT_DIR = "C:/Windows/Fonts"

W, H = 2400, 300
RED_DARK = (150, 20, 28)
RED_LIGHT = (196, 30, 40)
WHITE = (255, 255, 255)

img = Image.new("RGB", (W, H), RED_LIGHT)
draw = ImageDraw.Draw(img)

# horizontal gradient, dark -> light, left to right
for x in range(W):
    t = x / W
    r = int(RED_DARK[0] + (RED_LIGHT[0] - RED_DARK[0]) * t)
    g = int(RED_DARK[1] + (RED_LIGHT[1] - RED_DARK[1]) * t)
    b = int(RED_DARK[2] + (RED_LIGHT[2] - RED_DARK[2]) * t)
    draw.line([(x, 0), (x, H)], fill=(r, g, b))

title_font = ImageFont.truetype(os.path.join(FONT_DIR, "timesi.ttf"), 108)
sub_font = ImageFont.truetype(os.path.join(FONT_DIR, "ariali.ttf"), 46)
right_font = ImageFont.truetype(os.path.join(FONT_DIR, "arial.ttf"), 52)
mono_font = ImageFont.truetype(os.path.join(FONT_DIR, "arialbd.ttf"), 40)

# small stacked-pages icon, top-left, above "Data in Brief"
icon_x, icon_y = 70, 40
for i, dx in enumerate([0, 14, 28]):
    shade = 255 - i * 35
    draw.rectangle(
        [icon_x + dx, icon_y + dx, icon_x + dx + 70, icon_y + dx + 90],
        outline=(shade, shade, shade), width=4,
    )

text_x = 210
draw.text((text_x, 60), "Data in Brief", font=title_font, fill=WHITE)
draw.text((text_x + 4, 190), "Open access", font=sub_font, fill=(255, 220, 220))

right_text = "Article Template"
bbox = draw.textbbox((0, 0), right_text, font=right_font)
rw = bbox[2] - bbox[0]
draw.text((W - rw - 90, 110), right_text, font=right_font, fill=WHITE)

# small neutral monogram mark, top-right corner (generic, not a trademark)
mx, my, ms = W - 90, 20, 60
draw.rounded_rectangle([mx - ms, my, mx, my + ms], radius=8, fill=WHITE)
draw.text((mx - ms + 12, my + 6), "D", font=mono_font, fill=RED_DARK)

# thin bottom rule
draw.rectangle([0, H - 6, W, H], fill=RED_DARK)

img.save(os.path.join(BASE_DIR, "logo.png"))
print("OK -- wrote logo.png", img.size)
