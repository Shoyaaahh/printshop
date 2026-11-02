#!/usr/bin/env python3
"""Export a sales report to CSV straight from the database.
    python3 tools/daily_report.py                       # today
    python3 tools/daily_report.py --from 2026-01-01 --to 2026-01-31 --out january.csv
Revenue only counts payments that an administrator verified (status 'paid')."""
import argparse
import csv
import sqlite3
from datetime import date

from common import db_path


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--from", dest="start", default=date.today().isoformat())
    ap.add_argument("--to", dest="end", default=date.today().isoformat())
    ap.add_argument("--out", default=None)
    a = ap.parse_args()
    con = sqlite3.connect(db_path())
    rows = con.execute(
        """SELECT date(p.verified_at) AS day, COUNT(*) AS payments, ROUND(SUM(p.amount), 2) AS revenue
           FROM payments p WHERE p.status = 'paid' AND date(p.verified_at) BETWEEN ? AND ?
           GROUP BY day ORDER BY day""", (a.start, a.end)).fetchall()
    out = a.out or f"sales_{a.start}_to_{a.end}.csv"
    with open(out, "w", newline="", encoding="utf-8") as fh:
        w = csv.writer(fh)
        w.writerow(["date", "verified_payments", "revenue"])
        w.writerows(rows)
        w.writerow(["TOTAL", sum(r[1] for r in rows), round(sum(r[2] for r in rows), 2)])
    print(f"Wrote {out} ({len(rows)} day(s))")


if __name__ == "__main__":
    main()
