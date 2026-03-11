from playwright.sync_api import sync_playwright

URL = "http://127.0.0.1:8000/login"
EMAIL = "superadmin@example.com"
PASSWORD = "Wrong@12345"

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()

    logs = []
    page.on("console", lambda msg: logs.append(f"CONSOLE[{msg.type}] {msg.text}"))
    page.on("pageerror", lambda exc: logs.append(f"PAGEERROR {exc}"))

    page.goto(URL, wait_until="domcontentloaded", timeout=30000)

    page.fill("input[name='email'], input[type='email']", EMAIL)
    page.fill("input[name='password'], input[type='password']", PASSWORD)

    with page.expect_response(lambda r: '/login' in r.url or '/api' in r.url, timeout=15000) as resp_info:
        page.click("button[type='submit']")
    resp = resp_info.value

    page.wait_for_timeout(5000)

    print("SUBMIT_RESPONSE_URL", resp.url)
    print("SUBMIT_RESPONSE_STATUS", resp.status)
    print("FINAL_URL", page.url)
    print("ON_LOGIN", "/login" in page.url)

    text = page.locator("body").inner_text()
    print("HAS_SIGNING_IN", "Signing in..." in text)

    candidates = [
        "These credentials do not match our records.",
        "The provided credentials are incorrect.",
        "Invalid credentials",
        "Page Expired",
        "Whoops!",
        "error",
        "failed",
    ]
    found = [c for c in candidates if c.lower() in text.lower()]
    print("FOUND_MESSAGES", found)
    print("BODY_TEXT_START")
    print(text[:2000])
    print("BODY_TEXT_END")

    print("LOGS_START")
    for line in logs[:20]:
        print(line)
    print("LOGS_END")

    browser.close()
