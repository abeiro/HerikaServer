#!/usr/bin/env python3
"""Generate conservative Oghma aliases with an OpenRouter-hosted GLM model."""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path
from typing import Any

DEFAULT_MODEL = "z-ai/glm-5.2"
DEFAULT_ENDPOINT = "https://openrouter.ai/api/v1/chat/completions"
GENERIC_ALIASES = {
    "area",
    "book",
    "building",
    "city",
    "creature",
    "faction",
    "hold",
    "item",
    "location",
    "npc",
    "person",
    "place",
    "region",
    "settlement",
    "spell",
    "town",
    "village",
    "weapon",
}
MANUAL_REJECTIONS = {
    ("1st_era", "firstage"),
    ("3rd_era", "septimera"),
    ("a_minor_maze", "shalidorlabyrinthian"),
    ("aldmeri", "aldmer"),
    ("aldmeri", "firstfolk"),
    ("chimer", "peopleofthenorth"),
    ("fork_of_horripilation", "forky"),
    ("frost_atronach", "wateratronach"),
    ("hell", "outerrealms"),
    ("hero_of_kvatch", "champion"),
    ("orcish_arrow", "orchisharrow"),
}
MANUAL_ADDITIONS = {
    "mehrune_dagon": ["Mehrunes Dagon"],
}


def comparable_key(value: str) -> str:
    normalized = value.strip().lower().replace("_", " ")
    normalized = re.sub(r"^(?:the|a|an)\s+", "", normalized)
    normalized = re.sub(r"\b1st\b", "first", normalized)
    normalized = re.sub(r"\b2nd\b", "second", normalized)
    normalized = re.sub(r"\b3rd\b", "third", normalized)
    normalized = re.sub(r"\b4th\b", "fourth", normalized)
    return re.sub(r"[^a-z0-9]+", "", normalized)


def display_topic(value: str) -> str:
    return re.sub(r"\s+", " ", value.replace("_", " ")).strip()


def chunks(rows: list[dict[str, str]], size: int) -> list[list[dict[str, str]]]:
    return [rows[index : index + size] for index in range(0, len(rows), size)]


