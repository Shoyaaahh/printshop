#!/usr/bin/env python3
"""Send queued notification emails (table email_queue) through your SMTP server.

  1. In Admin → Settings tick "Send email notifications".
  2. Fill SMTP_* in .env
  3. Run this script on a schedule (cron / Windows Task Scheduler), e.g. every minute:
         python3 tools/send_emails.py
"""
import os
import smtplib
import sqlite3
import ssl
import sys
from datetime import datetime
from email.message import EmailMessage
from pathlib import Path

from common import db_path, load_env


def main():
    load_env()
    host = os.environ.get("SMTP_HOST")
    if not host:
        print("SMTP_HOST is not configured in .env - nothing sent.")
        return 1
    port = int(os.environ.get("SMTP_PORT", "587"))
    user = os.environ.get("SMTP_USER", "")
    password = os.environ.get("SMTP_PASSWORD", "")
    sender = os.environ.get("SMTP_FROM", user)
    security = os.environ.get("SMTP_SECURITY", "starttls").lower()  # starttls | ssl | none

    con = sqlite3.connect(db_path())
    con.row_factory = sqlite3.Row
    rows = con.execute("SELECT * FROM email_queue WHERE status='pending' ORDER BY id LIMIT 50").fetchall()
    if not rows:
        print("No pending emails.")
        return 0
    ctx = ssl.create_default_context()
    try:
        server = smtplib.SMTP_SSL(host, port, context=ctx, timeout=30) if security == "ssl" else smtplib.SMTP(host, port, timeout=30)
        if security == "starttls":
            server.starttls(context=ctx)
        if user:
            server.login(user, password)
    except Exception as exc:  # connection problem: leave messages pending and retry next run
        print("SMTP connection failed:", exc)
        return 2
    sent = failed = 0
    with server:
        for r in rows:
            msg = EmailMessage()
            msg["From"], msg["To"], msg["Subject"] = sender, r["to_email"], r["subject"]
            msg.set_content(r["body"])
            try:
                server.send_message(msg)
                con.execute("UPDATE email_queue SET status='sent', sent_at=? WHERE id=?", (datetime.now().strftime("%Y-%m-%d %H:%M:%S"), r["id"]))
                sent += 1
            except Exception as exc:
                con.execute("UPDATE email_queue SET status='failed', error=? WHERE id=?", (str(exc)[:300], r["id"]))
                failed += 1
            con.commit()
    print(f"Sent {sent}, failed {failed}.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
