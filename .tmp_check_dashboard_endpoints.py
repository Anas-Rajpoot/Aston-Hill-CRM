from playwright.sync_api import sync_playwright
from urllib.parse import urlparse
import json

BASE_URL = "http://127.0.0.1:8000"
EMAIL = "superadmin@example.com"
PASSWORD = "Password@123"
TARGET_PATHS = [
    "/api/bootstrap",
    "/api/dashboard/stats",
    "/api/dashboard/filters",
    "/api/reports/sla-performance",
    "/api/notifications/poll",
]


def path_of(url: str) -> str:
    return urlparse(url).path


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()
    seen = {}

    def on_response(response):
        req_path = path_of(response.request.url)
        if req_path in TARGET_PATHS and req_path not in seen:
            seen[req_path] = response.status

    page.on("response", on_response)
    page.goto(f"{BASE_URL}/login", wait_until="domcontentloaded", timeout=30000)
    # Simulate a hard refresh before login flow.
    page.reload(wait_until="domcontentloaded", timeout=30000)
    page.fill("input[name='email'], input[type='email'], #email", EMAIL)
    page.fill("input[name='password'], input[type='password'], #password", PASSWORD)

    page.click("button[type='submit']")

    page.wait_for_timeout(3500)
    # Force a fresh reload after login to avoid stale SPA state.
    page.reload(wait_until="networkidle", timeout=30000)

    print("LOGIN_RESPONSE_STATUS", "NOT_CAPTURED")
    print("FINAL_PAGE_URL", page.url)
    print("COOKIES", ",".join([c["name"] for c in context.cookies()]))

    for path in TARGET_PATHS:
        resp = page.request.get(f"{BASE_URL}{path}")
        body = ""
        message = None
        try:
            body = resp.text()
            parsed = json.loads(body)
            if isinstance(parsed, dict):
                message = parsed.get("message")
        except Exception:
            pass
        print(f"API {path} STATUS {resp.status} MESSAGE {message}")

    browser.close()
