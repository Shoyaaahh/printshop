"""Shared helpers for the maintenance scripts: read .env and find the database."""
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent


def load_env():
    env = ROOT / ".env"
    if env.is_file():
        for line in env.read_text(encoding="utf-8").splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                k, v = line.split("=", 1)
                os.environ.setdefault(k.strip(), v.strip().strip("\"'"))


def storage_dir() -> Path:
    load_env()
    return Path(os.environ.get("STORAGE_DIR", ROOT / "storage"))


def db_path() -> str:
    load_env()
    return os.environ.get("DB_PATH", str(storage_dir() / "printshop.sqlite"))
