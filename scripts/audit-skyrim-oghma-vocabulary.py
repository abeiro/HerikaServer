#!/usr/bin/env python3
"""Emit the deterministic Skyrim tag and canonical article-access audits."""

from __future__ import annotations

import argparse
import csv
import json
import re
from collections import Counter
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parents[1]
VOCABULARY = ROOT / "resources" / "oghma" / "canonical-knowledge-vocabulary-v1.json"
ONTOLOGY = ROOT / "resources" / "oghma" / "skyrim-official" / "ontology.json"
BIOGRAPHIES = ROOT / "data" / "bio_templates_20250913.sql"
BIOGRAPHY_EVIDENCE = ROOT / "data" / "canonical_npc_knowledge_tag_evidence.json"
CATALOG_ROOT = ROOT / "resources" / "oghma" / "skyrim-official"


def read_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8-sig"))


def normalized(value: str) -> str:
    return re.sub(r"[^a-z0-9]+", "_", value.casefold()).strip("_")


def split_tags(value: Any) -> list[str]:
    if isinstance(value, list):
        return [str(item).strip() for item in value if str(item).strip()]
    return [item for item in re.split(r"\s*[,;|]\s*", str(value)) if item]


def parse_sql_rows(path: Path) -> list[list[str | None]]:
    text = path.read_text(encoding="utf-8-sig")
    marker = ")VALUES"
    start = text.find(marker)
    if start < 0:
        raise ValueError("Biography seed has no VALUES clause")
    values = text[start + len(marker):]
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
                row = []
                token = []
        elif char == "," and depth == 1:
            raw = "".join(token).strip()
            row.append(None if raw.upper() == "NULL" else raw)
            token = []
        elif char == ")" and depth == 1:
            raw = "".join(token).strip()
            row.append(None if raw.upper() == "NULL" else raw)
            rows.append(row)
            row = []
            token = []
            depth = 0
        elif depth == 1:
            token.append(char)
        index += 1
    if in_string or depth:
        raise ValueError("Biography seed ended inside a tuple or string")
    if any(len(row) != 15 for row in rows):
        raise ValueError("Biography seed tuple column count changed")
    return rows


def vocabulary_sets(vocabulary: dict[str, Any]) -> tuple[set[str], set[str], set[str]]:
    shared = ({value for values in vocabulary["shared"].values() for value in values}
              | set(vocabulary["article_access_markers"]))
    almsivi = {
        value for values in vocabulary["product_specific"]["almsivi"].values() for value in values
    }
    chim = {value for values in vocabulary["product_specific"]["chim"].values() for value in values}
    return shared, almsivi, chim


def availability(
    canonical: list[str], shared: set[str], almsivi: set[str], chim: set[str],
    declared: set[str], local_product: str,
) -> str:
    products: set[str] = set()
    for value in canonical:
        if value in shared:
            products.update(["almsivi", "chim"])
        if value in almsivi:
            products.add("almsivi")
        if value in chim:
            products.add("chim")
        if value in declared and value not in shared | almsivi | chim:
            products.add(local_product)
    if products == {"almsivi", "chim"}:
        return "both"
    return next(iter(products)) if len(products) == 1 else "none"


