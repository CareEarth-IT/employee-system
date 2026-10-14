#!/usr/bin/env python3
"""List rows whose empty location/jurisdiction were filled by staffing normalize."""

from __future__ import annotations

import csv
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BACKUP = ROOT / "database" / "backups" / "ceemployee_20261008_122156.sql"
OUT_CSV = ROOT / "database" / "backups" / "filled_locations_from_staffing_normalize_20261008.csv"

LEGACY_TO_LOCATION = {
    "大阪営業部": "大阪",
    "東京営業部": "東京",
    "名古屋営業部": "名古屋",
    "福岡営業部": "福岡",
}


def split_mysql_tuples(body: str) -> list[str]:
    rows: list[str] = []
    buf: list[str] = []
    in_str = False
    escape = False
    depth = 0
    for ch in body:
        if in_str:
            buf.append(ch)
            if escape:
                escape = False
            elif ch == "\\":
                escape = True
            elif ch == "'":
                in_str = False
            continue
        if ch == "'":
            in_str = True
            buf.append(ch)
            continue
        if ch == "(":
            depth += 1
            if depth == 1:
                buf = []
                continue
        if ch == ")":
            depth -= 1
            if depth == 0:
                rows.append("".join(buf))
                buf = []
                continue
        if depth >= 1:
            buf.append(ch)
    return rows


def parse_values(row: str) -> list[str | None]:
    vals: list[str | None] = []
    buf: list[str] = []
    in_str = False
    escape = False
    i = 0
    while i < len(row):
        ch = row[i]
        if in_str:
            if escape:
                buf.append(ch)
                escape = False
            elif ch == "\\":
                escape = True
            elif ch == "'":
                in_str = False
                vals.append("".join(buf))
                buf = []
            else:
                buf.append(ch)
            i += 1
            continue
        if ch == "'":
            in_str = True
            buf = []
            i += 1
            continue
        if ch == ",":
            # Quoted values are already flushed when the closing quote is seen.
            # Only flush unquoted tokens (e.g. NULL, numbers) here.
            if buf:
                token = "".join(buf).strip()
                vals.append(None if token.upper() == "NULL" else token)
                buf = []
            i += 1
            continue
        buf.append(ch)
        i += 1
    if buf:
        token = "".join(buf).strip()
        vals.append(None if token.upper() == "NULL" else token)
    return vals


def unescape_sql(value: str | None) -> str | None:
    if value is None:
        return None
    return (
        value.replace("\\'", "'")
        .replace('\\"', '"')
        .replace("\\\\", "\\")
        .replace("\\n", "\n")
        .replace("\\r", "\r")
        .replace("\\t", "\t")
    )


def parse_create_columns(sql: str, table: str) -> list[str]:
    match = re.search(
        rf"CREATE TABLE `{re.escape(table)}`\s*\((.*?)\)\s*ENGINE=",
        sql,
        re.IGNORECASE | re.DOTALL,
    )
    if not match:
        raise RuntimeError(f"CREATE TABLE not found: {table}")
    columns: list[str] = []
    for line in match.group(1).splitlines():
        line = line.strip().rstrip(",")
        if not line.startswith("`"):
            continue
        col = line.split("`", 2)[1]
        columns.append(col)
    if not columns:
        raise RuntimeError(f"No columns parsed for {table}")
    return columns


def extract_values_inserts(sql: str, table: str) -> list[list[str | None]]:
    pattern = re.compile(
        rf"INSERT INTO `{re.escape(table)}` VALUES\s*(.+?);",
        re.IGNORECASE | re.DOTALL,
    )
    rows: list[list[str | None]] = []
    for match in pattern.finditer(sql):
        for raw in split_mysql_tuples(match.group(1)):
            parsed = parse_values(raw)
            rows.append([unescape_sql(v) if isinstance(v, str) else v for v in parsed])
    return rows


def infer_location_from_department(department: str | None) -> str | None:
    if not department:
        return None
    tokens = [t.strip() for t in department.split(",") if t.strip()]
    for token in tokens:
        if token in LEGACY_TO_LOCATION:
            return LEGACY_TO_LOCATION[token]
    return None


