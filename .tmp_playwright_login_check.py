from playwright.sync_api import sync_playwright

URL = "http://127.0.0.1:8000/login"
EMAIL = "superadmin@example.com"
PASSWORD = "Password@123"

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto(URL, wait_until="domcontentloaded", timeout=20000)

    # Fill either fallback Blade form or SPA inputs
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
        for s in selectors:
            loc = page.locator(s)
            if loc.count() > 0:
                return loc.first
        return None

    email = first_visible(email_selectors)
    pwd = first_visible(password_selectors)

    if email is None or pwd is None:
        print("ERROR Could not find login inputs")
        print("FINAL_URL", page.url)
        print("BODY_TEXT", page.locator("body").inner_text()[:500])
        browser.close()
        raise SystemExit(1)

    email.fill(EMAIL)
    pwd.fill(PASSWORD)

    # Try submit button first; fallback to Enter
    submitted = False
    for selector in ["button[type='submit']", "button:has-text('Sign in')", "button:has-text('Log in')", "button:has-text('Login')"]:
        btn = page.locator(selector)
        if btn.count() > 0:
            btn.first.click()
            submitted = True
            break
    if not submitted:
        pwd.press("Enter")

    page.wait_for_timeout(1500)
    page.wait_for_load_state("domcontentloaded")

    print("FINAL_URL", page.url)
    print("ON_LOGIN", "/login" in page.url)

    body_text = page.locator("body").inner_text()
    print("BODY_TEXT_START")
    print(body_text[:1500])
    print("BODY_TEXT_END")

    browser.close()
