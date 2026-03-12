#!/usr/bin/env python3

from __future__ import annotations

import json
import sys
from pathlib import Path

try:
    from jsonschema import Draft202012Validator
except ModuleNotFoundError as exc:  # pragma: no cover - environment guard
    print("python jsonschema is required for OpenAPI schema validation", file=sys.stderr)
    raise SystemExit(1) from exc


def main() -> int:
    repo_root = Path(__file__).resolve().parents[2]
    schema = json.loads((repo_root / "scripts/openapi/schema/openapi-3.1.1.schema.json").read_text())
    specification = json.loads((repo_root / "docs/api/openapi/openapi.json").read_text())

    errors = sorted(
        Draft202012Validator(schema).iter_errors(specification),
        key=lambda error: list(error.path),
    )

    if not errors:
        return 0

    print("OpenAPI schema validation failed.", file=sys.stderr)

    for error in errors[:20]:
        location = "/" + "/".join(str(part) for part in error.path)
        print(f"{location or '/'} {error.message}", file=sys.stderr)

    return 1


if __name__ == "__main__":
    raise SystemExit(main())