def load_rows(path: Path, limit: int | None) -> list[dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        if not reader.fieldnames or "topic" not in reader.fieldnames:
            raise ValueError("Input CSV must contain a topic column.")
        rows = []
        for row in reader:
            topic = (row.get("topic") or "").strip()
            if not topic:
                continue
            rows.append(
                {
                    "topic": topic,
                    "description": (
                        row.get("topic_desc_basic")
                        or row.get("topic_desc")
                        or row.get("description")
                        or ""
                    ).strip(),
                    "category": (row.get("category") or "").strip(),
                }
            )
            if limit and len(rows) >= limit:
                break
        return rows


def request_schema(topics: list[str]) -> dict[str, Any]:
    return {
        "type": "json_schema",
        "json_schema": {
            "name": "oghma_alias_batch",
            "strict": True,
            "schema": {
                "type": "object",
                "properties": {
                    "entries": {
                        "type": "array",
                        "minItems": len(topics),
                        "maxItems": len(topics),
                        "items": {
                            "type": "object",
                            "properties": {
                                "topic": {"type": "string", "enum": topics},
                                "aliases": {
                                    "type": "array",
                                    "maxItems": 5,
                                    "items": {"type": "string"},
                                },
                            },
                            "required": ["topic", "aliases"],
                            "additionalProperties": False,
                        },
                    }
                },
                "required": ["entries"],
                "additionalProperties": False,
            },
        },
    }


def make_prompt(batch: list[dict[str, str]]) -> str:
    payload = [
        {
            "topic": row["topic"],
            "display_name": display_topic(row["topic"]),
            "category": row["category"],
            "description": row["description"][:3500],
        }
        for row in batch
    ]
    return (
        "Create conservative search aliases for these Skyrim knowledge articles. "
        "Return every supplied topic exactly once. An alias must be a distinct proper "
        "name, common in-world name, abbreviation, title, spelling variant, or explicit "
        "alternate name for that exact subject. It must be usable in place of the "
        "canonical name without changing the referent. For books, documents, songs, "
        "and poems, return only genuine alternate titles, never authors, characters, "
        "locations, or subjects discussed by the work. For eras and dates, return only "
        "established names, never names from unrelated calendar systems. Use only the "
        "supplied article and well-established Skyrim terminology. Return zero aliases "
        "when uncertain. "
        "Do not return descriptions, broad categories, generic nouns, invented phrases, "
        "associated concepts, the canonical topic with only underscores/spaces changed, "
        "or aliases belonging to another subject. Keep each alias under 80 characters and do not use commas "
        "inside an alias.\n\nArticles:\n"
        + json.dumps(payload, ensure_ascii=False)
    )


def post_json(endpoint: str, api_key: str, payload: dict[str, Any], attempts: int) -> dict[str, Any]:
    body = json.dumps(payload).encode("utf-8")
    for attempt in range(1, attempts + 1):
        request = urllib.request.Request(
            endpoint,
            data=body,
            headers={
                "Authorization": f"Bearer {api_key}",
                "Content-Type": "application/json",
                "HTTP-Referer": "https://github.com/abeiro/HerikaServer",
                "X-Title": "HerikaServer Oghma Alias Generator",
            },
            method="POST",
        )
        try:
            with urllib.request.urlopen(request, timeout=180) as response:
                return json.loads(response.read().decode("utf-8"))
        except (urllib.error.URLError, TimeoutError, json.JSONDecodeError) as error:
            if attempt == attempts:
                raise RuntimeError(f"OpenRouter request failed after {attempts} attempts: {error}") from error
            time.sleep(min(2**attempt, 15))
    raise RuntimeError("OpenRouter request failed.")


def generate_batch(
    batch_index: int,
    batch: list[dict[str, str]],
    args: argparse.Namespace,
    api_key: str,
    cache_dir: Path,
) -> tuple[int, dict[str, list[str]], bool]:
    topics = [row["topic"] for row in batch]
    request_payload = {
        "model": args.model,
        "messages": [
            {
                "role": "system",
                "content": "You curate precise alternate names for a Skyrim knowledge index.",
            },
            {"role": "user", "content": make_prompt(batch)},
        ],
        "temperature": 0.1,
        "max_tokens": 3500,
        "response_format": request_schema(topics),
    }
    digest = hashlib.sha256(
        json.dumps(request_payload, sort_keys=True, ensure_ascii=False).encode("utf-8")
    ).hexdigest()
    cache_path = cache_dir / f"{digest}.json"
    cached = cache_path.exists()
    if cached:
        response_payload = json.loads(cache_path.read_text(encoding="utf-8"))
    else:
        response_payload = post_json(args.endpoint, api_key, request_payload, args.attempts)
        cache_path.write_text(
            json.dumps(response_payload, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )

    try:
        message = response_payload["choices"][0]["message"]
        content = message.get("content") or message.get("reasoning")
        parsed = json.loads(content)
        entries = parsed["entries"]
    except (KeyError, IndexError, TypeError, json.JSONDecodeError) as error:
        cache_path.unlink(missing_ok=True)
        raise RuntimeError(f"Invalid structured response for batch {batch_index + 1}") from error

    by_topic: dict[str, list[str]] = {topic: [] for topic in topics}
    for entry in entries:
        topic = str(entry.get("topic", ""))
        if topic not in by_topic:
            continue
        aliases = entry.get("aliases", [])
        if isinstance(aliases, list):
            by_topic[topic] = [str(alias).strip() for alias in aliases if str(alias).strip()]
    return batch_index, by_topic, cached


def validate_batch(
    batch_index: int,
    batch: list[dict[str, Any]],
    args: argparse.Namespace,
    api_key: str,
    cache_dir: Path,
) -> tuple[int, dict[str, list[str]], bool]:
    topics = [str(row["topic"]) for row in batch]
    articles = [
        {
            "topic": row["topic"],
            "description": str(row["description"])[:3500],
            "candidate_aliases": row["aliases"],
        }
        for row in batch
    ]
    request_payload = {
        "model": args.model,
        "messages": [
            {
                "role": "system",
                "content": "You are a strict fact checker for a Skyrim alternate-name index.",
            },
            {
                "role": "user",
                "content": (
                    "Audit these candidate aliases. Return every topic exactly once and "
                    "retain only candidates that are established alternate names for the "
                    "exact same subject. A retained alias must be safely usable wherever "
                    "the canonical topic is used without changing the referent. Reject "
                    "associated people, places, artifacts, factions, descriptions, book "
                    "subjects, authors, names from unrelated calendars, jokes, generic "
                    "terms, and titles invented from prose. For books and documents, "
                    "retain only genuine alternate titles. When uncertain, reject it. "
                    "Never add a candidate not supplied here.\n\n"
                    + json.dumps(articles, ensure_ascii=False)
                ),
            },
        ],
        "temperature": 0,
        "max_tokens": 4000,
        "reasoning": {"effort": "low", "exclude": True},
        "response_format": request_schema(topics),
    }
    digest = hashlib.sha256(
        json.dumps(request_payload, sort_keys=True, ensure_ascii=False).encode("utf-8")
    ).hexdigest()
    cache_path = cache_dir / f"review-{digest}.json"
    cached = cache_path.exists()
    if cached:
        response_payload = json.loads(cache_path.read_text(encoding="utf-8"))
        try:
            message = response_payload["choices"][0]["message"]
            content = message.get("content") or message.get("reasoning")
            entries = json.loads(content)["entries"]
        except (KeyError, IndexError, TypeError, json.JSONDecodeError):
            cache_path.unlink(missing_ok=True)
            cached = False

    if not cached:
        last_error: Exception | None = None
        for attempt in range(1, args.attempts + 1):
            try:
                response_payload = post_json(args.endpoint, api_key, request_payload, 1)
                message = response_payload["choices"][0]["message"]
                content = message.get("content") or message.get("reasoning")
                entries = json.loads(content)["entries"]
                cache_path.write_text(
                    json.dumps(response_payload, ensure_ascii=False, indent=2),
                    encoding="utf-8",
                )
                break
            except (KeyError, IndexError, TypeError, json.JSONDecodeError, RuntimeError) as error:
                last_error = error
                if attempt < args.attempts:
                    time.sleep(min(2**attempt, 15))
        else:
            raise RuntimeError(
                f"Invalid validation response for batch {batch_index + 1}"
            ) from last_error

    allowed = {
        str(row["topic"]): {comparable_key(str(alias)): str(alias) for alias in row["aliases"]}
        for row in batch
    }
    result: dict[str, list[str]] = {topic: [] for topic in topics}
    for entry in entries:
        topic = str(entry.get("topic", ""))
        if topic not in allowed:
            continue
        for alias in entry.get("aliases", []):
            key = comparable_key(str(alias))
            if key in allowed[topic]:
                result[topic].append(allowed[topic][key])
    return batch_index, result, cached


def sanitize_aliases(
    rows: list[dict[str, str]],
    generated: dict[str, list[str]],
) -> tuple[dict[str, list[str]], list[dict[str, str]]]:
    canonical_owners = {comparable_key(row["topic"]): row["topic"] for row in rows}
    accepted: dict[str, list[str]] = {}
    alias_owner: dict[str, str] = {}
    rejected: list[dict[str, str]] = []

    for row in rows:
        topic = row["topic"]
        topic_key = comparable_key(topic)
        topic_aliases: list[str] = []
        local_seen: set[str] = set()
        for raw_alias in generated.get(topic, []):
            alias = re.sub(r"\s+", " ", raw_alias).strip(" \t\r\n,;")
            key = comparable_key(alias)
            reason = ""
            if not alias or len(alias) < 3 or len(alias) > 80 or "," in alias:
                reason = "invalid format"
            elif key == topic_key or key in local_seen:
                reason = "canonical or duplicate variant"
            elif alias.lower() in GENERIC_ALIASES:
                reason = "generic term"
            elif key in canonical_owners and canonical_owners[key] != topic:
                reason = f"matches canonical topic {canonical_owners[key]}"
            elif key in alias_owner and alias_owner[key] != topic:
                reason = f"shared with {alias_owner[key]}"

            if reason:
                rejected.append({"topic": topic, "alias": alias, "reason": reason})
                continue
            local_seen.add(key)
            alias_owner[key] = topic
            topic_aliases.append(alias)
        accepted[topic] = topic_aliases
    return accepted, rejected


def apply_curated_overrides(
    aliases_by_topic: dict[str, list[str]],
) -> tuple[dict[str, list[str]], list[dict[str, str]]]:
    rejected: list[dict[str, str]] = []
    for topic, aliases in aliases_by_topic.items():
        kept = []
        for alias in aliases:
            if (topic, comparable_key(alias)) in MANUAL_REJECTIONS:
                rejected.append(
                    {"topic": topic, "alias": alias, "reason": "rejected during curated review"}
                )
                continue
            kept.append(alias)
        aliases_by_topic[topic] = kept

    for topic, additions in MANUAL_ADDITIONS.items():
        current = aliases_by_topic.setdefault(topic, [])
        seen = {comparable_key(alias) for alias in current}
        for alias in additions:
            if comparable_key(alias) not in seen:
                current.append(alias)
                seen.add(comparable_key(alias))
    return aliases_by_topic, rejected


def write_output(path: Path, rows: list[dict[str, str]], aliases: dict[str, list[str]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=["topic", "aliases"])
        writer.writeheader()
        for row in rows:
            writer.writerow({"topic": row["topic"], "aliases": ", ".join(aliases.get(row["topic"], []))})


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--input", type=Path, required=True)
    parser.add_argument("--output", type=Path, default=Path("data/oghma_aliases.csv"))
    parser.add_argument("--report", type=Path, default=Path("tools/oghma/.local/report.json"))
    parser.add_argument("--cache-dir", type=Path, default=Path("tools/oghma/.local/cache"))
    parser.add_argument("--model", default=DEFAULT_MODEL)
    parser.add_argument("--endpoint", default=DEFAULT_ENDPOINT)
    parser.add_argument("--batch-size", type=int, default=12)
    parser.add_argument("--workers", type=int, default=4)
    parser.add_argument("--attempts", type=int, default=3)
    parser.add_argument("--limit", type=int)
    parser.add_argument("--skip-validation", action="store_true")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    api_key = os.environ.get("OPENROUTER_API_KEY", "").strip()
    if not api_key:
        print("OPENROUTER_API_KEY is required.", file=sys.stderr)
        return 2

    rows = load_rows(args.input, args.limit)
    if not rows:
        print("No Oghma rows found.", file=sys.stderr)
        return 2
    args.cache_dir.mkdir(parents=True, exist_ok=True)
    batches = chunks(rows, max(1, args.batch_size))
    generated: dict[str, list[str]] = {}
    cache_hits = 0

    with ThreadPoolExecutor(max_workers=max(1, args.workers)) as executor:
        futures = {
            executor.submit(indexed_batch, index, batch, args, api_key, args.cache_dir): index
            for index, batch in enumerate(batches)
        }
        completed = 0
        for future in as_completed(futures):
            _, batch_result, cached = future.result()
            generated.update(batch_result)
            cache_hits += int(cached)
            completed += 1
            print(f"Processed batch {completed}/{len(batches)}", flush=True)

    accepted, rejected = sanitize_aliases(rows, generated)
    validation_cache_hits = 0
    candidates_before_validation = sum(len(values) for values in accepted.values())
    if not args.skip_validation:
        rows_by_topic = {row["topic"]: row for row in rows}
        review_rows = [
            {**rows_by_topic[topic], "aliases": aliases}
            for topic, aliases in accepted.items()
            if aliases
        ]
        review_batches = chunks(review_rows, max(1, args.batch_size))
        validated: dict[str, list[str]] = {row["topic"]: [] for row in rows}
        with ThreadPoolExecutor(max_workers=max(1, args.workers)) as executor:
            futures = {
                executor.submit(
                    validate_batch,
                    index,
                    batch,
                    args,
                    api_key,
                    args.cache_dir,
                ): index
                for index, batch in enumerate(review_batches)
            }
            completed = 0
            for future in as_completed(futures):
                _, batch_result, cached = future.result()
                validated.update(batch_result)
                validation_cache_hits += int(cached)
                completed += 1
                print(f"Validated batch {completed}/{len(review_batches)}", flush=True)
        accepted, validation_rejected = sanitize_aliases(rows, validated)
        retained = {
            (topic, comparable_key(alias))
            for topic, values in accepted.items()
            for alias in values
        }
        for topic, values in generated.items():
            for alias in values:
                key = comparable_key(alias)
                if key and (topic, key) not in retained:
                    validation_rejected.append(
                        {"topic": topic, "alias": alias, "reason": "rejected by validation pass"}
                    )
        rejected.extend(validation_rejected)

    accepted, curated_rejected = apply_curated_overrides(accepted)
    rejected.extend(curated_rejected)
    write_output(args.output, rows, accepted)
    args.report.parent.mkdir(parents=True, exist_ok=True)
    report = {
        "model": args.model,
        "articles": len(rows),
        "articles_with_aliases": sum(bool(values) for values in accepted.values()),
        "aliases": sum(len(values) for values in accepted.values()),
        "cache_hits": cache_hits,
        "validation_cache_hits": validation_cache_hits,
        "candidates_before_validation": candidates_before_validation,
        "rejected": rejected,
    }
    args.report.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")
    print(
        f"Wrote {report['aliases']} aliases for {report['articles_with_aliases']} "
        f"of {report['articles']} articles to {args.output}"
    )
    return 0


def indexed_batch(
    index: int,
    batch: list[dict[str, str]],
    args: argparse.Namespace,
    api_key: str,
    cache_dir: Path,
) -> tuple[int, dict[str, list[str]], bool]:
    return generate_batch(index, batch, args, api_key, cache_dir)


if __name__ == "__main__":
    raise SystemExit(main())
