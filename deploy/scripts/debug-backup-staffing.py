from pathlib import Path
import re
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))
from list_filled_locations_from_backup import (  # type: ignore
    BACKUP,
    extract_values_inserts,
    parse_create_columns,
    infer_location_from_department,
    is_empty,
)

sql = BACKUP.read_text(encoding="utf-8")
ah_cols = parse_create_columns(sql, "affiliation_histories")
ah_rows = extract_values_inserts(sql, "affiliation_histories")
print("ah cols", ah_cols)
print("ah rows", len(ah_rows))
print("sample row len", len(ah_rows[0]) if ah_rows else None)
print("sample", ah_rows[0] if ah_rows else None)

dept_i = ah_cols.index("department")
loc_i = ah_cols.index("location")
legacy = 0
empty_loc = 0
for row in ah_rows:
    if len(row) <= max(dept_i, loc_i):
        continue
    d = row[dept_i]
    loc = row[loc_i]
    if d in ("大阪営業部", "東京営業部", "名古屋営業部", "福岡営業部") or (
        d and any(x in d for x in ("大阪営業部", "東京営業部", "名古屋営業部", "福岡営業部"))
    ):
        legacy += 1
        if is_empty(loc):
            empty_loc += 1
            print("EMPTY", d, repr(loc), row[ah_cols.index("id")], row[ah_cols.index("user_id")])
print("legacy-ish", legacy, "empty loc", empty_loc)

# show a few legacy with location
shown = 0
for row in ah_rows:
    d = row[dept_i] if len(row) > dept_i else None
    if d == "大阪営業部":
        print("osaka sample", repr(row[loc_i]), row[ah_cols.index("id")])
        shown += 1
        if shown >= 5:
            break
