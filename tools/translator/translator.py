#!/usr/bin/env python3
"""
SISC Translation Helper — desktop GUI to translate missing keys one by one.

Usage:
    python translator.py                    # runs the artisan analyzer itself
    python translator.py missing.json       # uses an existing JSON export
    python translator.py --project PATH     # force project root detection

No external deps — tkinter + stdlib only. Tested on Windows with Python 3.10+.

Workflow:
    1. Launch. The UI loads the list of missing keys (EN/FR merged).
    2. Navigate with [<] [>] or arrow keys. For each key:
       - Read the context snippet (file path + code around the usage).
       - Fill in the English and French translations.
       - [Save & Next] writes both locales to resources/lang/*.json and advances.
       - [Skip] moves to the next key without writing.
       - [Ignore] records the key in storage/app/translation_ignore_pending.json
         so you can later run `php artisan app:missing-translation-analyzer-command
         --exclude-key=...` to persist it.
    3. Close the window when done. Rerun the artisan analyzer to refresh.

Limitations (v1):
    - Does not update the `missing_translations` table directly (use DB clear or
      the UI table to mark rows as fixed after saving).
    - Does not sync to BabelEdit `.babel` files.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import threading
import time
import tkinter as tk
from datetime import datetime
from pathlib import Path
from tkinter import filedialog, messagebox, scrolledtext, ttk
from typing import Optional

# Local module — sibling file.
sys.path.insert(0, str(Path(__file__).resolve().parent))
import ai as ai_module  # noqa: E402


# --------------------------------------------------------------------------- #
# Paths & project discovery
# --------------------------------------------------------------------------- #

def find_project_root(start: Path) -> Optional[Path]:
    """Walk up from `start` until a Laravel app root is found.

    Required markers: `artisan` binary + `composer.json` + `resources/lang/`.
    The `artisan` check is what distinguishes the real app root from a package
    (e.g. vendor/condoedge/utils ships a composer.json + resources/lang/ too).
    """
    for parent in [start, *start.parents]:
        if (
            (parent / "artisan").is_file()
            and (parent / "composer.json").is_file()
            and (parent / "resources" / "lang").is_dir()
        ):
            return parent
    return None


# --------------------------------------------------------------------------- #
# Lang file I/O (preserves tab indent + alphabetical key order)
# --------------------------------------------------------------------------- #

def load_lang(path: Path) -> dict:
    """Load a Laravel flat-JSON lang file, preserving insertion order."""
    if not path.is_file():
        return {}
    with path.open("r", encoding="utf-8") as fh:
        return json.load(fh)  # dict preserves insertion order (Python 3.7+)


def _insert_sorted(ordered_items: list[tuple[str, str]], new_key: str, new_value: str) -> list[tuple[str, str]]:
    """Insert a new (key, value) into the ordered list at a lexicographic position,
    case-insensitive. Existing order is otherwise preserved so diffs stay minimal."""
    lower = new_key.lower()
    for i, (k, _v) in enumerate(ordered_items):
        if lower < k.lower():
            return ordered_items[:i] + [(new_key, new_value)] + ordered_items[i:]
    return ordered_items + [(new_key, new_value)]


def save_lang(path: Path, data: dict, original: dict) -> None:
    """Write the lang file preserving the file's existing key order and only
    inserting new keys at their case-insensitive alphabetical position."""
    # Start from the original order, carrying over updated values.
    ordered: list[tuple[str, str]] = []
    seen: set[str] = set()
    for k in original.keys():
        if k in data:
            ordered.append((k, data[k]))
            seen.add(k)

    # Add new keys (those not in the original file) at their sorted position.
    for k in data.keys():
        if k not in seen:
            ordered = _insert_sorted(ordered, k, data[k])

    body = json.dumps(dict(ordered), ensure_ascii=False, indent="\t")
    # Laravel lang JSON convention: trailing newline, LF line endings.
    with path.open("w", encoding="utf-8", newline="\n") as fh:
        fh.write(body + "\n")


# --------------------------------------------------------------------------- #
# Analyzer integration
# --------------------------------------------------------------------------- #

def run_analyzer(project_root: Path) -> dict:
    """Invoke `php artisan app:missing-translation-analyzer-command --json` and
    return the parsed payload (dict with locale keys)."""
    proc = subprocess.run(
        ["php", "artisan", "app:missing-translation-analyzer-command", "--json"],
        cwd=str(project_root),
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
    )
    if proc.returncode != 0:
        raise RuntimeError(
            f"artisan exited {proc.returncode}\nstderr: {proc.stderr}"
        )
    # First line is "Indexing N translation keys..." — strip until '{' or '['.
    stdout = proc.stdout
    brace = stdout.find("{")
    if brace < 0:
        raise RuntimeError(f"No JSON object in analyzer output:\n{stdout[:500]}")
    return json.loads(stdout[brace:])


def load_missing_payload(
    json_file: Optional[Path], project_root: Path
) -> dict:
    if json_file is not None:
        if not json_file.is_file():
            raise SystemExit(f"{json_file} not found")
        text = json_file.read_text(encoding="utf-8")
        brace = text.find("{")
        if brace < 0:
            raise SystemExit("No JSON object found in input file")
        return json.loads(text[brace:])
    return run_analyzer(project_root)


# --------------------------------------------------------------------------- #
# Model: merge locales into a single iterable of entries
# --------------------------------------------------------------------------- #

def build_entries(payload: dict) -> list[dict]:
    """
    Collapse the per-locale payload into a flat, deduplicated list of keys.
    Keeps ALL locations so target resolution can prefer linked-folder paths
    when multiple files reference the same key.
    """
    by_key: dict[str, dict] = {}
    for locale, items in payload.items():
        for item in items:
            key = item["key"]
            locations = item.get("locations") or []
            if key not in by_key:
                primary = locations[0] if locations else {}
                by_key[key] = {
                    "key": key,
                    "file": primary.get("file", ""),
                    "line": primary.get("line"),
                    "context": primary.get("context", ""),
                    "all_files": [loc.get("file", "") for loc in locations if loc.get("file")],
                }
            else:
                # Merge additional locations from subsequent locales.
                existing_files = set(by_key[key]["all_files"])
                for loc in locations:
                    f = loc.get("file")
                    if f and f not in existing_files:
                        by_key[key]["all_files"].append(f)
                        existing_files.add(f)
    return sorted(by_key.values(), key=lambda e: e["key"].lower())


# --------------------------------------------------------------------------- #
# UI
# --------------------------------------------------------------------------- #

class TranslatorApp(tk.Tk):
    PADDING = 8

    def __init__(self, entries: list[dict], project_root: Path):
        super().__init__()
        self.title(f"SISC Translation Helper — {len(entries)} missing")
        self.geometry("820x620")
        self.minsize(700, 520)
        self.configure(padx=self.PADDING, pady=self.PADDING)

        self.entries = entries
        self.index = 0
        self.project_root = project_root
        # Per-target lang cache. A "target" is a project root (main app OR a linked package
        # that owns its own resources/lang). Each target gets its own lang dict + snapshot.
        self._lang_cache: dict[Path, dict] = {}
        # Precompute for the main app so legacy accessors (en_data/fr_data) still work.
        self._ensure_target_loaded(project_root)
        main = self._lang_cache[project_root]
        self.en_path = main["en_path"]
        self.fr_path = main["fr_path"]
        self.en_data = main["en"]
        self.fr_data = main["fr"]
        self.en_original = main["en_original"]
        self.fr_original = main["fr_original"]
        self.ignore_path = project_root / "storage" / "app" / "translation_ignore_pending.json"
        self.ignored: set[str] = self._load_ignored()
        # Buffer of artisan subprocesses running fire-and-forget — kept to avoid GC killing them.
        self._async_procs: list[subprocess.Popen] = []
        # Session counters
        self._session = {"saved": 0, "ignored": 0, "skipped": 0}
        # Per-entry drafts: unsaved text typed in EN/FR fields as the user navigates.
        # Flushed to disk on Save & Close.
        self._drafts: dict[str, dict[str, str]] = {}
        # AI suggestion cache: key -> {'en', 'fr', 'is_translation_key'}
        self._ai_cache: dict[str, dict] = {}
        # Auto-prefetch state
        self._auto_var: Optional[tk.BooleanVar] = None
        self._prefetch_stop = threading.Event()
        self._prefetch_thread: Optional[threading.Thread] = None
        # Auto save & next state
        self._auto_advance_var: Optional[tk.BooleanVar] = None
        self._auto_advance_after_id: Optional[str] = None
        self._auto_advance_countdown_id: Optional[str] = None
        self._auto_advance_seconds = 3  # base delay
        # Pending action for the current countdown: 'save' | 'ignore' | 'skip'
        self._auto_advance_action: Optional[str] = None

        self._build_ui()
        self._bind_shortcuts()
        if entries:
            self.render_current()
        else:
            self._show_empty_state()

    # ---- data -------------------------------------------------------------
    def _load_ignored(self) -> set[str]:
        if self.ignore_path.is_file():
            try:
                return set(json.loads(self.ignore_path.read_text(encoding="utf-8")))
            except Exception:
                pass
        return set()

    def _persist_ignored(self) -> None:
        self.ignore_path.parent.mkdir(parents=True, exist_ok=True)
        self.ignore_path.write_text(
            json.dumps(sorted(self.ignored), ensure_ascii=False, indent=2),
            encoding="utf-8",
        )

    # ---- layout -----------------------------------------------------------
    def _build_ui(self) -> None:
        header = ttk.Frame(self)
        header.pack(fill="x")
        self.progress_label = ttk.Label(header, text="", font=("Segoe UI", 10, "bold"))
        self.progress_label.pack(side="left")
        self.progress_bar = ttk.Progressbar(header, mode="determinate", length=260)
        self.progress_bar.pack(side="right")

        key_frame = ttk.LabelFrame(self, text="Translation key")
        key_frame.pack(fill="x", pady=(self.PADDING, 0))
        self.key_label = ttk.Label(key_frame, text="", font=("Consolas", 11, "bold"))
        self.key_label.pack(side="left", padx=6, pady=6)

        loc_frame = ttk.LabelFrame(self, text="Found in")
        loc_frame.pack(fill="x", pady=(self.PADDING, 0))
        self.file_label = ttk.Label(loc_frame, text="", font=("Consolas", 9), foreground="#0066cc", cursor="hand2")
        self.file_label.pack(side="left", padx=6, pady=4)
        self.file_label.bind("<Button-1>", lambda _e: self._open_file_in_editor())

        ctx_frame = ttk.LabelFrame(self, text="Code context")
        ctx_frame.pack(fill="both", expand=False, pady=(self.PADDING, 0))
        self.context_text = scrolledtext.ScrolledText(ctx_frame, height=6, wrap="none", font=("Consolas", 9))
        self.context_text.pack(fill="both", expand=True, padx=4, pady=4)
        self.context_text.configure(state="disabled")

        # AI suggest bar
        ai_bar = ttk.Frame(self)
        ai_bar.pack(fill="x", pady=(self.PADDING, 0))
        ai_ready = ai_module.is_available()
        self.suggest_btn = ttk.Button(
            ai_bar,
            text="🤖 Suggest translations" if ai_ready else "🤖 Suggest (claude CLI not found)",
            command=self.suggest_ai,
        )
        self.suggest_btn.pack(side="left")
        if not ai_ready:
            self.suggest_btn.configure(state="disabled")

        self._auto_var = tk.BooleanVar(value=False)
        self.auto_check = ttk.Checkbutton(
            ai_bar,
            text="Auto-prefetch next keys",
            variable=self._auto_var,
            command=self._on_auto_toggle,
        )
        self.auto_check.pack(side="left", padx=(12, 0))
        if not ai_ready:
            self.auto_check.configure(state="disabled")

        self._auto_advance_var = tk.BooleanVar(value=False)
        self.auto_advance_check = ttk.Checkbutton(
            ai_bar,
            text=f"Auto save & next ({self._auto_advance_seconds}s)",
            variable=self._auto_advance_var,
            command=self._on_auto_advance_toggle,
        )
        self.auto_advance_check.pack(side="left", padx=(12, 0))
        if not ai_ready:
            self.auto_advance_check.configure(state="disabled")

        self.ai_status = ttk.Label(ai_bar, text="", font=("Segoe UI", 9), foreground="#6366f1")
        self.ai_status.pack(side="left", padx=(10, 0))

        ttk.Button(
            ai_bar,
            text="🔗 Linked packages…",
            command=self.manage_linked_packages,
        ).pack(side="right")

        self.target_label = ttk.Label(
            self, text="", font=("Segoe UI", 9), foreground="#0f766e"
        )
        self.target_label.pack(fill="x", pady=(self.PADDING, 0))

        self.en_frame = ttk.LabelFrame(self, text="English")
        self.en_frame.pack(fill="x", pady=(self.PADDING, 0))
        self.en_var = tk.StringVar()
        self.en_entry = ttk.Entry(self.en_frame, textvariable=self.en_var, font=("Segoe UI", 10))
        self.en_entry.pack(fill="x", padx=6, pady=6)

        self.fr_frame = ttk.LabelFrame(self, text="Français")
        self.fr_frame.pack(fill="x", pady=(self.PADDING, 0))
        self.fr_var = tk.StringVar()
        self.fr_entry = ttk.Entry(self.fr_frame, textvariable=self.fr_var, font=("Segoe UI", 10))
        self.fr_entry.pack(fill="x", padx=6, pady=6)

        # Cancel the auto save-and-next countdown as soon as the user touches either field.
        # Ignore programmatic writes from `render_current` — we detect that via a flag set
        # around those writes. For simplicity here we just cancel any pending countdown;
        # render_current re-arms it immediately after so nothing visible changes.
        self._user_typing_suspend = False

        def _on_field_change(*_a):
            if not self._user_typing_suspend:
                self._cancel_auto_advance("Countdown cancelled (edit detected)")

        self.en_var.trace_add("write", _on_field_change)
        self.fr_var.trace_add("write", _on_field_change)

        btns = ttk.Frame(self)
        btns.pack(fill="x", pady=(self.PADDING, 0))
        self.prev_btn = ttk.Button(btns, text="◀ Prev", command=self.prev)
        self.prev_btn.pack(side="left")
        ttk.Button(btns, text="Skip", command=self.skip).pack(side="left", padx=(6, 0))
        self.ignore_btn = ttk.Button(btns, text="Ignore", command=self.ignore)
        self.ignore_btn.pack(side="left", padx=(6, 0))
        self.save_close_btn = ttk.Button(btns, text="Save && Close", command=self.save_and_close)
        self.save_close_btn.pack(side="right", padx=(6, 0))
        self.save_btn = ttk.Button(btns, text="Save && Next", command=self.save_and_next)
        self.save_btn.pack(side="right")
        ttk.Button(btns, text="Next ▶", command=self.next).pack(side="right", padx=(0, 6))

        # Toast area: auto-dismissing coloured banner for Save/Ignore confirmations.
        self.toast = tk.Label(
            self, text="", font=("Segoe UI", 10, "bold"),
            anchor="w", padx=12, pady=6,
            background=self.cget("background"),
            foreground=self.cget("background"),  # invisible when idle
        )
        self.toast.pack(fill="x", pady=(self.PADDING, 0))
        self._toast_after_id: Optional[str] = None

        self.status_label = ttk.Label(self, text="", font=("Segoe UI", 9))
        self.status_label.pack(fill="x")

        # Footer: session counters + live mtime of the lang files (proof they're being updated).
        footer = ttk.Frame(self)
        footer.pack(fill="x", pady=(6, 0))
        self.session_label = ttk.Label(footer, text="", font=("Segoe UI", 9), foreground="#334155")
        self.session_label.pack(side="left")
        self.files_label = ttk.Label(footer, text="", font=("Consolas", 8), foreground="#64748b")
        self.files_label.pack(side="right")
        self._refresh_footer()

    def _bind_shortcuts(self) -> None:
        self.bind("<Left>", lambda _e: self.prev())
        self.bind("<Right>", lambda _e: self.next())
        self.bind("<Control-s>", lambda _e: self.save_and_next())
        self.bind("<Control-S>", lambda _e: self.save_and_next())
        self.bind("<Control-g>", lambda _e: self.suggest_ai())
        self.bind("<Control-G>", lambda _e: self.suggest_ai())
        self.bind("<Escape>", lambda _e: self.destroy())

    def _show_empty_state(self) -> None:
        self.key_label.configure(text="No missing translations — you're done! 🎉")
        self.file_label.configure(text="")
        self.context_text.configure(state="normal")
        self.context_text.delete("1.0", "end")
        self.context_text.configure(state="disabled")
        self.en_entry.configure(state="disabled")
        self.fr_entry.configure(state="disabled")
        self.prev_btn.configure(state="disabled")
        self.save_btn.configure(state="disabled")
        self.progress_label.configure(text="0 / 0")

    # ---- Link-required guard ---------------------------------------------
    def _show_link_required_dialog(self, vendor_pkgs: list[str]) -> None:
        """Alert the user that the key lives only in vendor/ and must be linked."""
        pkg_lines = "\n".join(f"  • {p}" for p in vendor_pkgs)
        example_add = vendor_pkgs[0] if vendor_pkgs else "vendor/package"
        messagebox.showwarning(
            "Link the package first",
            "This translation key only appears inside vendor packages:\n\n"
            f"{pkg_lines}\n\n"
            "Saving into the main project's lang files would pollute them with "
            "translations that belong to the package.\n\n"
            "Please link your local clone of the package:\n"
            "  • Click '🔗 Linked packages…' in the toolbar, or\n"
            f"  • Run: php artisan app:translator-packages add C:/path/to/{example_add}\n\n"
            "Then click Rescan to pick up the link and retry the save."
        )

    # ---- target project resolution ---------------------------------------
    def _ensure_target_loaded(self, target: Path) -> None:
        """Load (once, cached) the en/fr JSON for a given project root."""
        target = target.resolve()
        if target in self._lang_cache:
            return
        en_path = target / "resources" / "lang" / "en.json"
        fr_path = target / "resources" / "lang" / "fr.json"
        en_data = load_lang(en_path)
        fr_data = load_lang(fr_path)
        self._lang_cache[target] = {
            "en_path": en_path,
            "fr_path": fr_path,
            "en": en_data,
            "fr": fr_data,
            "en_original": dict(en_data),
            "fr_original": dict(fr_data),
        }

    def _resolve_target_project(self, entry: dict) -> Path:
        """Pick the project whose lang files should receive this entry's translation.

        Rule: if ANY of the key's source locations lives inside a user-linked
        package that has its own `resources/lang/`, write there (linked folders
        win over vendor/app). Otherwise fall back to the main app.

        Real vendor/ packages (condoedge/*, kompo/*) are ignored — writing to
        vendor/ would be overwritten by the next composer install.
        """
        candidate_files: list[str] = []
        if entry.get("all_files"):
            candidate_files.extend(entry["all_files"])
        elif entry.get("file"):
            candidate_files.append(entry["file"])

        if not candidate_files:
            return self.project_root

        linked_abs_paths: list[Path] = []
        for linked_str in self._load_linked_packages():
            try:
                p = Path(linked_str).resolve()
                if (p / "resources" / "lang").is_dir():
                    linked_abs_paths.append(p)
            except Exception:
                continue

        if not linked_abs_paths:
            return self.project_root

        for file_rel in candidate_files:
            rel = file_rel.replace("\\", os.sep).replace("/", os.sep)
            file_abs = Path(rel)
            if not file_abs.is_absolute():
                file_abs = (self.project_root / rel).resolve()
            else:
                file_abs = file_abs.resolve()

            for linked_abs in linked_abs_paths:
                try:
                    file_abs.relative_to(linked_abs)
                    return linked_abs
                except ValueError:
                    continue

        return self.project_root

    def _entry_only_in_vendor(self, entry: dict) -> list[str]:
        """Return the list of vendor/<vendor>/<package> names that contain this key
        when NO location in the main app (non-vendor) exists.

        An empty list means at least one location is in the main app — safe to write
        to the project's own lang files.
        """
        files = entry.get("all_files") or ([entry["file"]] if entry.get("file") else [])
        if not files:
            return []

        vendor_packages: set[str] = set()
        for raw in files:
            rel = raw.replace("\\", "/")
            idx = rel.find("vendor/")
            if idx < 0 or (idx > 0 and rel[idx - 1] not in ("", "/", ":")):
                # Not under a vendor/ path → main-app location exists.
                return []
            pieces = rel[idx + len("vendor/"):].split("/", 2)
            if len(pieces) >= 2:
                vendor_packages.add(f"{pieces[0]}/{pieces[1]}")

        return sorted(vendor_packages)

    def _target_label(self, target: Path) -> str:
        """Human-friendly display of where a save will land."""
        try:
            rel = target.relative_to(self.project_root)
            if str(rel) == ".":
                return "main project"
            return f"linked: {rel}"
        except ValueError:
            return f"linked: {target.name}"

    # ---- rendering --------------------------------------------------------
    def _capture_draft_if_dirty(self) -> None:
        """If the current EN/FR fields differ from the persisted values (in the
        entry's target project), stash them so navigating away doesn't lose text."""
        if not self.entries:
            return
        entry = self.entries[self.index]
        key = entry["key"]
        target = self._resolve_target_project(entry)
        self._ensure_target_loaded(target)
        store = self._lang_cache[target]

        en = self.en_var.get().strip()
        fr = self.fr_var.get().strip()
        saved_en = store["en"].get(key, "").strip()
        saved_fr = store["fr"].get(key, "").strip()
        if en != saved_en or fr != saved_fr:
            self._drafts[key] = {"en": en, "fr": fr, "target": str(target)}
        else:
            self._drafts.pop(key, None)

    def render_current(self) -> None:
        if not self.entries:
            self._show_empty_state()
            return

        entry = self.entries[self.index]
        key = entry["key"]
        self.key_label.configure(text=key)

        loc_txt = entry["file"]
        if entry.get("line"):
            loc_txt = f"{loc_txt}:{entry['line']}"
        self.file_label.configure(text=loc_txt or "(no location)")

        self.context_text.configure(state="normal")
        self.context_text.delete("1.0", "end")
        self.context_text.insert("1.0", entry.get("context", ""))
        self.context_text.configure(state="disabled")

        # Resolve which project owns this key, load its lang data if not yet cached.
        target = self._resolve_target_project(entry)
        self._ensure_target_loaded(target)
        store = self._lang_cache[target]

        # Display the resolved target — flag vendor-only keys that need linking.
        vendor_pkgs = (
            self._entry_only_in_vendor(entry) if target == self.project_root else []
        )
        if vendor_pkgs:
            self.target_label.configure(
                text=f"⚠ Link required — key only in: {', '.join(vendor_pkgs)}",
                foreground="#b45309",
            )
        else:
            self.target_label.configure(
                text=f"Will write to: {self._target_label(target)}",
                foreground="#0f766e",
            )

        # Priority: unsaved draft > AI cache > already-saved JSON value > empty
        draft = self._drafts.get(key)
        cached = self._ai_cache.get(key)
        self._user_typing_suspend = True
        try:
            if draft:
                self.en_var.set(draft["en"])
                self.fr_var.set(draft["fr"])
            elif cached:
                self.en_var.set(cached.get("en", ""))
                self.fr_var.set(cached.get("fr", ""))
                self.ai_status.configure(text="🤖 Prefilled from cache — review before saving")
            else:
                self.en_var.set(store["en"].get(key, ""))
                self.fr_var.set(store["fr"].get(key, ""))
                self.ai_status.configure(text="")
        finally:
            self._user_typing_suspend = False

        # Classify → maybe highlight Ignore
        if cached and cached.get("is_translation_key") is False:
            self._highlight_ignore_suggestion()
        else:
            self._reset_ignore_highlight()

        total = len(self.entries)
        self.progress_label.configure(text=f"Key {self.index + 1} / {total}")
        pct = ((self.index + 1) / total) * 100 if total else 0
        self.progress_bar["value"] = pct

        self.prev_btn.configure(state="normal" if self.index > 0 else "disabled")

        self.en_entry.focus_set()
        self.en_entry.icursor("end")
        self.status_label.configure(text="")

        # Possibly start the auto save-and-next countdown.
        self._maybe_start_auto_advance()

    # ---- actions ----------------------------------------------------------
    def _do_save(self) -> Optional[list[str]]:
        """Write the current entry's EN/FR values to the RESOLVED target project.
        Returns the list of saved locales (e.g. ['EN', 'FR']) or None on error."""
        entry = self.entries[self.index]
        en = self.en_var.get().strip()
        fr = self.fr_var.get().strip()

        if not en and not fr:
            messagebox.showwarning(
                "Empty",
                "Both fields are empty — use Skip if you want to move on without saving.",
            )
            return None

        target = self._resolve_target_project(entry)

        # Guard: if the resolved target is the main project but the key ONLY lives
        # in vendor packages, block the save — writing to main would pollute it with
        # translations that belong to the package.
        if target == self.project_root:
            vendor_pkgs = self._entry_only_in_vendor(entry)
            if vendor_pkgs:
                self._show_link_required_dialog(vendor_pkgs)
                return None

        self._ensure_target_loaded(target)
        store = self._lang_cache[target]

        if en:
            store["en"][entry["key"]] = en
        if fr:
            store["fr"][entry["key"]] = fr

        try:
            save_lang(store["en_path"], store["en"], store["en_original"])
            save_lang(store["fr_path"], store["fr"], store["fr_original"])
        except Exception as exc:
            messagebox.showerror("Save error", str(exc))
            return None

        saved = []
        if en:
            saved.append("EN")
        if fr:
            saved.append("FR")

        # Mark matching DB rows as fixed — async so the UI doesn't block.
        self._mark_db_async(entry["key"], "fixed", [loc.lower() for loc in saved])
        self._session["saved"] += 1
        self._refresh_footer()
        return saved

    def save_and_next(self) -> None:
        entry = self.entries[self.index]
        saved = self._do_save()
        if saved is None:
            return

        self._show_toast(
            f"✓ Saved  [{'+'.join(saved)}]  {entry['key']}  →  en.json + fr.json",
            bg="#16a34a",
        )
        self.status_label.configure(
            text=f"Saved [{'+'.join(saved)}] for {entry['key']} · DB updated · files on disk"
        )
        self.next()

    def suggest_ai(self) -> None:
        """Fire a Claude request in a background thread; populate fields when done."""
        if not self.entries:
            return
        if not ai_module.is_available():
            messagebox.showinfo("Not available", "The `claude` CLI was not found on PATH.\nInstall Claude Code and authenticate, then relaunch.")
            return

        entry = self.entries[self.index]
        self.suggest_btn.configure(state="disabled", text="🤖 Asking Claude…")
        self.ai_status.configure(text="Waiting for response (file context included)…")
        self.update_idletasks()

        wider = ai_module.read_wider_context(
            self.project_root,
            entry.get("file", ""),
            entry.get("line"),
            radius=20,
        )

        def worker() -> None:
            try:
                result = ai_module.suggest(
                    key=entry["key"],
                    code_context=entry.get("context", ""),
                    file_path=entry.get("file", ""),
                    wider_context=wider,
                    timeout=90,
                )
                self.after(0, self._on_ai_result, entry["key"], result)
            except Exception as exc:  # AiError or subprocess issue
                self.after(0, self._on_ai_error, str(exc))

        threading.Thread(target=worker, daemon=True).start()

    def _on_ai_result(self, key: str, result: dict) -> None:
        """Called on the Tk main thread when the AI worker finishes."""
        # Always cache the result regardless of navigation.
        self._ai_cache[key] = result
        self._reset_suggest_button()

        # If the user navigated away, don't clobber their current fields.
        if not self.entries or self.entries[self.index]["key"] != key:
            return

        en = result.get("en", "").strip()
        fr = result.get("fr", "").strip()
        self._user_typing_suspend = True
        try:
            if en:
                self.en_var.set(en)
            if fr:
                self.fr_var.set(fr)
        finally:
            self._user_typing_suspend = False

        if result.get("is_translation_key") is False:
            self._highlight_ignore_suggestion()
        else:
            self._reset_ignore_highlight()
            self._show_toast(f"🤖 Suggestion loaded for {key}", bg="#6366f1")
            self.ai_status.configure(text="Suggestion loaded — review then Save")
            # On-demand suggestion may also trigger the auto-advance countdown.
            self._maybe_start_auto_advance()

    def _on_ai_error(self, message: str) -> None:
        self.ai_status.configure(text="")
        self._reset_suggest_button()
        self._show_toast("🤖 AI error — see dialog", bg="#dc2626")
        messagebox.showerror("AI error", message)

    def _reset_suggest_button(self) -> None:
        self.suggest_btn.configure(state="normal", text="🤖 Suggest translations")

    # ---- Linked packages manager ----
    @property
    def _linked_packages_path(self) -> Path:
        return self.project_root / "storage" / "app" / "translator_linked_packages.json"

    def _load_linked_packages(self) -> list[str]:
        path = self._linked_packages_path
        if path.is_file():
            try:
                data = json.loads(path.read_text(encoding="utf-8"))
                if isinstance(data, list):
                    return [str(p) for p in data if isinstance(p, str) and p.strip()]
            except Exception:
                pass
        return []

    def _save_linked_packages(self, paths: list[str]) -> None:
        path = self._linked_packages_path
        path.parent.mkdir(parents=True, exist_ok=True)
        # Deduplicate while preserving order.
        seen, ordered = set(), []
        for p in paths:
            if p not in seen:
                ordered.append(p)
                seen.add(p)
        path.write_text(
            json.dumps(ordered, ensure_ascii=False, indent=2) + "\n",
            encoding="utf-8",
        )

    def manage_linked_packages(self) -> None:
        win = tk.Toplevel(self)
        win.title("Linked packages — scanned for translation keys")
        win.geometry("640x380")
        win.transient(self)
        win.grab_set()

        info = ttk.Label(
            win,
            text=(
                "Folders listed below are scanned alongside app/, resources/, and\n"
                "vendor/condoedge/* / vendor/kompo/*. Useful for local in-dev packages."
            ),
            font=("Segoe UI", 9),
            foreground="#475569",
            justify="left",
        )
        info.pack(fill="x", padx=10, pady=(10, 4))

        list_frame = ttk.LabelFrame(win, text="Currently linked")
        list_frame.pack(fill="both", expand=True, padx=10, pady=(0, 8))

        listbox = tk.Listbox(list_frame, font=("Consolas", 9), activestyle="none")
        listbox.pack(side="left", fill="both", expand=True, padx=6, pady=6)
        sb = ttk.Scrollbar(list_frame, orient="vertical", command=listbox.yview)
        sb.pack(side="right", fill="y")
        listbox.configure(yscrollcommand=sb.set)

        def refresh() -> None:
            listbox.delete(0, "end")
            for p in self._load_linked_packages():
                exists = Path(p).is_dir()
                marker = "  " if exists else "✗ "
                listbox.insert("end", f"{marker}{p}")

        def add_path() -> None:
            chosen = filedialog.askdirectory(
                parent=win,
                title="Pick a local package folder to scan",
                mustexist=True,
            )
            if not chosen:
                return
            chosen_abs = str(Path(chosen).resolve())
            current = self._load_linked_packages()
            if chosen_abs in current:
                messagebox.showinfo("Already linked", f"{chosen_abs}\nis already in the list.")
                return
            current.append(chosen_abs)
            self._save_linked_packages(current)
            refresh()

        def remove_selected() -> None:
            sel = listbox.curselection()
            if not sel:
                return
            current = self._load_linked_packages()
            displayed = [current[i] for i in range(len(current))]
            victims = {displayed[i] for i in sel if i < len(displayed)}
            new_list = [p for p in current if p not in victims]
            self._save_linked_packages(new_list)
            refresh()

        def clear_all() -> None:
            if not self._load_linked_packages():
                return
            if messagebox.askyesno("Clear all", "Remove all linked packages?"):
                self._save_linked_packages([])
                refresh()

        def close_and_maybe_rescan() -> None:
            win.grab_release()
            win.destroy()
            if messagebox.askyesno(
                "Rescan now?",
                "Re-run the analyzer to pick up your linked packages? (~20 s)",
            ):
                self._rescan()

        btns = ttk.Frame(win)
        btns.pack(fill="x", padx=10, pady=(0, 10))
        ttk.Button(btns, text="➕ Add folder…", command=add_path).pack(side="left")
        ttk.Button(btns, text="✖ Remove", command=remove_selected).pack(side="left", padx=(6, 0))
        ttk.Button(btns, text="Clear all", command=clear_all).pack(side="left", padx=(6, 0))
        ttk.Button(btns, text="Done", command=close_and_maybe_rescan).pack(side="right")

        refresh()

    def _rescan(self) -> None:
        """Re-run the analyzer and reload the missing-keys list in place."""
        self._stop_prefetch()
        self.ai_status.configure(text="Rescanning project (~20 s)…")
        self.update_idletasks()

        def worker() -> None:
            try:
                payload = run_analyzer(self.project_root)
                self.after(0, self._on_rescan_done, payload, None)
            except Exception as exc:
                self.after(0, self._on_rescan_done, None, str(exc))

        threading.Thread(target=worker, daemon=True).start()

    def _on_rescan_done(self, payload: Optional[dict], error: Optional[str]) -> None:
        if error:
            self._show_toast(f"Rescan failed: {error[:60]}", bg="#dc2626")
            self.ai_status.configure(text="")
            messagebox.showerror("Rescan error", error)
            return

        self.entries = build_entries(payload or {})
        # Drop AI cache entries for keys that no longer exist.
        keep_keys = {e["key"] for e in self.entries}
        self._ai_cache = {k: v for k, v in self._ai_cache.items() if k in keep_keys}
        self._drafts = {k: v for k, v in self._drafts.items() if k in keep_keys}

        if not self.entries:
            self._show_empty_state()
            self._show_toast("✓ Rescan complete — no missing keys 🎉", bg="#16a34a")
            return

        # Keep index in bounds.
        self.index = min(self.index, len(self.entries) - 1)
        self.title(f"SISC Translation Helper — {len(self.entries)} missing")
        self.render_current()
        self._show_toast(f"✓ Rescan complete — {len(self.entries)} keys", bg="#16a34a")
        self.ai_status.configure(text="")

    # ---- Ignore button highlight (when Claude classifies key as non-translation) ----
    def _highlight_ignore_suggestion(self) -> None:
        style = ttk.Style(self)
        style.configure("Highlight.Ignore.TButton", foreground="#b45309")
        try:
            self.ignore_btn.configure(style="Highlight.Ignore.TButton")
        except Exception:
            pass
        self._show_toast(
            "🤔 Claude suggests: probably not a translation key — consider Ignore",
            bg="#f59e0b",
            duration_ms=3500,
        )

    def _reset_ignore_highlight(self) -> None:
        try:
            self.ignore_btn.configure(style="TButton")
        except Exception:
            pass

    # ---- Auto-prefetch (batch AI suggestions in background) ----
    def _on_auto_toggle(self) -> None:
        if self._auto_var and self._auto_var.get():
            self._start_prefetch()
        else:
            self._stop_prefetch()

    def _start_prefetch(self) -> None:
        if self._prefetch_thread and self._prefetch_thread.is_alive():
            return
        self._prefetch_stop.clear()
        self._prefetch_thread = threading.Thread(target=self._prefetch_worker, daemon=True)
        self._prefetch_thread.start()

    def _stop_prefetch(self) -> None:
        self._prefetch_stop.set()
        self.ai_status.configure(text="Auto-prefetch stopped")

    def _maybe_kick_prefetch(self) -> None:
        """Called after navigation — ensures the worker is still running if auto is on."""
        if self._auto_var and self._auto_var.get():
            self._start_prefetch()

    # ---- Auto save & next -----------------------------------------------
    def _on_auto_advance_toggle(self) -> None:
        if self._auto_advance_var and self._auto_advance_var.get():
            # Toggling on while an entry is already displayed — maybe start the countdown now.
            self._maybe_start_auto_advance()
        else:
            self._cancel_auto_advance("auto-advance disabled")

    def _cancel_auto_advance(self, reason: str = "") -> None:
        if self._auto_advance_after_id is not None:
            try:
                self.after_cancel(self._auto_advance_after_id)
            except Exception:
                pass
            self._auto_advance_after_id = None
        if self._auto_advance_countdown_id is not None:
            try:
                self.after_cancel(self._auto_advance_countdown_id)
            except Exception:
                pass
            self._auto_advance_countdown_id = None
        if reason:
            self.ai_status.configure(text=reason)

    def _maybe_start_auto_advance(self) -> None:
        """Schedule an auto action after N seconds, based on the current entry.

        Auto-action decision tree (when the checkbox is on and the user hasn't typed):
          - vendor-only key with no linked fork → auto-SKIP (rescan after linking will pick it up)
          - Claude classifies as non-translation → auto-IGNORE (marked in DB, never re-proposed)
          - AI suggestion loaded + EN/FR populated → auto-SAVE & NEXT
          - else nothing to auto (idle)

        When the end of the list is reached, save_and_next simply stops advancing,
        letting the user review any remaining entries manually.
        """
        self._cancel_auto_advance()
        if not (self._auto_advance_var and self._auto_advance_var.get()):
            return
        if not self.entries:
            return

        entry = self.entries[self.index]
        key = entry["key"]

        # Respect the user's in-progress edits.
        if key in self._drafts:
            return

        cached = self._ai_cache.get(key)

        # 1. Vendor-only key without a linked fork → skip (we cannot save safely).
        target = self._resolve_target_project(entry)
        if target == self.project_root and self._entry_only_in_vendor(entry):
            self._auto_advance_action = "skip"
            tpl = "⏭ Auto-skip (vendor-only, link the package to save) in {s}s… (any action cancels)"
            self._auto_advance_step(self._auto_advance_seconds, tpl)
            return

        # 2. Classified non-translation → ignore.
        if cached and cached.get("is_translation_key") is False:
            self._auto_advance_action = "ignore"
            tpl = "⊘ Auto-ignore (not a translation key) in {s}s… (any action cancels)"
            self._auto_advance_step(self._auto_advance_seconds, tpl)
            return

        # 3. Suggestion ready and fields filled → save.
        if cached and (self.en_var.get().strip() or self.fr_var.get().strip()):
            self._auto_advance_action = "save"
            tpl = "🤖 Auto save & next in {s}s… (type anything to cancel)"
            self._auto_advance_step(self._auto_advance_seconds, tpl)
            return

        # Otherwise nothing to auto — wait for a cached suggestion to arrive.

    def _auto_advance_step(self, remaining: int, text_template: str) -> None:
        if remaining <= 0:
            self._auto_advance_after_id = None
            self._auto_advance_countdown_id = None
            if not (self._auto_advance_var and self._auto_advance_var.get()):
                return
            if not self.entries:
                return
            action = self._auto_advance_action
            self._auto_advance_action = None
            if action == "save":
                self.save_and_next()
            elif action == "ignore":
                self.ignore()
            elif action == "skip":
                self.skip()
            return
        self.ai_status.configure(text=text_template.format(s=remaining))
        self._auto_advance_countdown_id = self.after(
            1000,
            lambda r=remaining - 1, t=text_template: self._auto_advance_step(r, t),
        )

    def _prefetch_worker(self) -> None:
        """Walk through entries starting from `self.index + 1`, filling `_ai_cache`.
        Stops if the user toggles auto off or closes the window."""
        while not self._prefetch_stop.is_set():
            # Pick the next uncached entry forward of current index.
            target: Optional[dict] = None
            for i in range(self.index + 1, len(self.entries)):
                e = self.entries[i]
                if e["key"] not in self._ai_cache:
                    target = e
                    break
            if target is None:
                self.after(0, lambda: self.ai_status.configure(text="Auto-prefetch: all ahead cached"))
                return

            self.after(0, lambda k=target["key"]: self.ai_status.configure(
                text=f"Auto-prefetch: asking Claude about {k}…"
            ))

            try:
                wider = ai_module.read_wider_context(
                    self.project_root,
                    target.get("file", ""),
                    target.get("line"),
                    radius=20,
                )
                result = ai_module.suggest(
                    key=target["key"],
                    code_context=target.get("context", ""),
                    file_path=target.get("file", ""),
                    wider_context=wider,
                    timeout=90,
                )
                self._ai_cache[target["key"]] = result
                self.after(0, self._on_prefetch_item_done, target["key"])
            except Exception as exc:  # keep the worker alive across failures
                self.after(0, lambda msg=str(exc): self.ai_status.configure(text=f"Prefetch error: {msg[:80]}"))
                # Small backoff to avoid hammering on persistent failure.
                if self._prefetch_stop.wait(5):
                    return

    def _on_prefetch_item_done(self, key: str) -> None:
        cached = len(self._ai_cache)
        self.ai_status.configure(text=f"Auto-prefetch: {cached} key(s) cached")
        # If the cached key matches what's currently shown, and the user hasn't typed anything,
        # auto-fill the fields so they see the suggestion immediately.
        if self.entries and self.entries[self.index]["key"] == key:
            if not self.en_var.get().strip() and not self.fr_var.get().strip():
                self.render_current()  # will also kick the countdown if enabled
            else:
                self._maybe_start_auto_advance()

    def save_and_close(self) -> None:
        """Flush ALL drafts (including the current entry) to JSON + DB, then close."""
        # Capture whatever is in the fields right now.
        self._capture_draft_if_dirty()

        # Stop auto-prefetch before heavy writes.
        self._stop_prefetch()

        # Group drafts per target so we write each project's lang files exactly once.
        per_target: dict[Path, list[tuple[str, str, str]]] = {}  # target -> [(key, en, fr)]
        flushed: list[tuple[str, list[str], Path]] = []
        entry_by_key = {e["key"]: e for e in self.entries}
        skipped_vendor_only: list[tuple[str, list[str]]] = []  # key, vendor_pkgs

        for key, draft in list(self._drafts.items()):
            en = draft.get("en", "").strip()
            fr = draft.get("fr", "").strip()
            if not en and not fr:
                continue
            entry = entry_by_key.get(key)
            if not entry:
                # Stale draft (key no longer in the scan): write to main project.
                target = self.project_root
            else:
                target = self._resolve_target_project(entry)
                if target == self.project_root:
                    vendor_pkgs = self._entry_only_in_vendor(entry)
                    if vendor_pkgs:
                        # Refuse to pollute the main project — queue a warning instead.
                        skipped_vendor_only.append((key, vendor_pkgs))
                        continue
            self._ensure_target_loaded(target)
            per_target.setdefault(target, []).append((key, en, fr))

        if skipped_vendor_only and not per_target:
            # Everything was vendor-only → nothing safe to save, show the guard once.
            pkg_set = sorted({p for _, pkgs in skipped_vendor_only for p in pkgs})
            self._show_link_required_dialog(pkg_set)
            return

        if not per_target:
            if not messagebox.askokcancel(
                "Nothing to save",
                "No drafts have text to save. Close without saving?",
            ):
                return
            self.destroy()
            return

        try:
            for target, items in per_target.items():
                store = self._lang_cache[target]
                for key, en, fr in items:
                    if en:
                        store["en"][key] = en
                    if fr:
                        store["fr"][key] = fr
                    locales = [loc for loc, val in [("EN", en), ("FR", fr)] if val]
                    flushed.append((key, locales, target))
                save_lang(store["en_path"], store["en"], store["en_original"])
                save_lang(store["fr_path"], store["fr"], store["fr_original"])
        except Exception as exc:
            messagebox.showerror("Save error", str(exc))
            return

        # DB updates for every flushed key (fire-and-forget, grouped).
        for key, locales, _target in flushed:
            self._mark_db_async(key, "fixed", [l.lower() for l in locales])
        self._session["saved"] += len(flushed)
        self._refresh_footer()

        # Let fire-and-forget subprocesses start before the app quits.
        self.update_idletasks()
        time.sleep(0.3)

        summary = "\n".join(
            f"  • {k}  [{'+'.join(locs)}]  → {self._target_label(t)}"
            for k, locs, t in flushed[:20]
        )
        if len(flushed) > 20:
            summary += f"\n  … and {len(flushed) - 20} more"

        touched_files: list[str] = []
        for target in per_target.keys():
            store = self._lang_cache[target]
            touched_files.append(f"  • {store['en_path']}")
            touched_files.append(f"  • {store['fr_path']}")

        skipped_lines = ""
        if skipped_vendor_only:
            skipped_lines = (
                "\n\n⚠ Skipped (vendor-only — link the package and retry):\n"
                + "\n".join(f"  • {k}   ({', '.join(pkgs)})" for k, pkgs in skipped_vendor_only[:10])
            )
            if len(skipped_vendor_only) > 10:
                skipped_lines += f"\n  … and {len(skipped_vendor_only) - 10} more"

        messagebox.showinfo(
            "Saved",
            f"Saved {len(flushed)} key(s) in this session:\n\n{summary}\n\n"
            f"Files updated:\n" + "\n".join(touched_files)
            + skipped_lines + "\n\n"
            f"Session totals:\n"
            f"  ✓ {self._session['saved']} saved\n"
            f"  ⊘ {self._session['ignored']} ignored\n"
            f"  → {self._session['skipped']} skipped",
        )
        self.destroy()

    def skip(self) -> None:
        self._cancel_auto_advance()
        entry = self.entries[self.index] if self.entries else None
        if entry:
            self._show_toast(f"→ Skipped  {entry['key']}", bg="#64748b")
        self._session["skipped"] += 1
        self._refresh_footer()
        self.status_label.configure(text="Skipped")
        self.next()

    def ignore(self) -> None:
        self._cancel_auto_advance()
        entry = self.entries[self.index]
        self.ignored.add(entry["key"])
        self._persist_ignored()

        # Mark all DB rows for this key as ignored (no --locale filter → both EN and FR).
        self._mark_db_async(entry["key"], "ignored", [])
        self._session["ignored"] += 1
        self._refresh_footer()

        self._show_toast(
            f"⊘ Ignored  {entry['key']}  ·  DB row marked",
            bg="#d97706",
        )
        self.status_label.configure(
            text=f"Ignored {entry['key']} · DB row marked · pending-ignore={len(self.ignored)}"
        )
        self.next()

    def next(self) -> None:
        self._cancel_auto_advance()
        self._capture_draft_if_dirty()
        if self.index < len(self.entries) - 1:
            self.index += 1
            self.render_current()
            self._maybe_kick_prefetch()
        else:
            # End of list reached — turn off auto mode so the user can review manually.
            if self._auto_advance_var and self._auto_advance_var.get():
                self._auto_advance_var.set(False)
                self._show_toast(
                    "✅ Reached end of list — auto-advance turned off for review",
                    bg="#0f766e",
                    duration_ms=4000,
                )
            self.status_label.configure(text="End of list — review remaining entries manually.")
            self.ai_status.configure(text="")

    def prev(self) -> None:
        self._cancel_auto_advance()
        self._capture_draft_if_dirty()
        if self.index > 0:
            self.index -= 1
            self.render_current()

    # ---- helpers ----------------------------------------------------------
    def _show_toast(self, text: str, bg: str, fg: str = "#ffffff", duration_ms: int = 2200) -> None:
        """Flash a coloured banner that auto-clears after `duration_ms`."""
        if self._toast_after_id is not None:
            try:
                self.after_cancel(self._toast_after_id)
            except Exception:
                pass
            self._toast_after_id = None

        self.toast.configure(text=text, background=bg, foreground=fg)
        self._toast_after_id = self.after(duration_ms, self._clear_toast)

    def _clear_toast(self) -> None:
        default_bg = self.cget("background")
        self.toast.configure(text="", background=default_bg, foreground=default_bg)
        self._toast_after_id = None

    def _file_mtime(self, path: Path) -> str:
        try:
            ts = path.stat().st_mtime
            return datetime.fromtimestamp(ts).strftime("%H:%M:%S")
        except FileNotFoundError:
            return "—"

    def _refresh_footer(self) -> None:
        s = self._session
        counters = f"Session: ✓ {s['saved']} saved  ⊘ {s['ignored']} ignored  → {s['skipped']} skipped"
        self.session_label.configure(text=counters)

        en_m = self._file_mtime(self.en_path)
        fr_m = self._file_mtime(self.fr_path)
        self.files_label.configure(
            text=f"en.json @ {en_m}   fr.json @ {fr_m}"
        )

    def _mark_db_async(self, key: str, status: str, locales: list[str]) -> None:
        """Fire-and-forget artisan call to update the missing_translations table.
        UI stays snappy; errors are silent (by design — JSON is source of truth)."""
        cmd = ["php", "artisan", "app:mark-missing-translation", key, f"--status={status}"]
        for loc in locales:
            cmd.append(f"--locale={loc}")

        creationflags = 0
        if sys.platform == "win32":
            # CREATE_NO_WINDOW = 0x08000000 — avoids a console flash per call.
            creationflags = 0x08000000

        # Prune finished processes to keep the buffer bounded.
        self._async_procs = [p for p in self._async_procs if p.poll() is None]
        proc = subprocess.Popen(
            cmd,
            cwd=str(self.project_root),
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            creationflags=creationflags,
        )
        self._async_procs.append(proc)

    def _open_file_in_editor(self) -> None:
        entry = self.entries[self.index]
        rel = entry.get("file")
        if not rel:
            return
        absolute = self.project_root / rel.replace("\\", os.sep).replace("/", os.sep)
        if not absolute.exists():
            messagebox.showinfo("File not found", f"{absolute}")
            return
        line = entry.get("line") or 1
        # Try VS Code first (via PATH), fall back to OS default.
        try:
            subprocess.Popen(["code", "-g", f"{absolute}:{line}"])
        except FileNotFoundError:
            try:
                os.startfile(str(absolute))  # type: ignore[attr-defined]
            except Exception as exc:
                messagebox.showerror("Open failed", str(exc))


# --------------------------------------------------------------------------- #
# Entry point
# --------------------------------------------------------------------------- #

def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.strip())
    parser.add_argument("json_file", nargs="?", type=Path,
                        help="Existing JSON from analyzer --json (optional).")
    parser.add_argument("--project", type=Path, default=None,
                        help="Project root (auto-detected by default).")
    args = parser.parse_args()

    start = Path(__file__).resolve().parent
    project_root = args.project or find_project_root(start) or find_project_root(Path.cwd())
    if not project_root:
        print("Cannot locate project root (no composer.json + resources/lang found).", file=sys.stderr)
        sys.exit(1)

    try:
        payload = load_missing_payload(args.json_file, project_root)
    except Exception as exc:
        print(f"Failed to load missing keys: {exc}", file=sys.stderr)
        sys.exit(2)

    entries = build_entries(payload)
    app = TranslatorApp(entries, project_root)
    app.mainloop()


if __name__ == "__main__":
    main()
