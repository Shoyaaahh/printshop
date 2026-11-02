#!/usr/bin/env python3
"""Back up the database and customer uploads into storage/backups/<timestamp>.zip
    python3 tools/backup.py            # create a backup, keep the newest 14
    python3 tools/backup.py --keep 30
Schedule it daily with cron or Windows Task Scheduler."""
import argparse
import sqlite3
import tempfile
import zipfile
from datetime import datetime
from pathlib import Path

from common import db_path, storage_dir


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--keep", type=int, default=14)
    args = ap.parse_args()
    out_dir = storage_dir() / "backups"
    out_dir.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    target = out_dir / f"printshop-{stamp}.zip"

    with tempfile.TemporaryDirectory() as tmp:
        snap = Path(tmp) / "printshop.sqlite"
        src = sqlite3.connect(db_path())
        dst = sqlite3.connect(snap)
        src.backup(dst)          # consistent snapshot even while the site is in use
        dst.close(); src.close()
        with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as z:
            z.write(snap, "printshop.sqlite")
            up = storage_dir() / "uploads"
            for f in up.rglob("*"):
                if f.is_file() and "tmp" not in f.relative_to(up).parts:
                    z.write(f, Path("uploads") / f.relative_to(up))
    backups = sorted(out_dir.glob("printshop-*.zip"))
    for old in backups[:-args.keep]:
        old.unlink()
    print(f"Backup written: {target} ({target.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    main()
