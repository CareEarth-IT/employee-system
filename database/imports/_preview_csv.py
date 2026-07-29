import csv
from pathlib import Path

p = Path(r"c:\xampp\htdocs\employee\database\imports\employees-add-20260716.csv")
with p.open(encoding="utf-8-sig", newline="") as f:
    rows = list(csv.DictReader(f))

preview = Path(r"c:\xampp\htdocs\employee\database\imports\_preview.txt")
lines = [f"count={len(rows)}"]
for r in rows:
    lines.append(
        f"{r['email']}|{r['姓']}|{r['名']}|{r['社員番号']}|{r['部']}|{r['課']}|{r['役職']}|{r['拠点']}|{r['会社']}"
    )
preview.write_text("\n".join(lines) + "\n", encoding="utf-8")
print(preview.read_text(encoding="utf-8"))
