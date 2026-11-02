#!/usr/bin/env python3
"""Delete abandoned temporary uploads (files customers uploaded but never ordered).
    python3 tools/cleanup_tmp.py --hours 24"""
import argparse
import time

from common import storage_dir


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--hours", type=int, default=24)
    a = ap.parse_args()
    tmp = storage_dir() / "uploads" / "tmp"
    cutoff = time.time() - a.hours * 3600
    removed = 0
    if tmp.is_dir():
        for f in tmp.rglob("*"):
            if f.is_file() and f.stat().st_mtime < cutoff:
                f.unlink()
                removed += 1
    print(f"Removed {removed} stale temporary file(s).")


if __name__ == "__main__":
    main()
