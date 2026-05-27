"""
Claude-powered translation suggestions for the SISC translator GUI.

Uses the `claude` CLI (Claude Code) when available — no API key required,
billing goes through each dev's existing Claude subscription.

Defaults (keep usage cheap):
  - Model: Haiku by default — plenty for short UI labels, ~10× cheaper than Sonnet.
    Override with env `SISC_TRANSLATOR_MODEL` (values: 'haiku' | 'sonnet' | 'opus'
    or a full Anthropic model id).
  - Wider file context: OFF by default. Opt-in with env
    `SISC_TRANSLATOR_WIDE_CONTEXT=1` to include ±20 lines around the usage line.
    Off = immediate 5-line context only (smaller prompt, cheaper).
"""

from __future__ import annotations

import json
import os
import shutil
import subprocess
from pathlib import Path
from typing import Optional


# --------------------------------------------------------------------------- #
# Detection
# --------------------------------------------------------------------------- #

def claude_cli_path() -> Optional[str]:
    """Return the absolute path to the `claude` binary if available, else None."""
    return shutil.which("claude")


def is_available() -> bool:
    return claude_cli_path() is not None


# --------------------------------------------------------------------------- #
# Prompt construction
# --------------------------------------------------------------------------- #

SYSTEM_PROMPT = """You translate UI labels for SISC, a Canadian Scout management platform (Laravel + Kompo framework). Audience: volunteer leaders and admin staff, mostly Quebec-based.

GLOSSARY (strict — reuse these terms verbatim):
- Scout = youth member (NEVER say "Member" / "Membre")
- Volunteer / Bénévole = adult leader (NEVER say "Leader" / "Chef")
- Person / Personne = individual record (NEVER "Member" / "Membre")
- Branch / Branche, Unit / Unité, Group / Groupe, District = hierarchy levels
- Camp (stays "Camp" in FR) = overnight camping event, approval workflow
- Inscription / Inscription = registration/sign-up (keep "inscription" in FR)
- Brevet / Brevet = training certificate (keep "brevet" in FR, not "diplôme")
- Background check / Vérification des antécédents = judiciary record check
- Meetup / Rencontre = weekly meeting
- Fundraiser / Collecte de fonds

STYLE:
- Short, direct UI labels. Buttons/titles = 1-4 words. Error/help = full sentence ending with period.
- EN: American spelling. Sentence case for labels ("Save changes"), Title Case only for page titles.
- FR: Quebec conventions. No franglais. Use "courriel" (not "email"), "téléverser" (not "upload"), "magasiner" (not "shopper").
- Preserve placeholders EXACTLY: `:attribute`, `:max`, `:user`, `{{name}}` — never translate them.
- Match the formality shown in the code context.

CLASSIFICATION — set is_translation_key=false when the identifier is clearly ONE of:
- icon name (hyphenated token like chevron-down, alert-triangle, dollar-circle, star-1) or context shows _Sax/_Icon/icon(
- date/time/number format (Y-m-d, H:i:s, M d, Y, #,##0.00)
- HTTP/HTML attribute (X-*, aria-*, data-*, role=)
- snake_case DB column or code identifier ending in _id, _at, _type, _by
- ALL_CAPS constant (PDO_MYSQL, MEMORY_LIMIT)
- single generic code word that never appears alone in UI (function_exists, pdo_mysql)
Otherwise true.

OUTPUT: JSON only. No fences, no prose, no commentary.
{"is_translation_key":bool,"en":"...","fr":"..."}
Examples:
{"is_translation_key":true,"en":"Camp validation","fr":"Validation de camp"}
{"is_translation_key":true,"en":"Add a volunteer","fr":"Ajouter un bénévole"}
{"is_translation_key":false,"en":"chevron-down","fr":"chevron-down"}"""


def build_user_prompt(
    key: str,
    code_context: str,
    file_path: str = "",
    wider_context: str = "",
) -> str:
    """Assemble the user-facing portion of the prompt."""
    parts: list[str] = [f"Translation key: `{key}`", ""]

    if file_path:
        parts.append(f"Source file: {file_path}")
    parts.append("")

    if code_context:
        parts += [
            "Immediate code context where the key is used:",
            "```php",
            code_context.strip(),
            "```",
            "",
        ]

    if wider_context and wider_context.strip() != code_context.strip():
        parts += [
            "Wider file context (arrow marks the line of interest):",
            "```php",
            wider_context.rstrip(),
            "```",
            "",
        ]

    parts.append(
        "Produce the English and French translations. "
        "Reply with JSON only: {\"en\": \"...\", \"fr\": \"...\"}"
    )
    return "\n".join(parts)


