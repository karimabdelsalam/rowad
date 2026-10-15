# Aqar Nova Mobile API (v1)

Replaces the legacy `?output=json` + `ACCESS-TOKEN` interface. Every office has its own API on
its own domain: `https://<office-domain>/api/v1`. Data is always the office's own database.

- Send `Accept: application/json`. Optional `Accept-Language: ar|en` (default: the user's language).
- Authenticated calls send `Authorization: Bearer <token>`.
- Money is a string with 2 decimals (`"2500.00"`), dates are `Y-m-d` (Gregorian), timestamps ISO-8601.
- Enums are `{ "value": "rented", "label": "مؤجرة" }` — the label follows the request language.
- Lists are paginated: `?page=` and `?per_page=` (max 100), with `links` and `meta`.
- Errors: `401` no/invalid token, `402` office suspended/expired, `403` not allowed, `422` validation, `429` throttled.

## Sign in

`POST /auth/login` — `{ "login": "<email or username>", "password": "...", "device_name": "iPhone 15" }`

- `200 { "token": "...", "user": {...} }`
- `202 { "mfa_required": true, "channel": "sms" }` when the office requires SMS login: a code was sent;
  call again with the same body plus `"code": "123456"`.
- Legacy (MD5) passwords work and are upgraded on first sign-in. 5 failed attempts lock the login for 5 minutes.

`POST /auth/logout` revokes the current token.

Forgotten password (legacy SMS reset): `POST /auth/password/code` `{ mobile }` → always `202`;
then `POST /auth/password/reset` `{ mobile, code, password, password_confirmation }`. A successful
reset signs the account out of every device.

## Every account

| Method | Path | |
|---|---|---|
| GET | `/me` | profile, type (`staff`/`owner`/`client`), permissions for staff |
| POST | `/devices` | `{ token, platform: ios\|android\|web }` register a push token (legacy pns) |
| DELETE | `/devices` | `{ token }` |
| GET | `/notifications` | in-app notifications, `meta.unread` |
| POST | `/notifications/read` | mark all read, or `{ id }` |

## AI assistant (every account)

The same assistant as the web panels: staff get answers from the office's data within their permissions;
owners and tenants only about their own units, contracts and payments. Needs the plan's `ai` feature
(`403` otherwise) and counts against the office's monthly AI questions.

| Method | Path | |
|---|---|---|
| POST | `/assistant/ask` | `{ question, conversation_id? }` → `{ conversation_id, answer, remaining_this_month }` (20/min) |
| GET | `/assistant/conversations` | the user's conversations, newest first (paginated) |
| GET | `/assistant/conversations/{id}` | `{ id, title, messages: [...] }` |
| DELETE | `/assistant/conversations/{id}` | `204` |

A message is `{ id, role: user|assistant, content, format: "markdown", sources: [labels of the data used], created_at }` —
render `content` as markdown. `remaining_this_month` is `null` for owners/tenants and for unlimited plans.
When the assistant can't answer (not configured, monthly quota used up, service busy) the response is
`503 { message, code: "ai_unavailable" }` with a message to show as is. Answers can take up to a minute.

## Office staff

Each endpoint checks the same permission as the matching admin screen.

| Method | Path | Permission |
|---|---|---|
| GET | `/dashboard` | `units.view_any` |
| GET | `/units?status=&building_id=&search=` | `units.view_any` |
| GET | `/units/{id}` | `units.view` |
| GET | `/contracts/{rent\|sale}?status=&unit_id=` | `rent_contracts.view_any` / `sale_contracts.view_any` |
| GET | `/contracts/{rent\|sale}/{id}` (with installments) | `*.view` |
| GET | `/installments?status=due\|overdue\|collected&unit_id=&contract_type=rent\|sale&contract_id=` | `installments.view_any` |
| POST | `/installments/{id}/collect` — `{}` full, or `{ amount, close?, settle_owners? }` partial | `installments.collect` / `installments.partial_collect` |
| GET | `/owners`, `/clients` `?search=` | `owners.view_any` / `clients.view_any` |
| GET | `/owners/{id}`, `/clients/{id}` | `*.view` |
| GET | `/vouchers/{receipt\|payment}?from=&to=` | `receipt_vouchers.view_any` / `payment_vouchers.view_any` |

## Owner app

| GET | `/owner/summary` | units, rented, balance, received this year |
|---|---|---|
| GET | `/owner/units` | units with shares |
| GET | `/owner/statement?settled=0\|1` | ledger: `debit` owed to the owner, `credit` deducted |
| GET/POST | `/owner/tickets` | `{ type: request\|enquiry\|problem\|suggestion, unit_id?, subject, message }` |

## Client (tenant/buyer) app

| GET | `/client/summary` | next installment, outstanding, overdue, active contracts |
|---|---|---|
| GET | `/client/contracts` | rent and sale contracts |
| GET | `/client/installments?status=collected` | default: unpaid |
| GET/POST | `/client/tickets` | as owner tickets |