def classify(
    raw: str,
    vocabulary: dict[str, Any],
    declared: set[str],
    shared: set[str],
    almsivi: set[str],
    chim: set[str],
) -> dict[str, str]:
    key = normalized(raw)
    if key in vocabulary["legacy_aliases"]:
        targets = list(vocabulary["legacy_aliases"][key])
        action = "translate"
    elif key in vocabulary["occupation_translation"]:
        targets = list(vocabulary["occupation_translation"][key])
        action = "keep" if raw == key and targets == [key] else "translate"
    elif key in set(vocabulary["profile_only_occupations"]):
        targets = []
        action = "move"
    elif key in declared:
        targets = [key]
        action = "keep" if raw == key else "translate"
    else:
        targets = []
        action = "remove"
    return {
        "current_tag": raw,
        "canonical_tag": ", ".join(targets),
        "action": action,
        "product_availability": availability(
            targets, shared, almsivi, chim, declared, "chim"
        ),
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--output",
        type=Path,
        default=ROOT / "docs" / "evidence" / "oghma-canonical-vocabulary",
    )
    args = parser.parse_args()
    vocabulary = read_json(VOCABULARY)
    ontology = read_json(ONTOLOGY)
    declared = set(ontology["knowledge_classes"])
    shared, almsivi, chim = vocabulary_sets(vocabulary)
    biography_rows = parse_sql_rows(BIOGRAPHIES)
    biography_evidence = read_json(BIOGRAPHY_EVIDENCE)
    npc_counts: Counter[str] = Counter()
    for item in biography_evidence["items"]:
        npc_counts.update(split_tags(item.get("tags", [])))

    version = (CATALOG_ROOT / "active-catalog-version.txt").read_text(encoding="utf-8").strip()
    articles = read_json(CATALOG_ROOT / "catalogs" / version / "articles.json")
    article_counts: Counter[str] = Counter()
    advanced: dict[str, list[str]] = {}
    basic: dict[str, list[str]] = {}
    tier_errors: list[str] = []
    for article in articles:
        topic = str(article["topic"])
        advanced_values = split_tags(article["knowledge_class"])
        basic_values = split_tags(article["knowledge_class_basic"])
        advanced_markers = sorted(set(advanced_values) & {"common", "esoteric"})
        overlap = sorted(set(advanced_values) & set(basic_values))
        if advanced_markers:
            tier_errors.append(f"{topic}: article marker in advanced tier: {', '.join(advanced_markers)}")
        if overlap:
            tier_errors.append(f"{topic}: knowledge class exists in both tiers: {', '.join(overlap)}")
        for value in advanced_values:
            article_counts[value] += 1
            advanced.setdefault(value, []).append(topic)
        for value in basic_values:
            article_counts[value] += 1
            basic.setdefault(value, []).append(topic)
    if tier_errors:
        raise RuntimeError("Active catalog violates tier-exclusive knowledge classes:\n" + "\n".join(tier_errors))
    audit_rows = []
    for raw in sorted(set(npc_counts) | set(article_counts), key=lambda value: (value.casefold(), value)):
        item = classify(raw, vocabulary, declared, shared, almsivi, chim)
        item["npc_count"] = npc_counts[raw]
        item["article_count"] = article_counts[raw]
        audit_rows.append(item)
    if any(row["action"] in {"translate", "remove"} and row["article_count"] for row in audit_rows):
        raise RuntimeError("Active catalog still contains noncanonical article classes")

    article_markers = set(vocabulary["article_access_markers"]) | set(vocabulary["internal_classes"])
    matrix = [
        {
            "canonical_tag": knowledge_class,
            "expected_level": "advanced" if knowledge_class in advanced else "basic",
            "topic": (advanced.get(knowledge_class) or basic[knowledge_class])[0],
            "advanced_article_count": len(advanced.get(knowledge_class, [])),
            "basic_article_count": len(basic.get(knowledge_class, [])),
        }
        for knowledge_class in sorted((set(advanced) | set(basic)) - article_markers)
    ]
    actions = Counter(row["action"] for row in audit_rows)
    summary = {
        "catalog_version": version,
        "current_tag_rows": len(audit_rows),
        "npc_rows": len(biography_evidence["items"]),
        "npc_assignments": sum(npc_counts.values()),
        "article_assignments": sum(article_counts.values()),
        "actions": dict(sorted(actions.items())),
        "matrix_rows": len(matrix),
    }
    args.output.mkdir(parents=True, exist_ok=True)
    (args.output / "tag-audit.json").write_text(
        json.dumps({"format": "chim.oghma-canonical-tag-audit.v1", "summary": summary, "rows": audit_rows}, indent=2)
        + "\n",
        encoding="utf-8",
        newline="\n",
    )
    with (args.output / "tag-audit.csv").open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=list(audit_rows[0]))
        writer.writeheader()
        writer.writerows(audit_rows)
    (args.output / "access-matrix.json").write_text(
        json.dumps({"format": "chim.oghma-canonical-access-matrix.v1", "rows": matrix}, indent=2) + "\n",
        encoding="utf-8",
        newline="\n",
    )
    lines = [
        "# Skyrim canonical Oghma tag audit",
        "",
        f"- Current vocabulary rows: {summary['current_tag_rows']}",
        f"- Factory NPC rows: {summary['npc_rows']}",
        f"- NPC source assignments: {summary['npc_assignments']}",
        f"- Article class assignments: {summary['article_assignments']}",
        f"- Keep / translate / move / remove: {summary['actions']}",
        f"- Canonical access matrix: {summary['matrix_rows']} rows",
        "",
        "| Current tag | Canonical tag | Action | NPC count | Article count | Product availability |",
        "|---|---|---:|---:|---:|---|",
    ]
    lines.extend(
        f"| {row['current_tag']} | {row['canonical_tag']} | {row['action']} | "
        f"{row['npc_count']} | {row['article_count']} | {row['product_availability']} |"
        for row in audit_rows
    )
    (args.output / "tag-audit.md").write_text("\n".join(lines) + "\n", encoding="utf-8", newline="\n")
    print(json.dumps(summary, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