def is_empty(value: str | None) -> bool:
    return value is None or str(value).strip() == ""


def main() -> int:
    if not BACKUP.exists():
        print(f"Backup not found: {BACKUP}", file=sys.stderr)
        return 1

    sql = BACKUP.read_text(encoding="utf-8")

    ah_cols = parse_create_columns(sql, "affiliation_histories")
    hd_cols = parse_create_columns(sql, "employee_hr_details")
    user_cols = parse_create_columns(sql, "users")

    ah_rows = extract_values_inserts(sql, "affiliation_histories")
    hd_rows = extract_values_inserts(sql, "employee_hr_details")
    user_rows = extract_values_inserts(sql, "users")

    users = {str(row[user_cols.index("id")]): row for row in user_rows}

    def user_label(user_id: str | None) -> dict[str, str]:
        row = users.get(str(user_id or ""))
        if not row:
            return {"user_id": str(user_id or ""), "employee_id": "", "email": "", "name": ""}
        last = row[user_cols.index("last_name")] or ""
        first = row[user_cols.index("first_name")] or ""
        name = (row[user_cols.index("name")] or "").strip()
        display = f"{last} {first}".strip() or name
        return {
            "user_id": str(user_id or ""),
            "employee_id": str(row[user_cols.index("employee_id")] or ""),
            "email": str(row[user_cols.index("email")] or ""),
            "name": display,
        }

    affiliation_hits: list[dict[str, str]] = []
    for row in ah_rows:
        if len(row) < len(ah_cols):
            continue
        department = row[ah_cols.index("department")]
        location = row[ah_cols.index("location")]
        filled = infer_location_from_department(department)
        if not filled or not is_empty(location):
            continue
        info = user_label(row[ah_cols.index("user_id")])
        affiliation_hits.append(
            {
                **info,
                "source": "affiliation_histories",
                "record_id": str(row[ah_cols.index("id")] or ""),
                "before_department": str(department or ""),
                "before_location_or_jurisdiction": "",
                "filled_location": filled,
                "enrollment_status": str(row[ah_cols.index("enrollment_status")] or ""),
                "start_date": str(row[ah_cols.index("start_date")] or ""),
                "end_date": str(row[ah_cols.index("end_date")] or ""),
            }
        )

    hr_hits: list[dict[str, str]] = []
    for row in hd_rows:
        if len(row) < len(hd_cols):
            continue
        department = row[hd_cols.index("department_primary")]
        jurisdiction = row[hd_cols.index("jurisdiction")]
        filled = infer_location_from_department(department)
        if not filled or not is_empty(jurisdiction):
            continue
        info = user_label(row[hd_cols.index("user_id")])
        hr_hits.append(
            {
                **info,
                "source": "employee_hr_details",
                "record_id": str(row[hd_cols.index("id")] or ""),
                "before_department": str(department or ""),
                "before_location_or_jurisdiction": "",
                "filled_location": filled,
                "enrollment_status": "",
                "start_date": "",
                "end_date": "",
            }
        )

    all_hits = affiliation_hits + hr_hits
    fields = [
        "source",
        "record_id",
        "user_id",
        "employee_id",
        "name",
        "email",
        "before_department",
        "before_location_or_jurisdiction",
        "filled_location",
        "enrollment_status",
        "start_date",
        "end_date",
    ]
    with OUT_CSV.open("w", encoding="utf-8-sig", newline="") as fh:
        writer = csv.DictWriter(fh, fieldnames=fields)
        writer.writeheader()
        writer.writerows(all_hits)

    print(f"affiliation_histories filled: {len(affiliation_hits)}")
    print(f"employee_hr_details filled: {len(hr_hits)}")
    print(f"total: {len(all_hits)}")
    print(f"csv: {OUT_CSV}")

    summary: dict[str, int] = {}
    for hit in all_hits:
        key = f"{hit['source']} / {hit['filled_location']}"
        summary[key] = summary.get(key, 0) + 1
    for key, count in sorted(summary.items()):
        print(f"  {key}: {count}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
