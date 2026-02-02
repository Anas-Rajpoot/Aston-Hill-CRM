# API Documentation

All API endpoints live under `/api/`. The API is framework-agnostic and supports both **session** and **token** auth.

---

## Auth Modes

### Session (default – same-origin SPA)

1. `GET /sanctum/csrf-cookie` – Initialize CSRF (web route)
2. `POST /api/auth/login` with `{ email, password }` – Sets session cookie, returns `{ redirect }`
3. Use `withCredentials: true` and CSRF token on all requests
4. `GET /api/me` – Returns current user (uses session cookie)

### Token (separate frontend / mobile)

1. `POST /api/auth/login` with `{ email, password }` and header `X-Request-Token: true`
2. Response: `{ token, token_type: "Bearer", user }` – Store token in `sessionStorage` or `localStorage`
3. Add `Authorization: Bearer {token}` to all requests
4. `GET /api/me` – Returns current user (uses Bearer token)
5. `POST /api/auth/logout` – Revokes the current token

---

## Endpoints

### Auth

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth/login` | Guest | Login (session or token via header) |
| POST | `/api/auth/logout` | Yes | Logout (session or revoke token) |
| POST | `/api/auth/2fa/verify` | Yes | Verify 2FA OTP `{ otp }` |
| GET | `/api/me` | Yes | Current user with roles |

### Public

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/countries` | List countries for registration |

### Field Submissions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/field-submissions/team-options` | Manager / Team Leader / Sales Agent dropdowns |
| POST | `/api/field-submissions` | Create field submission |

### Lead Submissions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/lead-submissions/current-draft` | Latest draft for current user |
| GET | `/api/lead-submissions/categories` | Service categories |
| GET | `/api/lead-submissions/service-types?service_category_id=` | Service types by category |
| GET | `/api/lead-submissions/type-schema/{type}` | Schema for service type |
| GET | `/api/lead-submissions/{id}` | Single lead submission |
| POST | `/api/lead-submissions/step-1` | Create draft (step 1) |
| PUT | `/api/lead-submissions/{id}/step-1` | Update step 1 |
| DELETE | `/api/lead-submissions/{id}/discard` | Discard draft |
| POST | `/api/lead-submissions/{id}/step-2` | Save step 2 (category + type) |
| POST | `/api/lead-submissions/{id}/step-3` | Save step 3 |
| POST | `/api/lead-submissions/{id}/step-4` | Save documents |
| POST | `/api/lead-submissions/{id}/submit` | Submit lead |

### Super Admin

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/super-admin/team-role-mappings` | Team hierarchy role config |
| PUT | `/api/super-admin/team-role-mappings` | Update team hierarchy |

---

## Using with React / Angular / Separate Deploy

1. **Token login**: Send `X-Request-Token: true` header when calling `/api/auth/login`
2. Store the returned `token` (e.g. in `sessionStorage`)
3. Add `Authorization: Bearer {token}` to all API requests
4. Configure CORS in Laravel if frontend is on a different domain (`config/cors.php`, `supports_credentials => true`)
5. Add your frontend origin to `SANCTUM_STATEFUL_DOMAINS` in `.env` if using session; for token-only, no need