# --------------------------------------------------------------------------- #
# File context extraction
# --------------------------------------------------------------------------- #

def read_wider_context(
    project_root: Path,
    file_rel: str,
    line: Optional[int],
    radius: int = 20,
) -> str:
    """Read a window of lines around `line` from the source file, with a `→` marker."""
    if not file_rel or not line:
        return ""
    rel = file_rel.replace("\\", os.sep).replace("/", os.sep)
    path = project_root / rel
    try:
        source = path.read_text(encoding="utf-8", errors="replace")
    except (FileNotFoundError, OSError):
        return ""

    lines = source.splitlines()
    start = max(0, line - radius - 1)
    end = min(len(lines), line + radius)

    out: list[str] = []
    for i, text in enumerate(lines[start:end], start=start + 1):
        marker = "→ " if i == line else "  "
        out.append(f"{marker}{i:5d} | {text}")
    return "\n".join(out)


# --------------------------------------------------------------------------- #
# Claude call
# --------------------------------------------------------------------------- #

class AiError(RuntimeError):
    """Raised when the AI call fails or produces unparseable output."""


def _extract_json(response: str) -> dict:
    """Pull the first balanced JSON object out of a text response."""
    text = response.strip()
    if text.startswith("```"):
        # Strip opening ``` and optional language hint
        first_nl = text.find("\n")
        if first_nl >= 0:
            text = text[first_nl + 1:]
        if text.endswith("```"):
            text = text[:-3]
        text = text.strip()

    start = text.find("{")
    end = text.rfind("}")
    if start < 0 or end <= start:
        raise AiError(f"No JSON object in response:\n{response!r}")
    try:
        return json.loads(text[start:end + 1])
    except json.JSONDecodeError as exc:
        raise AiError(f"Invalid JSON from AI: {exc}\nRaw: {text[start:end+1]!r}")


_MODEL_ALIASES = {
    "haiku": "claude-haiku-4-5-20251001",
    "sonnet": "claude-sonnet-4-6",
    "opus": "claude-opus-4-7",
}


def _resolve_model() -> str:
    """Model to use for translations. Defaults to Haiku (cheap + fast)."""
    raw = (os.environ.get("SISC_TRANSLATOR_MODEL") or "haiku").strip()
    return _MODEL_ALIASES.get(raw.lower(), raw)


def suggest(
    key: str,
    code_context: str,
    file_path: str = "",
    wider_context: str = "",
    timeout: int = 60,
) -> dict:
    """
    Ask Claude for EN/FR translations for `key`.

    Returns a dict with 'en', 'fr', 'is_translation_key'. Raises AiError on failure.

    Cost-saving defaults:
    - Model defaults to Haiku (override with env SISC_TRANSLATOR_MODEL).
    - wider_context is dropped unless env SISC_TRANSLATOR_WIDE_CONTEXT=1 — the
      immediate 5-line snippet captured by the analyzer is usually enough for
      short UI labels and makes each prompt dramatically smaller.
    """
    cli = claude_cli_path()
    if not cli:
        raise AiError("`claude` CLI not found on PATH. Install Claude Code and authenticate.")

    include_wide = os.environ.get("SISC_TRANSLATOR_WIDE_CONTEXT", "").strip() in ("1", "true", "yes")
    effective_wider = wider_context if include_wide else ""

    prompt = (
        SYSTEM_PROMPT
        + "\n\n---\n\n"
        + build_user_prompt(key, code_context, file_path, effective_wider)
    )

    creationflags = 0
    if os.name == "nt":
        creationflags = 0x08000000  # CREATE_NO_WINDOW — no console flash

    cmd = [cli, "--print", "--model", _resolve_model()]

    try:
        proc = subprocess.run(
            cmd,
            input=prompt,
            capture_output=True,
            text=True,
            encoding="utf-8",
            errors="replace",
            timeout=timeout,
            creationflags=creationflags,
        )
    except FileNotFoundError as exc:
        raise AiError(f"claude CLI not launchable: {exc}")
    except subprocess.TimeoutExpired:
        raise AiError(f"claude CLI timed out after {timeout}s")

    if proc.returncode != 0:
        raise AiError(
            f"claude exited {proc.returncode}: "
            f"{(proc.stderr or proc.stdout).strip()[:300]}"
        )

    data = _extract_json(proc.stdout)
    if "en" not in data or "fr" not in data:
        raise AiError(f"Response missing 'en' or 'fr' key: {data!r}")
    return {
        "en": str(data["en"]).strip(),
        "fr": str(data["fr"]).strip(),
        "is_translation_key": bool(data.get("is_translation_key", True)),
    }
