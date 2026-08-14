#!/usr/bin/env python3
"""Classify Skyrim factory biographies against the frozen Oghma vocabulary."""

from __future__ import annotations

import argparse
import concurrent.futures
import hashlib
import json
import os
import re
import statistics
import time
from collections import Counter
from pathlib import Path
from typing import Any

import requests


ROOT = Path(__file__).resolve().parents[1]
VOCABULARY_PATH = ROOT / "resources" / "oghma" / "canonical-knowledge-vocabulary-v1.json"
ONTOLOGY_PATH = ROOT / "resources" / "oghma" / "skyrim-official" / "ontology.json"
BIOGRAPHIES_PATH = ROOT / "data" / "bio_templates_20250913.sql"
MIGRATION_PATH = ROOT / "data" / "canonical_npc_knowledge_tags_20260814_v3.sql"
EVIDENCE_PATH = ROOT / "data" / "canonical_npc_knowledge_tag_evidence.json"
REPORT_PATH = ROOT / "docs" / "evidence" / "oghma-canonical-vocabulary" / "biography-classification-manifest.json"
OPENROUTER_URL = "https://openrouter.ai/api/v1/chat/completions"
MODEL = "z-ai/glm-5.1"


def read_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8-sig"))


def write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + "\n", encoding="utf-8", newline="\n")


def normalized(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "_", value.casefold()).strip("_")


def compact(value: str) -> str:
    return re.sub(r"\s+", " ", value).strip().casefold()


def evidence_signals(vocabulary: dict[str, Any]) -> dict[str, set[str]]:
    tags = {
        value for values in vocabulary["shared"].values() for value in values
    } | {
        value for values in vocabulary["product_specific"]["chim"].values() for value in values
    }
    signals = {tag: {tag.replace("_", " ")} for tag in tags}
    for alias, targets in vocabulary["legacy_aliases"].items():
        for target in targets:
            if target in signals:
                signals[target].add(alias.replace("_", " "))
    for occupation, targets in vocabulary["occupation_translation"].items():
        for target in targets:
            if target in signals:
                signals[target].add(occupation.replace("_", " "))
    location_signals = {
        "eastmarch": ["eastmarch", "windhelm", "kynesgrove", "darkwater crossing"],
        "falkreath": ["falkreath", "helgen"],
        "haafingar": ["haafingar", "solitude", "dragon bridge"],
        "hjaalmarch": ["hjaalmarch", "morthal"],
        "pale": ["the pale", "dawnstar"],
        "reach": ["the reach", "markarth", "karthwasten"],
        "rift": ["the rift", "riften", "ivarstead", "shor's stone"],
        "solstheim": ["solstheim", "raven rock", "skaal village", "tel mithryn"],
        "whiterun": ["whiterun", "riverwood", "rorikstead", "dragon bridge"],
        "winterhold": ["winterhold"],
    }
    for tag, values in location_signals.items():
        signals.setdefault(tag, set()).update(values)
    signals["scholar"].update(["research", "researcher", "historian", "archaeolog", "academic", "scholarly"])
    return signals


def parse_sql_rows(path: Path) -> list[list[str | None]]:
    text = path.read_text(encoding="utf-8-sig")
    start = text.find(")VALUES")
    if start < 0:
        raise ValueError("Biography seed has no VALUES clause")
    values = text[start + len(")VALUES"):]
    rows: list[list[str | None]] = []
    row: list[str | None] = []
    token: list[str] = []
    in_string = False
    depth = 0
    index = 0
    while index < len(values):
        char = values[index]
        if in_string:
            if char == "'" and index + 1 < len(values) and values[index + 1] == "'":
                token.append("'")
                index += 2
                continue
            if char == "'":
                in_string = False
            else:
                token.append(char)
            index += 1
            continue
        if char == "'":
            in_string = True
        elif char == "(":
            depth += 1
            if depth == 1:
                row, token = [], []
        elif char == "," and depth == 1:
            raw = "".join(token).strip()
            row.append(None if raw.upper() == "NULL" else raw)
            token = []
        elif char == ")" and depth == 1:
            raw = "".join(token).strip()
            row.append(None if raw.upper() == "NULL" else raw)
            rows.append(row)
            row, token, depth = [], [], 0
        elif depth == 1:
            token.append(char)
        index += 1
    if in_string or depth or any(len(row) != 15 for row in rows):
        raise ValueError("Biography seed tuple structure changed")
    return rows


