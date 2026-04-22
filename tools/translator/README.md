# Translation Helper (desktop GUI)

A tkinter desktop tool that walks through missing translation keys detected by
`php artisan app:missing-translation-analyzer-command` and lets you write
EN/FR pairs one at a time, with optional Claude-powered suggestions.

Ships with the `condoedge/utils` package so any Laravel app that depends on it
(SISC, Coolecto, future Decizif apps…) can use the same tool.

## Prerequisites

- Python 3.10+ (tkinter is part of the standard library on Windows).
- PHP reachable on PATH.
- Optional: `claude` CLI (Claude Code) for AI-assisted translations — no API
  key needed, billing goes through each dev's own Claude subscription.

## AI cost knobs

The GUI calls `claude --print` once per key you Suggest or that the prefetch
worker looks at. To keep Max-subscription usage under control, the tool has
these defaults:

- **Model: Haiku by default** (`claude-haiku-4-5-20251001`). Roughly 10× cheaper
  than Sonnet, plenty for short UI labels.
- **Wider file context disabled** — only the 5-line immediate snippet is sent.
  Saves hundreds of tokens per call.

Override via environment variables (set them before launching):

```bash
# Switch to Sonnet (better for complex phrases, more expensive)
SISC_TRANSLATOR_MODEL=sonnet php artisan app:translator

# Or pick a full model id
SISC_TRANSLATOR_MODEL=claude-sonnet-4-6 php artisan app:translator

# Include ±20 lines of file context when Haiku struggles with a label
SISC_TRANSLATOR_WIDE_CONTEXT=1 php artisan app:translator
```

Tip: if you only translate a few keys by hand, leave Auto-prefetch OFF — each
prefetched key is one CLI invocation.

## Launch

From any project root that has `condoedge/utils` installed:

```bash
# Easiest — via artisan wrapper
php artisan app:translator

# With an existing JSON export (skips the 20 s scan)
php artisan app:missing-translation-analyzer-command --json > missing.json
php artisan app:translator missing.json

# Bypass artisan if you prefer
python vendor/condoedge/utils/tools/translator/translator.py
```

## Keyboard shortcuts

| Key          | Action                                                  |
|--------------|---------------------------------------------------------|
| `←` / `→`    | Prev / Next without saving                              |
| `Ctrl+S`     | Save EN+FR and move to the next key                     |
| `Ctrl+G`     | Ask Claude to suggest translations for the current key  |
| `Esc`        | Close                                                   |

## Actions

| Button          | Writes JSON               | DB flag         | Notes                                                            |
|-----------------|---------------------------|-----------------|------------------------------------------------------------------|
| Save & Next     | ✅ for filled field(s)    | `fixed_at`      | Advances to the next key                                         |
| Save & Close    | ✅ for all drafts         | `fixed_at` × N  | Flushes every entry you typed during the session                 |
| Skip            | ❌                        | ❌              | Will re-appear on next scan                                      |
| Ignore          | ❌                        | `ignored_at`    | Persistent — hidden from future scans unless `--include-triaged` |
| 🤖 Suggest      | fields prefilled          | ❌              | Classifies the key too (non-translations highlight Ignore)       |
| Auto-prefetch   | background                | ❌              | Pre-computes upcoming suggestions while you work                 |

## What the AI sees

For each key the prompt includes:

1. The translation key itself (e.g. `discussions.today`).
2. The immediate 5-line code context captured by the analyzer.
3. ±20 lines from the real source file around the usage line, with a `→`
   marker on the exact line.
4. SISC-specific glossary enforced in the system prompt (Scout vs Member,
   Volunteer vs Leader, Person, Brevet, Quebec French conventions, …).

Response is classified into `is_translation_key: true|false`. When Claude
thinks the string is NOT a UI key (icon name, CSS class, date format…), the
GUI highlights the Ignore button and shows a hint.

## Files touched

| Path                                                 | Purpose                                |
|------------------------------------------------------|----------------------------------------|
| `resources/lang/en.json`, `resources/lang/fr.json`   | Canonical translation files (written)  |
| `missing_translations` DB table                      | Status (fixed/ignored) + hit counts    |
| `storage/app/translation_ignore_pending.json`        | Queue for `--exclude-key` batches      |
| `storage/app/translation_keys.json`                  | Cache from the analyzer                |

## Troubleshooting

- **"claude CLI not found"** — the AI button is disabled. Install Claude Code
  and run `claude` once to authenticate.
- **python not found** when running the artisan wrapper — pass
  `--python=py` or the full path:
  `php artisan app:translator --python=C:/Python313/python.exe`.
- **Writes reorder the whole JSON file** — not supposed to happen. The
  script preserves the existing key order and only inserts new keys at their
  alphabetical position. Round-trip is byte-identical on unchanged data.
