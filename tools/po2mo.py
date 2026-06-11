#!/usr/bin/env python3
"""Minimal PO -> MO compiler (GNU MO format) with plural support.

Used to compile the SIM Viewer plugin locale catalogs when `msgfmt` is not
available on the build host. Mirrors the subset of the format GLPI/gettext use.
"""
import ast
import struct
import sys
from pathlib import Path


def parse_po(text):
    """Return list of (msgid, msgid_plural|None, [msgstr,...])."""
    entries = []
    cur = {"msgid": None, "plural": None, "strs": {}}
    state = None  # 'msgid' | 'plural' | ('str', index)

    def flush():
        if cur["msgid"] is not None:
            strs = [cur["strs"][k] for k in sorted(cur["strs"])]
            entries.append((cur["msgid"], cur["plural"], strs))

    for raw in text.splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        if line.startswith("msgid_plural"):
            cur["plural"] = ast.literal_eval(line[len("msgid_plural"):].strip())
            state = "plural"
        elif line.startswith("msgid"):
            flush()
            cur = {"msgid": ast.literal_eval(line[len("msgid"):].strip()),
                   "plural": None, "strs": {}}
            state = "msgid"
        elif line.startswith("msgstr["):
            idx = int(line[line.index("[") + 1:line.index("]")])
            val = ast.literal_eval(line[line.index("]") + 1:].strip())
            cur["strs"][idx] = val
            state = ("str", idx)
        elif line.startswith("msgstr"):
            cur["strs"][0] = ast.literal_eval(line[len("msgstr"):].strip())
            state = ("str", 0)
        elif line.startswith('"'):
            chunk = ast.literal_eval(line)
            if state == "msgid":
                cur["msgid"] += chunk
            elif state == "plural":
                cur["plural"] += chunk
            elif isinstance(state, tuple):
                cur["strs"][state[1]] += chunk
    flush()
    return entries


def make_mo(entries):
    keys, vals = [], []
    for msgid, plural, strs in entries:
        if plural is not None:
            key = msgid + "\x00" + plural
            val = "\x00".join(strs)
        else:
            key = msgid
            val = strs[0] if strs else ""
        keys.append(key.encode("utf-8"))
        vals.append(val.encode("utf-8"))

    pairs = sorted(zip(keys, vals), key=lambda kv: kv[0])
    n = len(pairs)
    offsets_o = 7 * 4
    ktab = offsets_o + n * 8
    vtab = ktab + n * 8
    out = bytearray()
    koff, voff = [], []
    blob = bytearray()
    cur = vtab
    for k, _ in pairs:
        koff.append((len(k), cur))
        blob += k + b"\x00"
        cur += len(k) + 1
    for _, v in pairs:
        voff.append((len(v), cur))
        blob += v + b"\x00"
        cur += len(v) + 1

    out += struct.pack("<I", 0x950412DE)  # magic
    out += struct.pack("<I", 0)           # revision
    out += struct.pack("<I", n)
    out += struct.pack("<I", offsets_o)
    out += struct.pack("<I", ktab)
    out += struct.pack("<I", 0)           # hash size
    out += struct.pack("<I", vtab)        # hash offset (unused)
    for length, off in koff:
        out += struct.pack("<II", length, off)
    for length, off in voff:
        out += struct.pack("<II", length, off)
    out += blob
    return bytes(out)


def main():
    locales = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(__file__).resolve().parent.parent / "locales"
    for po in locales.glob("*.po"):
        entries = parse_po(po.read_text(encoding="utf-8"))
        mo = make_mo(entries)
        po.with_suffix(".mo").write_bytes(mo)
        print(f"compiled {po.name} -> {po.stem}.mo ({len(entries)} entries, {len(mo)} bytes)")


if __name__ == "__main__":
    main()
