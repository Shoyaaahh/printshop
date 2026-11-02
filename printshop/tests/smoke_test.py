#!/usr/bin/env python3
"""End-to-end smoke test. Starts PHP's built-in server with a TEMPORARY database and walks through
the whole business flow: register -> order -> review -> price change -> payment -> printing -> pickup.
Also checks the main security rules. Run from the project folder:   python3 tests/smoke_test.py"""
import http.cookiejar, io, json, os, re, shutil, subprocess, sys, tempfile, time, urllib.error, urllib.parse, urllib.request, uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PORT = int(os.environ.get("TEST_PORT", "8765"))
BASE = f"http://127.0.0.1:{PORT}"
tmp = Path(tempfile.mkdtemp(prefix="printshop-test-"))
env = dict(os.environ, DB_PATH=str(tmp / "t.sqlite"), STORAGE_DIR=str(tmp / "storage"), APP_DEBUG="1")
PASS = FAIL = 0


def check(name, cond, extra=""):
    global PASS, FAIL
    if cond: PASS += 1; print(f"  ok   {name}")
    else: FAIL += 1; print(f"  FAIL {name} {extra}")


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None


class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.op_nr = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect)

    def _do(self, req, follow=True):
        try:
            r = (self.op if follow else self.op_nr).open(req, timeout=60)
            return r.status, r.read(), r.headers
        except urllib.error.HTTPError as e:
            return e.code, e.read(), e.headers

    def get(self, path, follow=True):
        return self._do(urllib.request.Request(BASE + path), follow)

    def post(self, path, data=None, files=None, json_body=None, headers=None, follow=True):
        h = dict(headers or {})
        if json_body is not None:
            body = json.dumps(json_body).encode(); h["Content-Type"] = "application/json"
        elif files:
            b = uuid.uuid4().hex; buf = io.BytesIO()
            for k, v in (data or {}).items(): buf.write(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
            for k, (fn, content, ct) in files.items():
                buf.write(f'--{b}\r\nContent-Disposition: form-data; name="{k}"; filename="{fn}"\r\nContent-Type: {ct}\r\n\r\n'.encode()); buf.write(content); buf.write(b"\r\n")
            buf.write(f"--{b}--\r\n".encode()); body = buf.getvalue(); h["Content-Type"] = f"multipart/form-data; boundary={b}"
        else:
            body = urllib.parse.urlencode(data or {}).encode()
        return self._do(urllib.request.Request(BASE + path, data=body, headers=h, method="POST"), follow)

    def csrf(self, path="/login.php"):
        s, b, _ = self.get(path); m = re.search(rb'name="_csrf" value="([a-f0-9]+)"', b) or re.search(rb'"csrf":"([a-f0-9]+)"', b)
        return m.group(1).decode() if m else ""


def tiny_pdf(pages=3):
    objs = ["<< /Type /Catalog /Pages 2 0 R >>", f"<< /Type /Pages /Kids [{' '.join(f'{3+i} 0 R' for i in range(pages))}] /Count {pages} >>"]
    objs += ["<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >>"] * pages
    out = b"%PDF-1.4\n"; offs = []
    for i, o in enumerate(objs, 1): offs.append(len(out)); out += f"{i} 0 obj\n{o}\nendobj\n".encode()
    x = len(out); out += f"xref\n0 {len(objs)+1}\n0000000000 65535 f \n".encode()
    for o in offs: out += f"{o:010d} 00000 n \n".encode()
    return out + f"trailer\n<< /Size {len(objs)+1} /Root 1 0 R >>\nstartxref\n{x}\n%%EOF\n".encode()


PNG = bytes.fromhex("89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000d49444154789c6360000002000001e221bc330000000049454e44ae426082")


def main():
    subprocess.run(["php", str(ROOT / "database/install.php"), "--reset", "--admin-email=admin@test.local", "--admin-password=Adm1nPass!"], env=env, check=True, stdout=subprocess.DEVNULL)
    srv = subprocess.Popen(["php", "-S", f"127.0.0.1:{PORT}", "-t", str(ROOT / "public")], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    time.sleep(1.2)
    try: run()
    finally:
        srv.terminate(); shutil.rmtree(tmp, ignore_errors=True)
    print(f"\n{PASS} passed, {FAIL} failed"); sys.exit(1 if FAIL else 0)


def run():
    print("Public pages")
    anon = Client()
    for p in ["/", "/services.php", "/pricing.php", "/about.php", "/contact.php", "/track.php", "/login.php", "/register.php", "/admin/login.php"]:
        s, b, _ = anon.get(p); check(f"GET {p} -> 200", s == 200 and b"<html" in b, s)
    s, _, h = anon.get("/customer/dashboard.php", follow=False); check("customer area redirects anonymous users to login", s == 302 and "login.php" in h["Location"])
    s, _, h = anon.get("/admin/index.php", follow=False); check("admin area redirects anonymous users to admin login", s == 302 and "admin/login.php" in h["Location"])

    print("Registration & login")
    cust = Client(); t = cust.csrf("/register.php")
    s, b, _ = cust.post("/register.php", {"_csrf": "bad", "full_name": "X"}); check("POST without valid CSRF token is rejected", s == 419)
    s, b, _ = cust.post("/register.php", {"_csrf": t, "full_name": "Ana Cruz", "email": "ana@test.local", "phone": "0917 123 4567", "password": "short", "password_confirm": "short"})
    check("weak password rejected", b"at least 8" in b)
    s, b, _ = cust.post("/register.php", {"_csrf": t, "full_name": "Ana Cruz", "email": "ana@test.local", "phone": "0917 123 4567", "password": "Passw0rd1", "password_confirm": "Passw0rd1"})
    check("registration works and lands on dashboard", b"Welcome back, Ana Cruz" in b)
    other = Client(); t2 = other.csrf("/register.php")
    other.post("/register.php", {"_csrf": t2, "full_name": "Ben Reyes", "email": "ben@test.local", "phone": "0917 765 4321", "password": "Passw0rd1", "password_confirm": "Passw0rd1"})
    s, _, h = cust.get("/admin/index.php", follow=False); check("customer cannot open admin dashboard", s == 302 and "admin" not in h["Location"].replace("/admin/login", "x") or "customer" in h["Location"])
    adm = Client(); t = adm.csrf("/login.php")
    s, b, _ = adm.post("/login.php", {"_csrf": t, "email": "admin@test.local", "password": "Adm1nPass!"}); check("admin cannot log in through the customer form", b"Incorrect email or password" in b)
    t = adm.csrf("/admin/login.php"); s, b, _ = adm.post("/admin/login.php", {"_csrf": t, "email": "admin@test.local", "password": "Adm1nPass!"}); check("admin login works", b"Dashboard" in b and b"Total Revenue" in b)

    print("Order creation")
    tok = cust.csrf("/customer/order_new.php?service=black-white-printing"); H = {"X-CSRF-Token": tok}
    s, b, _ = cust.post("/api/price.php", json_body={"service_id": 1, "size": "A4", "paper": "Regular", "color": "bw", "sides": "single", "pages": 10, "copies": 2, "binding": "none"}, headers=H)
    pr = json.loads(b); check("price API: 10 pages x 2 copies x P2 = P40", pr["ok"] and pr["total"] == 40.0, pr)
    s, b, _ = cust.post("/api/price.php", json_body={"service_id": 1, "size": "A4", "paper": "Regular", "color": "color", "sides": "single", "pages": 10, "copies": 1}, headers=H)
    check("price API rejects options the service does not offer", not json.loads(b)["ok"])
    s, b, _ = cust.post("/api/upload.php", files={"file": ("evil.php", b"<?php echo 1;", "application/x-php")}, data={"_csrf": tok}); check("upload rejects .php files", json.loads(b)["ok"] is False)
    s, b, _ = cust.post("/api/upload.php", files={"file": ("fake.pdf", b"<?php echo 1;", "application/pdf")}, data={"_csrf": tok}); check("upload rejects content that does not match extension", json.loads(b)["ok"] is False)
    s, b, _ = cust.post("/api/upload.php", files={"file": ("thesis.pdf", tiny_pdf(3), "application/pdf")}, data={"_csrf": tok}); up = json.loads(b)
    check("PDF uploads and Python helper detects 3 pages", up["ok"] and up["pages"] == 3, up)
    s, b, _ = cust.post("/api/order_submit.php", json_body={"service_id": 1, "size": "A4", "paper": "Regular", "color": "bw", "sides": "double", "pages": 3, "copies": 2, "binding": "spiral", "files": [up["token"]], "notes": "Staple please", "payment_method": "gcash", "total": 1}, headers=H)
    od = json.loads(b); ref = od.get("reference", ""); check("order submitted, reference generated", od["ok"] and re.match(r"PS-\d{6}-[A-Z0-9]{6}$", ref), od)
    check("server computed the price itself (12 pages x P2 -10% + 2x45 binding = 100.80)", abs(od["total"] - 100.80) < 0.01, od)
    s, b, _ = cust.post("/api/order_submit.php", json_body={"service_id": 1, "size": "A4", "paper": "Regular", "color": "bw", "sides": "single", "pages": 3, "copies": 1, "files": []}, headers=H)
    check("order without required file is rejected", json.loads(b)["ok"] is False)

    print("Tracking & isolation")
    s, b, _ = anon.get(f"/track.php?ref={ref}"); check("public tracking shows the order", ref.encode() in b and b"Awaiting Shop Review" in b)
    check("public tracking does not leak customer details", b"Ana Cruz" not in b and b"ana@test.local" not in b)
    s, b, _ = anon.get("/track.php?ref=PS-000000-XXXXXX"); check("unknown reference handled", b"No order found" in b)
    s, _, h = other.get(f"/customer/order_view.php?ref={ref}", follow=False); check("another customer cannot open this order", s == 302 and "orders.php" in h["Location"])
    s, b, _ = other.get("/download.php?id=1"); check("another customer cannot download the file", s == 404)
    s, b, _ = anon.get("/download.php?id=1"); check("anonymous cannot download the file", s == 401)
    s, b, _ = anon.get("/storage/printshop.sqlite"); check("database file is not reachable through the web root", not b.startswith(b"SQLite format"))

    print("Admin review & price change")
    s, b, _ = adm.get("/admin/orders.php?status=awaiting_review"); oid = int(re.search(rb"order_view.php\?id=(\d+)", b).group(1))
    t = adm.csrf(f"/admin/order_view.php?id={oid}")
    s, b, _ = adm.get(f"/download.php?id=1"); check("admin can download customer file", s == 200 and b.startswith(b"%PDF"))
    s, b, _ = adm.post(f"/admin/order_view.php?id={oid}", {"_csrf": t, "action": "status", "status": "processing", "note": ""}); check("cannot skip workflow steps", b"Cannot move" in b)
    s, b, _ = adm.post(f"/admin/order_view.php?id={oid}", {"_csrf": t, "action": "approve", "final_total": "120.00", "note": "Extra pages", "completion": ""}); check("approve with changed price -> awaiting customer confirmation", b"Awaiting Customer Confirmation" in b)
    s, b, _ = cust.get(f"/customer/order_view.php?ref={ref}"); check("customer sees price-change notice", b"Price changed" in b and b"120.00" in b)
    t = cust.csrf(f"/customer/order_view.php?ref={ref}")
    s, b, _ = cust.post(f"/customer/order_view.php?ref={ref}", {"_csrf": t, "action": "accept_price"}); check("customer accepts new price -> pending payment", b"Pending Payment" in b)
    s, b, _ = cust.get("/customer/notifications.php"); check("customer was notified of price change", b"Estimated price changed" in b)

    print("Payment verification")
    t = cust.csrf(f"/customer/order_view.php?ref={ref}")
    s, b, _ = cust.post(f"/customer/order_view.php?ref={ref}", {"_csrf": t, "action": "pay", "method": "gcash", "amount": "120", "reference_no": "GC123456"}, files={"receipt": ("r.png", PNG, "image/png")})
    check("payment submitted -> pending verification (not paid)", b"Pending Verification" in b and b">Paid<" not in b)
    t = adm.csrf(f"/admin/order_view.php?id={oid}")
    s, b, _ = adm.post(f"/admin/order_view.php?id={oid}", {"_csrf": t, "action": "status", "status": "processing", "note": ""}); check("cannot process before payment is verified (shop policy)", b"Payment must be verified" in b)
    s, b, _ = adm.get(f"/admin/payments.php?status=pending_verification"); pid = int(re.search(rb'name="payment_id" value="(\d+)"', b).group(1))
    s, b, _ = adm.post("/admin/payments.php", {"_csrf": t, "action": "verify", "payment_id": pid}); check("admin verifies payment", b"Payment verified" in b or b"OR-" in b)
    s, b, _ = adm.get(f"/admin/order_view.php?id={oid}"); check("order moved to Payment Verified automatically", b"Payment Verified" in b)

    print("Production & pickup")
    for step in ["processing", "printing", "quality_checking", "ready_for_pickup"]:
        t = adm.csrf(f"/admin/order_view.php?id={oid}"); s, b, _ = adm.post(f"/admin/order_view.php?id={oid}", {"_csrf": t, "action": "status", "status": step, "note": ""})
        check(f"status -> {step}", ORDER[step].encode() in b)
    s, b, _ = adm.post(f"/admin/order_view.php?id={oid}", {"_csrf": t, "action": "status", "status": "completed", "note": ""}); check("'Completed' cannot be set directly", b"only be completed by confirming" in b)
    s, b, _ = cust.get("/customer/notifications.php"); check("customer notified order is ready", b"Ready for pickup" in b)
    t = adm.csrf("/admin/pickups.php"); s, b, _ = adm.post("/admin/pickups.php", {"_csrf": t, "action": "pickup", "order_id": oid, "picked_up_by": "Ana Cruz"}); check("pickup confirmation completes the order", b"Pickup confirmed" in b)
    s, b, _ = anon.get(f"/track.php?ref={ref}"); check("tracking shows Completed", b"Completed" in b)

    print("Reports & settings")
    s, b, _ = adm.get("/admin/reports.php?type=sales_day&export=csv&from=2000-01-01&to=2099-01-01"); check("CSV export works and counts verified revenue", s == 200 and b"120.00" in b, b[:200])
    for p in ["/admin/index.php", "/admin/orders.php", "/admin/services.php", "/admin/pricing.php", "/admin/customers.php", "/admin/payments.php", "/admin/pickups.php", "/admin/reports.php", "/admin/settings.php", "/admin/messages.php", "/admin/audit.php", "/admin/notifications.php", "/customer/orders.php", "/customer/payments.php", "/customer/profile.php", "/customer/order_new.php"]:
        c = cust if p.startswith("/customer") else adm; s, b, _ = c.get(p); check(f"GET {p} -> 200", s == 200 and b"Fatal error" not in b and b"Warning:" not in b, s)
    t = adm.csrf("/admin/pricing.php"); s, b, _ = adm.post("/admin/pricing.php", {"_csrf": t, "rule[1]": "3.00"}); 
    s, b, _ = cust.post("/api/price.php", json_body={"service_id": 1, "size": "A4", "paper": "Regular", "color": "bw", "sides": "single", "pages": 10, "copies": 1}, headers={"X-CSRF-Token": cust.csrf("/customer/order_new.php")})
    check("calculator uses updated admin pricing (10 pages x P3 = P30)", json.loads(b)["total"] == 30.0, b)


ORDER = {"processing": "Processing", "printing": "Printing", "quality_checking": "Quality Checking", "ready_for_pickup": "Ready for Pickup"}
if __name__ == "__main__": main()
