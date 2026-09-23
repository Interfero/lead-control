# Cloudflare для lead-control.space (VPN ↔ VPS)

## Статус (2026-09-01)

| Параметр | Сейчас | Нужно |
|----------|--------|-------|
| NS домена | Cloudflare (`craig` / `mina`) | OK |
| A `@` / `www` | **DNS only** → `194.67.92.69` | **Proxied** (оранжевое облако) |
| nginx real_ip на VPS | установлен | OK |
| Laravel trustProxies | `*` | OK |

Пока proxy **выключен**, браузер ходит **напрямую на VPS IP**. Это ломается:

- с **VPN** (Happ, MantaRay и т.п.) — обрыв после ~16 KB;
- со **скриптами обхода** (`fix-lead-control-vpn-lag`) — конфликт с **белыми списками** (домен в списке, IP — нет).

**Один переключатель в Cloudflare** закрывает оба сценария. Проверка на ПК: `deploy/check-network-mode.ps1`.

Подробный план: `docs/NETWORK-UNIVERSAL-ACCESS.md`.

## Зачем

С VPN (MantaRay Tun) прямые запросы на `194.67.92.69` часто «залипают».  
Cloudflare Free: браузер → ближайший CF edge → VPS по нормальному пути.

План: **бесплатный** тариф Free. Платить не нужно.

## Что уже на VPS (origin)

- `APP_URL=https://lead-control.space`
- Laravel `trustProxies(at: '*')` — HTTPS/сессия за прокси
- nginx: Let's Encrypt на origin
- nginx: `snippets/cloudflare-realip.conf` — реальный IP клиента из `CF-Connecting-IP`

SSH по-прежнему: `ssh` на `194.67.92.69` (не через домен).

## Шаги (один раз)

### 1. Cloudflare

1. Зарегистрироваться / войти: https://dash.cloudflare.com  
2. **Add a site** → `lead-control.space` → план **Free**  
3. Cloudflare покажет **два NS**, например:
   - `xxx.ns.cloudflare.com`
   - `yyy.ns.cloudflare.com`  
   Сохраните их.

### 2. DNS в Cloudflare (после скана или вручную)

| Type | Name | Content           | Proxy status      |
|------|------|-------------------|-------------------|
| A    | `@`  | `194.67.92.69`    | **Proxied** (оранжевое облако) |
| A    | `www`| `194.67.92.69`    | **Proxied**       |

Лишние A/AAAA на старый shared IP — удалить.  
AAAA на `@`/`www` не нужны (у origin только IPv4).

### 3. NS у REG.RU

Панель домена `lead-control.space` → DNS / NS → указать NS от Cloudflare (шаг 1).  
Пока NS не сменятся (часто 5–60 мин, иногда до суток) прокси не заработает полностью.

### 4. SSL/TLS в Cloudflare

**SSL/TLS** → Overview → режим **Full (strict)**  
(на VPS уже валидный Let's Encrypt — Flexible/не Full ломает сайт).

Рекомендуется:

- Always Use HTTPS: On  
- Automatic HTTPS Rewrites: On  

### 5. Проверка

```text
# DNS должен отдавать IP Cloudflare (не 194.67.92.69)
nslookup lead-control.space 1.1.1.1

# С включённым VPN: login + /orders + CSS без stall 15–20 с
https://lead-control.space/login
```

Заголовки ответа: `cf-ray` / `server: cloudflare` — значит трафик через CF.

Webhooks (Mango, Partner API) на `https://lead-control.space/...` продолжают работать через CF.

## Откат

В Cloudflare DNS: Proxy status → **DNS only** (серое облако)  
или вернуть NS на REG.RU — снова прямой IP (и снова тормоза у VPN).

## Кэш ассетов (по желанию)

Cache Rules / Page Rules: для `/build/*`, `/css/*`, `/js/*` — Cache Everything / длинный TTL.  
HTML (`/orders`, `/login`) лучше не кэшировать агрессивно (сессия/CSRF).

## Обновление IP-диапазонов Cloudflare

Файл на VPS: `/etc/nginx/snippets/cloudflare-realip.conf`  
Источник в репо: `deploy/vps/nginx-cloudflare-realip.conf`  
После правки: `nginx -t && systemctl reload nginx`
