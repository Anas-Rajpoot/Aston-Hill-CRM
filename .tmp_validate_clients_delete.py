from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError
import re
import json
from urllib.parse import unquote

BASE_URL = "http://127.0.0.1:8000"
LOGIN_URL = f"{BASE_URL}/login"
CLIENTS_URL = f"{BASE_URL}/clients"
EMAIL = "superadmin@example.com"
PASSWORD = "Password@123"


def text_or_empty(locator):
    try:
        if locator.count() > 0:
            return locator.first.inner_text().strip()
    except Exception:
        pass
    return ""


def get_rows(page):
    rows = page.locator("table tbody tr")
    data_rows = []
    for i in range(rows.count()):
        row = rows.nth(i)
        txt = row.inner_text().strip()
        if "No clients found." in txt:
            continue
        data_rows.append(row)
    return data_rows


def get_toast_text(page, timeout_ms=6000):
    alert = page.locator("[role='alert']")
    try:
        alert.first.wait_for(state="visible", timeout=timeout_ms)
    except PlaywrightTimeoutError:
        return ""
    return alert.first.inner_text().strip()


def wait_for_table_settle(page):
    # Wait briefly for Vue render/API settle without blocking forever.
    page.wait_for_timeout(1200)
    page.wait_for_load_state("networkidle")
    page.wait_for_timeout(1000)


def extract_otp_from_modal(page):
    modal = page.locator(".fixed.inset-0.z-\\[100\\]")
    modal.wait_for(state="visible", timeout=5000)
    modal_text = modal.inner_text()
    match = re.search(r"\b(\d{6})\b", modal_text)
    if not match:
        return None
    return match.group(1)


def login(page):
    # Open login page as requested, then authenticate via API route used by SPA.
    # The fallback Blade form in this environment can render with an empty CSRF token.
    page.goto(LOGIN_URL, wait_until="domcontentloaded", timeout=30000)
    page.wait_for_timeout(800)

    page.context.request.get(f"{BASE_URL}/sanctum/csrf-cookie", timeout=20000)
    xsrf_cookie = ""
    for cookie in page.context.cookies():
        if cookie.get("name") == "XSRF-TOKEN":
            xsrf_cookie = cookie.get("value") or ""
            break
    if not xsrf_cookie:
        raise RuntimeError("Could not get XSRF-TOKEN cookie before login.")

    resp = page.context.request.post(
        f"{BASE_URL}/api/auth/login",
        data={"email": EMAIL, "password": PASSWORD},
        headers={
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-XSRF-TOKEN": unquote(xsrf_cookie),
        },
        timeout=20000,
    )
    if not resp.ok:
        raise RuntimeError(f"API login failed with status {resp.status}: {resp.text()[:250]}")


def run_single_delete(page):
    result = {
        "attempted": False,
        "pre_rows": 0,
        "post_rows": 0,
        "target_row_text": "",
        "otp": "",
        "toast_text": "",
        "row_removed": False,
        "error": "",
    }

    wait_for_table_settle(page)
    rows = get_rows(page)
    result["pre_rows"] = len(rows)
    if not rows:
        return result

    result["attempted"] = True
    target_row = rows[0]
    result["target_row_text"] = target_row.inner_text().strip().replace("\n", " | ")

    delete_btn = target_row.locator("button[title='Delete']")
    if delete_btn.count() == 0:
        result["error"] = "Delete icon not found on first row."
        return result
    delete_btn.first.click()

    otp = extract_otp_from_modal(page)
    result["otp"] = otp or ""
    if not otp:
        result["error"] = "OTP not visible in delete modal."
        return result

    page.locator("input[placeholder='Type OTP here']").first.fill(otp)
    page.locator(".fixed.inset-0.z-\\[100\\] button:has-text('Delete')").first.click()

    result["toast_text"] = get_toast_text(page)
    wait_for_table_settle(page)
    post_rows = get_rows(page)
    result["post_rows"] = len(post_rows)
    result["row_removed"] = result["post_rows"] < result["pre_rows"]
    return result


def run_bulk_delete(page):
    result = {
        "attempted": False,
        "selected_count": 0,
        "pre_rows": 0,
        "post_rows": 0,
        "otp": "",
        "toast_text": "",
        "rows_removed_count": 0,
        "error": "",
    }

    wait_for_table_settle(page)
    rows = get_rows(page)
    result["pre_rows"] = len(rows)
    if not rows:
        return result

    # Select at least 2 rows when available, otherwise all available.
    to_select = min(2, len(rows)) if len(rows) >= 2 else len(rows)
    if to_select <= 0:
        return result

    for i in range(to_select):
        checkbox = rows[i].locator("td input[type='checkbox']").first
        checkbox.check(force=True)

    result["selected_count"] = to_select

    bulk_btn = page.locator("button:has-text('Delete Selected')")
    if bulk_btn.count() == 0:
        result["error"] = "Bulk delete button not visible after selecting rows."
        return result

    result["attempted"] = True
    bulk_btn.first.click()

    otp = extract_otp_from_modal(page)
    result["otp"] = otp or ""
    if not otp:
        result["error"] = "OTP not visible in bulk delete modal."
        return result

    page.locator("input[placeholder='Type OTP here']").first.fill(otp)
    page.locator(".fixed.inset-0.z-\\[100\\] button:has-text('Delete')").first.click()

    result["toast_text"] = get_toast_text(page)
    wait_for_table_settle(page)

    post_rows = get_rows(page)
    result["post_rows"] = len(post_rows)
    result["rows_removed_count"] = max(0, result["pre_rows"] - result["post_rows"])
    return result


def main():
    output = {
        "login_url": LOGIN_URL,
        "clients_url": CLIENTS_URL,
        "single_delete": {},
        "bulk_delete": {},
        "notes": [],
    }

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={"width": 1500, "height": 1000})
        try:
            login(page)
            page.goto(CLIENTS_URL, wait_until="domcontentloaded", timeout=30000)
            wait_for_table_settle(page)

            output["single_delete"] = run_single_delete(page)
            output["bulk_delete"] = run_bulk_delete(page)

            if not output["single_delete"].get("attempted"):
                output["notes"].append("Single delete not attempted because no rows were available.")
            if not output["bulk_delete"].get("attempted"):
                output["notes"].append("Bulk delete not attempted because no selectable rows or bulk action was unavailable.")
        except Exception as exc:
            output["notes"].append(f"Fatal error: {exc}")
        finally:
            browser.close()

    print(json.dumps(output, indent=2))


if __name__ == "__main__":
    main()