def schema(allowed: list[str], batch_size: int) -> dict[str, Any]:
    return {
        "type": "object",
        "additionalProperties": False,
        "properties": {
            "items": {
                "type": "array",
                "minItems": batch_size,
                "maxItems": batch_size,
                "items": {
                    "type": "object",
                    "additionalProperties": False,
                    "properties": {
                        "claims": {
                            "type": "array",
                            "maxItems": 8,
                            "items": {
                                "type": "object",
                                "additionalProperties": False,
                                "properties": {
                                    "tag": {"type": "string", "enum": allowed},
                                    "evidence": {"type": "string", "minLength": 4, "maxLength": 160},
                                    "confidence": {"type": "string", "enum": ["high", "medium"]},
                                },
                                "required": ["tag", "evidence", "confidence"],
                            },
                        },
                    },
                    "required": ["claims"],
                },
            }
        },
        "required": ["items"],
    }


def classify_batch(
    batch: list[dict[str, str]], api_key: str, allowed: list[str], timeout: float
) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    system = (
        "Audit Skyrim biographies for Oghma knowledge permissions. A permission is not a general "
        "biography descriptor. Use only the allowed tags. Do not tag farmer, miner, peasant, child, "
        "servant, prisoner, lumberjack, or animal. Scholar requires explicit study or scholarly duties. "
        "Organizations require membership or durable service. Regions must be current or former stable "
        "residences, never transient quest destinations. Quote an exact short evidence substring from "
        "the supplied fields. Return no claim when uncertain. Mages Guild and College of Winterhold are "
        "different organizations. Return exactly {\"items\":[{\"claims\":[]}]} with one item per "
        "input row in the same order."
        " Allowed tags: " + ", ".join(allowed) + "."
    )
    payload_rows = [
        {
            "id": index,
            "name": row["id"],
            "core": row["core"],
            "biography": row["biography"],
            "occupation": row["occupation"],
            "skills": row["skills"],
        }
        for index, row in enumerate(batch)
    ]
    started = time.monotonic()
    last_error: Exception | None = None
    for attempt in range(4):
        try:
            response = requests.post(
                OPENROUTER_URL,
                headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
                json={
                    "model": MODEL,
                    "temperature": 0.0,
                    "max_tokens": 5000,
                    "reasoning": {"effort": "none"},
                    "messages": [
                        {"role": "system", "content": system},
                        {"role": "user", "content": json.dumps(payload_rows, ensure_ascii=False)},
                    ],
                    "response_format": {
                        "type": "json_schema",
                        "json_schema": {
                            "name": "skyrim_oghma_biography_tags",
                            "strict": True,
                            "schema": schema(allowed, len(batch)),
                        },
                    } if attempt < 2 else {"type": "json_object"},
                },
                timeout=timeout,
            )
            if response.status_code == 429 or response.status_code >= 500:
                raise requests.HTTPError(f"OpenRouter HTTP {response.status_code}", response=response)
            response.raise_for_status()
            payload = response.json()
            content = payload["choices"][0]["message"]["content"]
            if not isinstance(content, str) or not content.strip():
                raise ValueError("OpenRouter returned an empty structured response")
            result = json.loads(content)
            response_items = list(result["items"])
            if len(response_items) != len(batch):
                raise ValueError("OpenRouter changed structured row count")
            items = [
                {"id": batch[index]["id"], "claims": item["claims"]}
                for index, item in enumerate(response_items)
            ]
            usage = payload.get("usage") or {}
            return items, {
                "prompt_tokens": int(usage.get("prompt_tokens") or 0),
                "completion_tokens": int(usage.get("completion_tokens") or 0),
                "total_tokens": int(usage.get("total_tokens") or 0),
                "cost": float(usage.get("cost") or 0.0),
                "seconds": time.monotonic() - started,
                "provider": payload.get("provider"),
            }
        except (requests.RequestException, KeyError, TypeError, ValueError, json.JSONDecodeError) as exc:
            last_error = exc
            if attempt < 3:
                time.sleep(2**attempt)
    # Isolate provider-sensitive biographies without discarding a completed surrounding batch.
    if len(batch) > 1:
        midpoint = len(batch) // 2
        left_items, left_usage = classify_batch(batch[:midpoint], api_key, allowed, timeout)
        right_items, right_usage = classify_batch(batch[midpoint:], api_key, allowed, timeout)
        return left_items + right_items, {
            "prompt_tokens": left_usage["prompt_tokens"] + right_usage["prompt_tokens"],
            "completion_tokens": left_usage["completion_tokens"] + right_usage["completion_tokens"],
            "total_tokens": left_usage["total_tokens"] + right_usage["total_tokens"],
            "cost": left_usage["cost"] + right_usage["cost"],
            "seconds": left_usage["seconds"] + right_usage["seconds"],
            "provider": "split-batch",
        }
    return [{"id": batch[0]["id"], "claims": []}], {
        "prompt_tokens": 0,
        "completion_tokens": 0,
        "total_tokens": 0,
        "cost": 0.0,
        "seconds": time.monotonic() - started,
        "provider": "provider_failed_abstention",
    }


