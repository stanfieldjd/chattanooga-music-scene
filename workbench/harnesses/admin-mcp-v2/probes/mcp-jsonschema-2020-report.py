#!/usr/bin/env python3
import json
import sys
from pathlib import Path

from jsonschema import Draft202012Validator, SchemaError

if len(sys.argv) != 2:
    raise SystemExit("usage: mcp-jsonschema-2020-report.py REPORT.json")

path = Path(sys.argv[1])
data = json.loads(path.read_text(encoding="utf-8"))

tool_descriptor_schema = {
    "$schema": "https://json-schema.org/draft/2020-12/schema",
    "type": "object",
    "required": [
        "name",
        "title",
        "description",
        "inputSchema",
        "outputSchema",
        "annotations",
        "securitySchemes",
        "_meta",
    ],
    "properties": {
        "name": {"type": "string", "minLength": 1, "pattern": "^[A-Za-z0-9_.-]+$"},
        "title": {"type": "string", "minLength": 1},
        "description": {"type": "string", "minLength": 1},
        "inputSchema": {"type": "object"},
        "outputSchema": {"type": "object"},
        "annotations": {
            "type": "object",
            "required": ["readOnlyHint", "destructiveHint", "openWorldHint"],
            "properties": {
                "readOnlyHint": {"type": "boolean"},
                "destructiveHint": {"type": "boolean"},
                "idempotentHint": {"type": "boolean"},
                "openWorldHint": {"type": "boolean"},
            },
        },
        "securitySchemes": {
            "type": "array",
            "minItems": 1,
            "items": {
                "type": "object",
                "required": ["type"],
                "properties": {
                    "type": {"enum": ["oauth2", "noauth"]},
                    "scopes": {"type": "array", "items": {"type": "string"}},
                },
            },
        },
        "_meta": {
            "type": "object",
            "required": ["securitySchemes"],
            "properties": {
                "securitySchemes": {"type": "array", "minItems": 1},
            },
        },
    },
}

Draft202012Validator.check_schema(tool_descriptor_schema)
validator = Draft202012Validator(tool_descriptor_schema)

tools = list(data.get("tools") or [])
canary = data.get("canaryTool")
if isinstance(canary, dict):
    tools_for_validation = tools + [canary]
else:
    tools_for_validation = tools

results = []
failure_count = 0

for tool in tools_for_validation:
    name = str(tool.get("name", ""))
    issues = []

    for error in sorted(validator.iter_errors(tool), key=lambda item: list(item.absolute_path)):
        location = ".".join(str(part) for part in error.absolute_path)
        issues.append(f"descriptor:{location or '$'}:{error.message}")

    for schema_name in ("inputSchema", "outputSchema"):
        schema = tool.get(schema_name)
        if not isinstance(schema, dict):
            issues.append(f"{schema_name}:not_object")
            continue
        try:
            Draft202012Validator.check_schema(schema)
        except SchemaError as exc:
            issues.append(f"{schema_name}:draft2020-12:{exc.message}")

    if tool.get("securitySchemes") != (tool.get("_meta") or {}).get("securitySchemes"):
        issues.append("securitySchemes:_meta_mismatch")

    for scheme in tool.get("securitySchemes") or []:
        if scheme.get("type") == "oauth2" and "mcp:admin" not in (scheme.get("scopes") or []):
            issues.append("securitySchemes:oauth2_missing_mcp_admin")

    passed = not issues
    if not passed:
        failure_count += 1

    results.append({
        "name": name,
        "pass": passed,
        "issues": issues,
    })

data["jsonSchema202012"] = {
    "validator": "jsonschema.Draft202012Validator",
    "toolCount": len(tools_for_validation),
    "pass": len(tools_for_validation) - failure_count,
    "fail": failure_count,
    "results": results,
}

path.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

if failure_count:
    for result in results:
        if not result["pass"]:
            print(result["name"], *result["issues"], sep="\n  ", file=sys.stderr)
    raise SystemExit(1)

print(
    "cmsa-mcp-jsonschema-2020: PASS "
    f"tools={len(tools_for_validation)} "
    f"pass={len(tools_for_validation)} fail=0"
)
