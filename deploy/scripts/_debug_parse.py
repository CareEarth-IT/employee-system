from pathlib import Path
import re
import importlib.util

raw = Path("database/backups/ceemployee_20261008_122156.sql").read_text(encoding="utf-8")
print("osaka", raw.count("大阪営業部"), "tokyo", raw.count("東京営業部"))

idx = raw.find("INSERT INTO `affiliation_histories` VALUES")
print("idx", idx)
print(raw[idx : idx + 350])

spec = importlib.util.spec_from_file_location(
    "filled", "deploy/scripts/list-filled-locations-from-backup.py"
)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

ah_cols = m.parse_create_columns(raw, "affiliation_histories")
ah_rows = m.extract_values_inserts(raw, "affiliation_histories")
print("cols", ah_cols)
print("n", len(ah_rows), "width", len(ah_rows[0]) if ah_rows else None)
print("row0", ah_rows[0])

# Find rows containing 大阪営業部 anywhere
hits = [r for r in ah_rows if any(isinstance(v, str) and "大阪営業部" in v for v in r)]
print("rows containing osaka dept string", len(hits))
if hits:
    print("hit0", hits[0])
