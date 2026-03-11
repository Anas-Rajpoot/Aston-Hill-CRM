import requests
s = requests.Session()
r = s.get("http://127.0.0.1:8000/login", timeout=15)
print("STATUS", r.status_code)
print("URL", r.url)
print("SET_COOKIES", dict(s.cookies))
with open('.tmp_login_page.html','w',encoding='utf-8') as f:
    f.write(r.text)
