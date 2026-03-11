from playwright.sync_api import sync_playwright

BASE_URL = "http://127.0.0.1:8000"
EMAIL = "superadmin@example.com"
PASSWORD = "Password@123"


with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    page.goto(f"{BASE_URL}/login", wait_until="domcontentloaded", timeout=30000)
    page.fill("input[name='email'], input[type='email'], #email", EMAIL)
    page.fill("input[name='password'], input[type='password'], #password", PASSWORD)
    page.click("button[type='submit']")
    page.wait_for_timeout(4000)

    cookies = context.cookies()
    print("FINAL_URL", page.url)
    print("COOKIE_COUNT", len(cookies))
    for c in cookies:
        print(
            "COOKIE "
            f"name={c.get('name')} "
            f"domain={c.get('domain')} "
            f"path={c.get('path')} "
            f"secure={c.get('secure')} "
            f"httpOnly={c.get('httpOnly')}"
        )

    browser.close()