def sql_literal(value: str) -> str:
    return "'" + value.replace("'", "''") + "'"


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--apply", action="store_true")
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--batch-size", type=int, default=20)
    parser.add_argument("--workers", type=int, default=6)
    parser.add_argument("--timeout", type=float, default=120.0)
    parser.add_argument("--max-cost", type=float, default=8.0)
    parser.add_argument("--api-key-env", default="OPENROUTER_API_KEY")
    parser.add_argument("--checkpoint", type=Path, default=ROOT / "build" / "skyrim-biography-tags.json")
    parser.add_argument("--reuse-evidence", action="store_true")
    args = parser.parse_args()
    api_key = os.getenv(args.api_key_env, "").strip()
    vocabulary = read_json(VOCABULARY_PATH)
    ontology = read_json(ONTOLOGY_PATH)
    source_rows = parse_sql_rows(BIOGRAPHIES_PATH)
    if len({str(row[0]).casefold() for row in source_rows}) != len(source_rows):
        raise RuntimeError("Factory biography names are not unique")
    rows = [
        {
            "id": str(row[0]),
            "core": str(row[2] or ""),
            "biography": str(row[3] or ""),
            "occupation": str(row[7] or ""),
            "skills": str(row[8] or ""),
            "race": str(row[13] or ""),
            "current_tags": str(row[1] or ""),
        }
        for row in source_rows
    ]
    if args.limit > 0:
        rows = rows[:args.limit]
    allowed_ontology = set(ontology["knowledge_classes"])
    glm_allowed = sorted(
        (
            {value for values in vocabulary["shared"].values() for value in values}
            | {
                value
                for values in vocabulary["product_specific"]["chim"].values()
                for value in values
            }
        )
        - {"common", "esoteric"}
    )
    signals = evidence_signals(vocabulary)
    vocabulary_sha = hashlib.sha256(VOCABULARY_PATH.read_bytes()).hexdigest()
    reused_report: dict[str, Any] = {}
    if args.reuse_evidence:
        evidence = read_json(EVIDENCE_PATH)
        evidence_items = list(evidence.get("items", []))
        if {str(item.get("npc_name", "")) for item in evidence_items} != {row["id"] for row in rows}:
            raise RuntimeError("Existing evidence does not match the frozen biographies and vocabulary")
        checkpoint = {
            "format": "chim.skyrim-biography-tags-checkpoint.v1",
            "model": MODEL,
            "vocabulary_sha256": vocabulary_sha,
            "items": {str(item["npc_name"]): list(item.get("glm_claims", [])) for item in evidence_items},
            "usage": [],
        }
        reused_report = read_json(REPORT_PATH)
    else:
        checkpoint = read_json(args.checkpoint) if args.checkpoint.exists() else {
            "format": "chim.skyrim-biography-tags-checkpoint.v1",
            "model": MODEL,
            "vocabulary_sha256": vocabulary_sha,
            "items": {},
            "usage": [],
        }
    if checkpoint["vocabulary_sha256"] != vocabulary_sha:
        raise RuntimeError("Checkpoint vocabulary does not match the frozen vocabulary")
    pending = [row for row in rows if row["id"] not in checkpoint["items"]]
    if pending and not api_key:
        raise RuntimeError(f"Missing {args.api_key_env}")
    batches = [pending[index:index + args.batch_size] for index in range(0, len(pending), args.batch_size)]
    spent = sum(float(item.get("cost", 0.0)) for item in checkpoint["usage"])
    for start in range(0, len(batches), args.workers):
        if spent >= args.max_cost - 1.0:
            raise RuntimeError(f"Stopped before reserve: spent USD {spent:.4f} of USD {args.max_cost:.2f}")
        wave = batches[start:start + args.workers]
        with concurrent.futures.ThreadPoolExecutor(max_workers=len(wave)) as executor:
            futures = [
                executor.submit(classify_batch, batch, api_key, glm_allowed, args.timeout)
                for batch in wave
            ]
            for batch, future in zip(wave, futures):
                items, usage = future.result()
                expected = {row["id"] for row in batch}
                actual = {str(item.get("id", "")) for item in items}
                if actual != expected:
                    raise RuntimeError(f"GLM batch IDs changed: expected {expected - actual}, extra {actual - expected}")
                for item in items:
                    checkpoint["items"][item["id"]] = item["claims"]
                checkpoint["usage"].append(usage)
                spent += usage["cost"]
        write_json(args.checkpoint, checkpoint)
        print(f"completed={len(checkpoint['items'])}/{len(rows)} spent_usd={spent:.4f}", flush=True)
    if any(row["id"] not in checkpoint["items"] for row in rows):
        raise RuntimeError("Classification incomplete")

    evidence_rows = []
    final_tags: dict[str, list[str]] = {}
    tag_counts: Counter[str] = Counter()
    region_policy_counts: Counter[str] = Counter()
    region_tags = set(vocabulary["product_specific"]["chim"]["regions"])
    rejected = 0
    for row in rows:
        tags = []
        race_key = normalized(row["race"])
        for target in vocabulary["legacy_aliases"].get(race_key, [race_key] if race_key else []):
            if target in allowed_ontology and target not in tags:
                tags.append(target)
        race_tags = set(vocabulary["shared"]["races"])
        for current in re.split(r"\s*[,;|]\s*", row["current_tags"]):
            current_key = normalized(current)
            for target in vocabulary["legacy_aliases"].get(current_key, [current_key] if current_key else []):
                if target in race_tags and target in allowed_ontology and target not in tags:
                    tags.append(target)
        source = compact(" ".join([row["core"], row["biography"], row["occupation"], row["skills"]]))
        accepted = []
        claims = checkpoint["items"][row["id"]]
        if not isinstance(claims, list):
            rejected += 1
            claims = []
        for claim in claims:
            if not isinstance(claim, dict) or not {"tag", "evidence", "confidence"} <= set(claim):
                rejected += 1
                continue
            tag = str(claim["tag"])
            evidence = compact(str(claim["evidence"]))
            supported = any(signal in evidence for signal in signals.get(tag, set()))
            if tag not in glm_allowed or evidence not in source or not supported:
                rejected += 1
                continue
            if tag in allowed_ontology and tag not in tags:
                tags.append(tag)
            accepted.append(claim)
        legacy_regions: list[str] = []
        for current in re.split(r"\s*[,;|]\s*", row["current_tags"]):
            current_key = normalized(current)
            for target in vocabulary["legacy_aliases"].get(current_key, [current_key] if current_key else []):
                if target in region_tags and target not in legacy_regions:
                    legacy_regions.append(target)
        glm_regions = [tag for tag in tags if tag in region_tags]
        if not legacy_regions:
            region_policy = "glm_only" if glm_regions else "none"
        elif not glm_regions:
            region_policy = "legacy_fallback"
            tags.extend(tag for tag in legacy_regions if tag not in tags)
        elif set(legacy_regions) & set(glm_regions):
            region_policy = "legacy_plus_glm"
            tags.extend(tag for tag in legacy_regions if tag not in tags)
        else:
            region_policy = "glm_replacement"
        final_regions = [tag for tag in tags if tag in region_tags]
        region_policy_counts[region_policy] += 1
        final_tags[row["id"]] = tags
        tag_counts.update(tags)
        evidence_rows.append({
            "npc_name": row["id"], "tags": tags, "glm_claims": accepted,
            "region_decision": {
                "policy": region_policy,
                "legacy_regions": legacy_regions,
                "glm_regions": glm_regions,
                "final_regions": final_regions,
            },
        })

    usage = checkpoint["usage"]
    usage_report = reused_report.get("usage") if args.reuse_evidence else {
        "calls": len(usage),
        "prompt_tokens": sum(item["prompt_tokens"] for item in usage),
        "completion_tokens": sum(item["completion_tokens"] for item in usage),
        "total_tokens": sum(item["total_tokens"] for item in usage),
        "cost": sum(item["cost"] for item in usage),
        "request_seconds_median": statistics.median(item["seconds"] for item in usage) if usage else 0,
        "provider_failed_abstentions": sum(
            item.get("provider") == "provider_failed_abstention" for item in usage
        ),
    }
    report = {
        "format": "chim.skyrim-biography-knowledge-classification.v2",
        "model": MODEL,
        "vocabulary_sha256": vocabulary_sha,
        "biography_count": len(rows),
        "glm_completed_count": len(rows),
        "rejected_claim_count": int(reused_report.get("rejected_claim_count", 0)) + rejected,
        "tag_counts": dict(sorted(tag_counts.items())),
        "region_policy": {
            "description": "Keep evidence-validated GLM corrections; otherwise preserve legacy biography regions.",
            "counts": dict(sorted(region_policy_counts.items())),
        },
        "usage": usage_report,
    }
    if args.reuse_evidence:
        report["classification_reused_from_evidence_sha256"] = hashlib.sha256(EVIDENCE_PATH.read_bytes()).hexdigest()
    if args.apply:
        values = ",\n".join(
            f"    ({sql_literal(name)}, {sql_literal(', '.join(tags))})"
            for name, tags in sorted(final_tags.items(), key=lambda item: item[0].casefold())
        )
        migration = (
            "-- Canonical factory NPC Oghma tags generated from the frozen shared vocabulary.\n"
            "BEGIN;\n"
            "CREATE TEMP TABLE canonical_npc_knowledge_tags(npc_name text PRIMARY KEY, tags text) ON COMMIT DROP;\n"
            "INSERT INTO canonical_npc_knowledge_tags(npc_name, tags) VALUES\n"
            + values
            + ";\n"
            "UPDATE public.bio_templates AS bio\n"
            "   SET oghma_knowledge_tags = canonical.tags\n"
            "  FROM canonical_npc_knowledge_tags AS canonical\n"
            " WHERE lower(bio.npc_name) = lower(canonical.npc_name);\n"
            "CREATE OR REPLACE FUNCTION pg_temp.chim_merge_canonical_regions(current_tags text, canonical_tags text)\n"
            "RETURNS text LANGUAGE sql IMMUTABLE AS $$\n"
            "    WITH region_tags(tag) AS (VALUES\n"
            "        ('eastmarch'),('falkreath'),('haafingar'),('hjaalmarch'),('pale'),\n"
            "        ('reach'),('rift'),('solstheim'),('whiterun'),('winterhold')\n"
            "    ), candidates AS (\n"
            "        SELECT lower(btrim(value)) AS tag, 0 AS source_order, ordinal\n"
            "          FROM regexp_split_to_table(COALESCE(current_tags, ''), '\\s*,\\s*') WITH ORDINALITY AS item(value, ordinal)\n"
            "         WHERE btrim(value) <> '' AND lower(btrim(value)) NOT IN (SELECT tag FROM region_tags)\n"
            "           AND lower(btrim(value)) NOT IN ('common', 'esoteric', 'skyrimall')\n"
            "        UNION ALL\n"
            "        SELECT lower(btrim(value)) AS tag, 1 AS source_order, ordinal\n"
            "          FROM regexp_split_to_table(COALESCE(canonical_tags, ''), '\\s*,\\s*') WITH ORDINALITY AS item(value, ordinal)\n"
            "         WHERE lower(btrim(value)) IN (SELECT tag FROM region_tags)\n"
            "    ), deduplicated AS (\n"
            "        SELECT tag, min(source_order) AS source_order, min(ordinal) AS ordinal\n"
            "          FROM candidates GROUP BY tag\n"
            "    )\n"
            "    SELECT COALESCE(string_agg(tag, ', ' ORDER BY source_order, ordinal), '') FROM deduplicated;\n"
            "$$;\n"
            "UPDATE public.core_npc_master AS master\n"
            "   SET oghma_knowledge_tags = pg_temp.chim_merge_canonical_regions(master.oghma_knowledge_tags, canonical.tags)\n"
            "  FROM canonical_npc_knowledge_tags AS canonical\n"
            " WHERE canonical.npc_name = trim(both '_' from lower(regexp_replace(master.npc_name, '[^A-Za-z0-9]+', '_', 'g')));\n"
            "CREATE OR REPLACE FUNCTION pg_temp.chim_strip_article_markers(current_tags text)\n"
            "RETURNS text LANGUAGE sql IMMUTABLE AS $$\n"
            "    SELECT COALESCE(string_agg(tag, ', ' ORDER BY ordinal), '')\n"
            "      FROM (\n"
            "        SELECT lower(btrim(value)) AS tag, min(ordinal) AS ordinal\n"
            "          FROM regexp_split_to_table(COALESCE(current_tags, ''), '\\s*[,|;]\\s*') WITH ORDINALITY AS item(value, ordinal)\n"
            "         WHERE btrim(value) <> '' AND lower(btrim(value)) NOT IN ('common', 'esoteric', 'skyrimall')\n"
            "         GROUP BY lower(btrim(value))\n"
            "      ) AS cleaned;\n"
            "$$;\n"
            "UPDATE public.bio_templates\n"
            "   SET oghma_knowledge_tags = pg_temp.chim_strip_article_markers(oghma_knowledge_tags);\n"
            "UPDATE public.bio_templates_custom\n"
            "   SET oghma_knowledge_tags = pg_temp.chim_strip_article_markers(oghma_knowledge_tags);\n"
            "UPDATE public.core_npc_master\n"
            "   SET oghma_knowledge_tags = pg_temp.chim_strip_article_markers(oghma_knowledge_tags);\n"
            "COMMIT;\n"
        )
        MIGRATION_PATH.write_text(migration, encoding="utf-8", newline="\n")
        write_json(EVIDENCE_PATH, {
            "format": "chim.skyrim-biography-knowledge-evidence.v2",
            "vocabulary_sha256": vocabulary_sha,
            "items": evidence_rows,
        })
        report["migration_sha256"] = hashlib.sha256(MIGRATION_PATH.read_bytes()).hexdigest()
        report["evidence_sha256"] = hashlib.sha256(EVIDENCE_PATH.read_bytes()).hexdigest()
        write_json(REPORT_PATH, report)
    print(json.dumps(report, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
