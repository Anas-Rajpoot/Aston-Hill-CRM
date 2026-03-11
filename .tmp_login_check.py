import requests
import re

s = requests.Session()
base = "http://127.0.0.1:8000"

r = s.get(base + "/login", timeout=15)
print("GET_STATUS", r.status_code)

m = re.search(r'name="_token"\s+value="([^"]+)"', r.text)
token = m.group(1) if m else ""
print("TOKEN_FOUND", bool(token))

data = {
    "_token": token,
    "email": "superadmin@example.com",
    "password": "Wrong@12345",
}

p = s.post(base + "/login", data=data, allow_redirects=True, timeout=15)
print("FINAL_URL", p.url)
print("FINAL_STATUS", p.status_code)
print("ON_LOGIN", "/login" in p.url)

patterns = [
    r"These credentials do not match our records\\.",
    r"The provided credentials are incorrect\\.",
    r"Invalid credentials",
    r"Whoops!",
]
found = []
for pattern in patterns:
    found.extend(re.findall(pattern, p.text, flags=re.IGNORECASE))
print("ERRORS", found)

with open(".tmp_login_attempt_response.html", "w", encoding="utf-8") as f:
    f.write(p.text)
