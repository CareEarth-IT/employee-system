import importlib.util
from collections import Counter
from pathlib import Path

spec = importlib.util.spec_from_file_location(
    "filled", "deploy/scripts/list-filled-locations-from-backup.py"
)
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)

sql = Path("database/backups/ceemployee_20261008_122156.sql").read_text(encoding="utf-8")

for table, dept_col, loc_col in [
    ("affiliation_histories", "department", "location"),
    ("employee_hr_details", "department_primary", "jurisdiction"),
]:
    cols = m.parse_create_columns(sql, table)
    rows = m.extract_values_inserts(sql, table)
    legacy = Counter()
    empty = Counter()
    for row in rows:
        if len(row) < len(cols):
            continue
        dept = row[cols.index(dept_col)]
        loc = row[cols.index(loc_col)]
        filled = m.infer_location_from_department(dept)
        if not filled:
            continue
        legacy[dept] += 1
        if m.is_empty(loc):
            empty[f"{dept} -> {filled}"] += 1
    print("===", table)
    print("legacy departments:", dict(legacy))
    print("empty location/jurisdiction:", dict(empty))
    print("empty total:", sum(empty.values()))
