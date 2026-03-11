from playwright.sync_api import sync_playwright
from urllib.parse import urlparse
import json

BASE_URL = "http://127.0.0.1:8000"
LOGIN_URL = f"{BASE_URL}/login"
EMAIL = "superadmin@example.com"
PASSWORD = "Password@123"

TARGET_PATHS = [
    "/api/dashboard/stats",
    "/api/dashboard/filters",
    "/api/reports/sla-performance",
    "/api/notifications/poll",
    "/api/bootstrap",
    "/api/me",
]


def normalize_path(url: str) -> str:
    parsed = urlparse(url)
    return parsed.path


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    captured = {}

    def on_response(response):
        req = response.request
        path = normalize_path(req.url)
        if path in TARGET_PATHS and path not in captured:
            headers = req.all_headers()
            cookie = headers.get("cookie", "")
            body_text = ""
            reason_value = None
            try:
                body_text = response.text()
                parsed = json.loads(body_text)
                if isinstance(parsed, dict):
                    reason_value = parsed.get("reason")
            except Exception:
                body_text = ""
            captured[path] = {
                "status": response.status,
                "cookie": cookie,
                "has_laravel_session": (
                    "laravel-session=" in cookie or "laravel_session=" in cookie
                ),
                "url": req.url,
                "body_text": body_text,
                "reason_value": reason_value,
            }

    page.on("response", on_response)

    page.goto(LOGIN_URL, wait_until="domcontentloaded", timeout=30000)

    email_selectors = [
        "input[name='email']",
        "input[type='email']",
        "#email",
        "#fallback_email",
    ]
    password_selectors = [
        "input[name='password']",
        "input[type='password']",
        "#password",
        "#fallback_password",
    ]

    def first_visible(selectors):
        for selector in selectors:
            loc = page.locator(selector)
            if loc.count() > 0 and loc.first.is_visible():
                return loc.first
        return None

    email_input = first_visible(email_selectors)
    password_input = first_visible(password_selectors)
    if email_input is None or password_input is None:
        raise RuntimeError("Could not locate visible login inputs.")

    email_input.fill(EMAIL)
    password_input.fill(PASSWORD)

    submitted = False
    submit_selectors = [
        "button[type='submit']",
        "button:has-text('Sign in')",
        "button:has-text('Log in')",
        "button:has-text('Login')",
    ]
    for selector in submit_selectors:
        btn = page.locator(selector)
        if btn.count() > 0 and btn.first.is_visible():
            btn.first.click()
            submitted = True
            break
    if not submitted:
        password_input.press("Enter")

    # Wait for navigation after login attempt.
    page.wait_for_timeout(2500)

    # Ensure we are on dashboard route if not already.
    if "/dashboard" not in page.url:
        page.goto(f"{BASE_URL}/dashboard", wait_until="domcontentloaded", timeout=30000)

    # Give time for initial dashboard API calls and polling.
    for _ in range(12):
        if len(captured) == len(TARGET_PATHS):
            break
        page.wait_for_timeout(1000)

    # Ensure bootstrap/me statuses are available from the same authenticated session.
    for forced_path in [
        "/api/dashboard/stats",
        "/api/dashboard/filters",
        "/api/bootstrap",
        "/api/me",
    ]:
        if forced_path not in captured:
            resp = page.request.get(f"{BASE_URL}{forced_path}")
            body_text = resp.text()
            reason_value = None
            try:
                parsed = json.loads(body_text)
                if isinstance(parsed, dict):
                    reason_value = parsed.get("reason")
            except Exception:
                pass
            captured[forced_path] = {
                "status": resp.status,
                "cookie": "",
                "has_laravel_session": None,
                "url": f"{BASE_URL}{forced_path}",
                "body_text": body_text,
                "reason_value": reason_value,
            }

    session_cookies = context.cookies()
    session_names = [c["name"] for c in session_cookies]
    print("FINAL_PAGE_URL", page.url)
    print("COOKIES", ",".join(session_names))
    for path in TARGET_PATHS:
        item = captured.get(path)
        if item is None:
            print(f"REQ {path} status=NOT_CAPTURED has_laravel_session=UNKNOWN")
        else:
            print(
                "REQ "
                f"{path} status={item['status']} "
                f"has_laravel_session={item['has_laravel_session']} "
                f"cookie={item['cookie']}"
            )

    for path in ["/api/dashboard/stats", "/api/dashboard/filters"]:
        item = captured.get(path)
        if item is None:
            print(f"BODY {path} not_captured=True")
            continue
        reason_val = item.get("reason_value")
        has_session_terminated_reason = reason_val == "session_terminated"
        print(
            f"BODY {path} reason={reason_val} "
            f"has_reason_session_terminated={has_session_terminated_reason}"
        )
        print(f"BODY_JSON {path} {item.get('body_text', '')}")

    browser.close()
