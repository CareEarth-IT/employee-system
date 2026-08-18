#!/usr/bin/env python3
"""Patch MAIL_FROM_NAME via Cloud Run v2 REST API (UTF-8 JSON)."""
from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
import urllib.error
import urllib.request

SERVICE = "employee"
REGION = "asia-northeast1"
PROJECT = "ce-gr-employee-info-2606st"
EXPECTED = "CE-Group \u793e\u54e1\u5c02\u7528"
GCLOUD = shutil.which("gcloud") or "gcloud"


def access_token() -> str:
    result = subprocess.run(
        [GCLOUD, "auth", "print-access-token"],
        check=True,
        capture_output=True,
        text=True,
        encoding="utf-8",
        shell=os.name == "nt",
    )
    return result.stdout.strip()


def get_service() -> dict:
    url = (
        f"https://run.googleapis.com/v2/projects/{PROJECT}/locations/{REGION}/services/{SERVICE}"
    )
    req = urllib.request.Request(url, headers={"Authorization": f"Bearer {access_token()}"})
    with urllib.request.urlopen(req) as resp:
        return json.loads(resp.read().decode("utf-8"))


def revision_mail_from_name(revision: str) -> str:
    result = subprocess.run(
        [
            GCLOUD,
            "run",
            "revisions",
            "describe",
            revision,
            f"--region={REGION}",
            f"--project={PROJECT}",
            "--format=json(spec.containers[0].env)",
        ],
        check=True,
        capture_output=True,
        text=True,
        encoding="utf-8",
        shell=os.name == "nt",
    )
    env = json.loads(result.stdout)["spec"]["containers"][0]["env"]
    for item in env:
        if item.get("name") == "MAIL_FROM_NAME" and "value" in item:
            return str(item["value"])
    return ""


def patch_mail_from_name(service: dict) -> None:
    containers = service["template"]["containers"]
    env = containers[0].setdefault("env", [])
    replaced = False
    for item in env:
        if item.get("name") == "MAIL_FROM_NAME":
            item["value"] = EXPECTED
            replaced = True
            break
    if not replaced:
        env.append({"name": "MAIL_FROM_NAME", "value": EXPECTED})


def main() -> int:
    service = get_service()
    revision = service.get("status", {}).get("latestReadyRevision", "").split("/")[-1]
    before = revision_mail_from_name(revision) if revision else ""
    print(f"revision={revision}")
    print(f"before_ok={before == EXPECTED}")
    if before == EXPECTED:
        print("already_ok=true")
        return 0

    patch_mail_from_name(service)
    body = json.dumps(
        {"template": {"containers": service["template"]["containers"]}},
        ensure_ascii=False,
    ).encode("utf-8")

    url = (
        f"https://run.googleapis.com/v2/projects/{PROJECT}/locations/{REGION}/services/{SERVICE}"
        "?updateMask=template.containers"
    )
    req = urllib.request.Request(
        url,
        data=body,
        method="PATCH",
        headers={
            "Authorization": f"Bearer {access_token()}",
            "Content-Type": "application/json; charset=utf-8",
        },
    )
    try:
        with urllib.request.urlopen(req) as resp:
            resp.read()
    except urllib.error.HTTPError as exc:
        print(f"http_error={exc.code}")
        print(exc.read().decode("utf-8", errors="replace")[:500])
        return 1

    updated = get_service()
    new_revision = updated.get("status", {}).get("latestReadyRevision", "").split("/")[-1]
    after = revision_mail_from_name(new_revision) if new_revision else ""
    print(f"new_revision={new_revision}")
    print(f"after_ok={after == EXPECTED}")
    return 0 if after == EXPECTED else 1


if __name__ == "__main__":
    sys.exit(main())
