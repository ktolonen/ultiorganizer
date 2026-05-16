#!/usr/bin/env python3
"""Report PHP function declarations in lib/ and their repository usage counts.

This is a lightweight static scanner. It intentionally avoids requiring PHP
extensions or Composer packages, so it can run in minimal development shells.
It strips comments and string literals for code scanning, extracts named
function declarations, counts direct call sites of the form name(...), and
also tracks string references that may be callback or dynamic-call usage.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
from dataclasses import asdict, dataclass
from pathlib import Path


PHP_IDENTIFIER = r"[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*"
FUNCTION_RE = re.compile(rf"\bfunction\s+&?\s*({PHP_IDENTIFIER})\s*\(", re.IGNORECASE)
CALL_RE = re.compile(rf"\b({PHP_IDENTIFIER})\s*\(", re.IGNORECASE)
METHOD_CALL_RE = re.compile(rf"(?:->|::)\s*({PHP_IDENTIFIER})\s*\(", re.IGNORECASE)
CLASS_LIKE_RE = re.compile(r"\b(?:class|interface|trait)\b", re.IGNORECASE)
STRING_LITERAL = r"""(['"])(?P<name>[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)\1"""
STRING_VALUE_RE = re.compile(
    r"""(['"])(?P<name>[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)\1""",
    re.DOTALL,
)
CALLBACK_FUNCTIONS = (
    "array_filter",
    "array_map",
    "array_reduce",
    "array_walk",
    "array_walk_recursive",
    "call_user_func",
    "call_user_func_array",
    "function_exists",
    "is_callable",
    "preg_replace_callback",
    "register_shutdown_function",
    "register_tick_function",
    "set_error_handler",
    "set_exception_handler",
    "spl_autoload_register",
    "uasort",
    "uksort",
    "usort",
    "xml_set_character_data_handler",
    "xml_set_default_handler",
    "xml_set_element_handler",
    "xml_set_end_namespace_decl_handler",
    "xml_set_external_entity_ref_handler",
    "xml_set_notation_decl_handler",
    "xml_set_processing_instruction_handler",
    "xml_set_start_namespace_decl_handler",
    "xml_set_unparsed_entity_decl_handler",
)
CALLBACK_CALL_RE = re.compile(rf"\b(?:{'|'.join(CALLBACK_FUNCTIONS)})\s*\(", re.IGNORECASE)
SKIP_DIRS = {".git", "dist", "vendor"}


@dataclass(frozen=True)
class FunctionDeclaration:
    name: str
    file: str
    line: int
    kind: str
    direct_usage_count: int = 0
    dynamic_reference_count: int = 0

    @property
    def usage_count(self) -> int:
        return self.direct_usage_count + self.dynamic_reference_count

    def to_dict(self) -> dict[str, int | str]:
        data = asdict(self)
        data["usage_count"] = self.usage_count
        return data


def strip_comments_and_strings(source: str) -> str:
    """Replace comments and quoted strings with spaces, preserving newlines."""
    result: list[str] = []
    i = 0
    length = len(source)
    state = "code"
    quote = ""

    while i < length:
        char = source[i]
        next_char = source[i + 1] if i + 1 < length else ""

        if state == "code":
            if char in ("'", '"'):
                quote = char
                result.append(" ")
                state = "string"
            elif char == "/" and next_char == "/":
                result.extend((" ", " "))
                i += 1
                state = "line_comment"
            elif char == "#":
                result.append(" ")
                state = "line_comment"
            elif char == "/" and next_char == "*":
                result.extend((" ", " "))
                i += 1
                state = "block_comment"
            else:
                result.append(char)
        elif state == "string":
            if char == "\\":
                result.append(" ")
                if next_char:
                    result.append("\n" if next_char == "\n" else " ")
                    i += 1
            elif char == quote:
                result.append(" ")
                state = "code"
            else:
                result.append("\n" if char == "\n" else " ")
        elif state == "line_comment":
            if char == "\n":
                result.append("\n")
                state = "code"
            else:
                result.append(" ")
        elif state == "block_comment":
            if char == "*" and next_char == "/":
                result.extend((" ", " "))
                i += 1
                state = "code"
            else:
                result.append("\n" if char == "\n" else " ")

        i += 1

    return "".join(result)


def strip_comments(source: str) -> str:
    """Replace comments with spaces, preserving strings and newlines."""
    result: list[str] = []
    i = 0
    length = len(source)
    state = "code"
    quote = ""

    while i < length:
        char = source[i]
        next_char = source[i + 1] if i + 1 < length else ""

        if state == "code":
            if char in ("'", '"'):
                quote = char
                result.append(char)
                state = "string"
            elif char == "/" and next_char == "/":
                result.extend((" ", " "))
                i += 1
                state = "line_comment"
            elif char == "#":
                result.append(" ")
                state = "line_comment"
            elif char == "/" and next_char == "*":
                result.extend((" ", " "))
                i += 1
                state = "block_comment"
            else:
                result.append(char)
        elif state == "string":
            result.append(char)
            if char == "\\" and next_char:
                result.append(next_char)
                i += 1
            elif char == quote:
                state = "code"
        elif state == "line_comment":
            if char == "\n":
                result.append("\n")
                state = "code"
            else:
                result.append(" ")
        elif state == "block_comment":
            if char == "*" and next_char == "/":
                result.extend((" ", " "))
                i += 1
                state = "code"
            else:
                result.append("\n" if char == "\n" else " ")

        i += 1

    return "".join(result)


def repo_files(root: Path) -> list[Path]:
    files: list[Path] = []
    for path in root.rglob("*"):
        if path.is_dir():
            continue
        if any(part in SKIP_DIRS for part in path.relative_to(root).parts):
            continue
        if path.suffix.lower() in {".php", ".inc"}:
            files.append(path)
    return sorted(files)


def lib_php_files(root: Path, target: Path | None, recursive: bool) -> list[Path]:
    lib_dir = root / "lib"
    if target is not None:
        resolved = target if target.is_absolute() else root / target
        if not resolved.is_file():
            raise SystemExit(f"error: file does not exist: {target}")
        try:
            resolved.relative_to(lib_dir)
        except ValueError as exc:
            raise SystemExit(f"error: target must be under lib/: {target}") from exc
        return [resolved]

    globber = lib_dir.rglob if recursive else lib_dir.glob
    return sorted(path for path in globber("*.php") if path.is_file())


def find_matching_brace(source: str, opening_offset: int) -> int:
    depth = 0
    for i in range(opening_offset, len(source)):
        if source[i] == "{":
            depth += 1
        elif source[i] == "}":
            depth -= 1
            if depth == 0:
                return i

    return len(source)


def class_like_ranges(source: str) -> list[tuple[int, int]]:
    ranges: list[tuple[int, int]] = []
    for match in CLASS_LIKE_RE.finditer(source):
        opening_offset = source.find("{", match.end())
        if opening_offset == -1:
            continue
        ranges.append((opening_offset, find_matching_brace(source, opening_offset)))

    return ranges


def declaration_kind(offset: int, ranges: list[tuple[int, int]]) -> str:
    for start, end in ranges:
        if start < offset < end:
            return "method"

    return "function"


def extract_functions(root: Path, path: Path) -> list[FunctionDeclaration]:
    source = path.read_text(encoding="utf-8", errors="replace")
    stripped = strip_comments_and_strings(source)
    method_ranges = class_like_ranges(stripped)
    rel_path = path.relative_to(root).as_posix()
    declarations: list[FunctionDeclaration] = []

    for match in FUNCTION_RE.finditer(stripped):
        declarations.append(
            FunctionDeclaration(
                name=match.group(1),
                file=rel_path,
                line=stripped.count("\n", 0, match.start()) + 1,
                kind=declaration_kind(match.start(), method_ranges),
            )
        )

    return declarations


def previous_word(source: str, offset: int) -> str:
    i = offset - 1
    while i >= 0 and source[i].isspace():
        i -= 1

    end = i + 1
    while i >= 0 and re.match(r"[A-Za-z0-9_\x80-\xff]", source[i]):
        i -= 1

    return source[i + 1 : end].lower()


def is_direct_call(source: str, offset: int) -> bool:
    prefix = source[max(0, offset - 2) : offset]
    if prefix in {"->", "::"}:
        return False

    return previous_word(source, offset) not in {"function", "new"}


def count_direct_calls(stripped_sources: list[str]) -> dict[str, int]:
    counts: dict[str, int] = {}

    for source in stripped_sources:
        for match in CALL_RE.finditer(source):
            if is_direct_call(source, match.start()):
                name = match.group(1).lower()
                counts[name] = counts.get(name, 0) + 1

    return counts


def count_method_calls(stripped_sources: list[str]) -> dict[str, int]:
    counts: dict[str, int] = {}

    for source in stripped_sources:
        for match in METHOD_CALL_RE.finditer(source):
            name = match.group(1).lower()
            counts[name] = counts.get(name, 0) + 1

    return counts


def call_argument_span(source: str, opening_paren_offset: int) -> str:
    depth = 0
    for i in range(opening_paren_offset, len(source)):
        if source[i] == "(":
            depth += 1
        elif source[i] == ")":
            depth -= 1
            if depth == 0:
                return source[opening_paren_offset + 1 : i]
        elif source[i] == ";":
            return source[opening_paren_offset + 1 : i]

    return source[opening_paren_offset + 1 :]


def count_dynamic_references(comment_stripped_sources: list[str]) -> dict[str, int]:
    counts: dict[str, int] = {}

    for source in comment_stripped_sources:
        for match in CALLBACK_CALL_RE.finditer(source):
            opening_paren_offset = source.find("(", match.start())
            if opening_paren_offset == -1:
                continue

            arguments = call_argument_span(source, opening_paren_offset)
            for string_match in STRING_VALUE_RE.finditer(arguments):
                name = string_match.group("name").lower()
                counts[name] = counts.get(name, 0) + 1

    return counts


def analyze(root: Path, target: Path | None, recursive: bool) -> list[FunctionDeclaration]:
    declarations: list[FunctionDeclaration] = []
    for path in lib_php_files(root, target, recursive):
        declarations.extend(extract_functions(root, path))

    sources = [path.read_text(encoding="utf-8", errors="replace") for path in repo_files(root)]
    stripped_sources = [strip_comments_and_strings(source) for source in sources]
    direct_usage_counts = count_direct_calls(stripped_sources)
    method_usage_counts = count_method_calls(stripped_sources)
    dynamic_reference_counts = count_dynamic_references([strip_comments(source) for source in sources])

    return [
        FunctionDeclaration(
            name=declaration.name,
            file=declaration.file,
            line=declaration.line,
            kind=declaration.kind,
            direct_usage_count=(
                method_usage_counts.get(declaration.name.lower(), 0)
                if declaration.kind == "method"
                else direct_usage_counts.get(declaration.name.lower(), 0)
            ),
            dynamic_reference_count=dynamic_reference_counts.get(declaration.name.lower(), 0),
        )
        for declaration in declarations
    ]


def print_text_report(declarations: list[FunctionDeclaration], limit: int) -> None:
    if not declarations:
        print("No functions found.")
        return

    most_used = sorted(
        declarations,
        key=lambda declaration: (-declaration.usage_count, declaration.name.lower(), declaration.file),
    )
    dead_code = sorted(
        [
            declaration
            for declaration in declarations
            if declaration.kind == "function" and declaration.usage_count == 0
        ],
        key=lambda declaration: (declaration.file, declaration.line, declaration.name.lower()),
    )
    unused_methods = sorted(
        [
            declaration
            for declaration in declarations
            if declaration.kind == "method" and declaration.usage_count == 0
        ],
        key=lambda declaration: (declaration.file, declaration.line, declaration.name.lower()),
    )

    print(f"Functions analyzed: {len(declarations)}")
    print(f"Global dead-code candidates: {len(dead_code)}")
    print(f"Method candidates excluded from global dead-code list: {len(unused_methods)}")
    print()
    print(f"Most used functions (top {min(limit, len(most_used))}):")
    for declaration in most_used[:limit]:
        print(
            f"{declaration.usage_count:5d}  "
            f"direct={declaration.direct_usage_count:<4d} "
            f"dynamic={declaration.dynamic_reference_count:<4d} "
            f"{declaration.kind:<8s} "
            f"{declaration.name}  {declaration.file}:{declaration.line}"
        )

    print()
    print(f"Global dead-code candidates (first {min(limit, len(dead_code))}):")
    for declaration in dead_code[:limit]:
        print(f"{declaration.name}  {declaration.file}:{declaration.line}")

    print()
    print(f"Unused method candidates (first {min(limit, len(unused_methods))}):")
    for declaration in unused_methods[:limit]:
        print(f"{declaration.name}  {declaration.file}:{declaration.line}")


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Extract function declarations from lib/ PHP files and count direct repo usage."
    )
    parser.add_argument(
        "file",
        nargs="?",
        type=Path,
        help="Optional lib/*.php file to analyze. Omit to analyze all lib/**/*.php files.",
    )
    parser.add_argument(
        "--root",
        type=Path,
        default=Path.cwd(),
        help="Repository root. Defaults to the current directory.",
    )
    parser.add_argument(
        "--format",
        choices=("text", "json"),
        default="text",
        help="Output format.",
    )
    parser.add_argument(
        "--recursive",
        action="store_true",
        help="Analyze lib/**/*.php. By default only top-level lib/*.php files are analyzed.",
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=25,
        help="Number of most-used and dead-code rows to print in text output.",
    )
    args = parser.parse_args()

    root = args.root.resolve()
    declarations = analyze(root, args.file, args.recursive)

    if args.format == "json":
        print(json.dumps([declaration.to_dict() for declaration in declarations], indent=2))
    else:
        print_text_report(declarations, args.limit)

    return 0


if __name__ == "__main__":
    sys.exit(main())
