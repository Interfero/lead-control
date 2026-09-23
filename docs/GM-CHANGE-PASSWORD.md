# GM: change-password и password-status

Server-to-server смена пароля мастера из Guild Master. Источник правды — Lead Control (LC); GM проксирует запрос с `userId` из своей сессии.

См. также: [GM-VERIFY-PASSWORD.md](GM-VERIFY-PASSWORD.md).

## Auth

```
Authorization: Bearer <GM_API_BEARER_TOKEN>
Content-Type: application/json
Accept: application/json
```

Без пользовательской сессии LC и CSRF. Пароли (`currentPassword`, `newPassword`) не логируются.

---

## POST /api/v1/gm/users/change-password

### Request

```json
{
  "userId": 95,
  "currentPassword": "старый-пароль",
  "newPassword": "новый-пароль"
}
```

| Поле | Обязательно | Описание |
|------|-------------|----------|
| `userId` | да*, | LC `user_id` из сессии GM после `verify-password` / lc-login |
| `login` | да* | альтернатива: email целиком или локальная часть до `@` |
| `currentPassword` | да | текущий пароль |
| `newPassword` | да | новый пароль, минимум 8 символов |

\* Нужно хотя бы одно из `userId` или `login`. GM передаёт `userId`.

### Успех — 200

```json
{
  "ok": true,
  "userId": 95,
  "mustChangePassword": false
}
```

После успеха: новый bcrypt-хеш в БД, `must_change_password = 0`, `password_set_at = now()`.

### Ошибки

| Code | `error` | Когда |
|------|---------|--------|
| 401 | `invalid_credentials` | неверный `currentPassword` |
| 403 | `forbidden` | пользователь неактивен или в ЧС |
| 404 | `not_found` | `userId` / `login` не найден |
| 422 | `validation_error` | пустые поля, короткий пароль, новый = текущий |
| 429 | — | rate limit (5 попыток / 15 мин на userId+IP) |

Сообщения валидации совпадают с CRM → Настройки → Изменение пароля:

- «Текущий пароль обязателен»
- «Новый пароль обязателен»
- «Новый пароль должен содержать минимум 8 символов»
- «Новый пароль не должен совпадать с текущим»

### Пример curl

```bash
curl -sS -X POST 'https://lead-control.space/api/v1/gm/users/change-password' \
  -H 'Authorization: Bearer <GM_API_BEARER_TOKEN>' \
  -H 'Content-Type: application/json' \
  -d '{"userId":95,"currentPassword":"…","newPassword":"…"}'
```

Rate limit: `throttle:gm-change-password` — **5 попыток / 15 мин** на пару `userId` (или `login`) + IP вызывающего GM + общий `60/мин` на `/api/v1/gm/*`.

### Совместимость с verify-password

После успешной смены:

- `POST /users/verify-password` с **новым** паролем → 200, `mustChangePassword: false`
- с **старым** → 401 `invalid_credentials`

---

## GET /api/v1/gm/users/{userId}/password-status

Баннер «нужно сменить пароль» в GM без повторного `verify-password`.

### Response 200

```json
{
  "userId": 95,
  "hasPassword": true,
  "mustChangePassword": true
}
```

| Поле | Описание |
|------|----------|
| `hasPassword` | в LC задан bcrypt-хеш (не пустой) |
| `mustChangePassword` | флаг принудительной смены после сброса HR |

404 `not_found` — пользователь не найден.

### Пример curl

```bash
curl -sS 'https://lead-control.space/api/v1/gm/users/95/password-status' \
  -H 'Authorization: Bearer <GM_API_BEARER_TOKEN>'
```

Identity-заголовки (`X-GM-User-Id` и т.д.) **не** требуются — `userId` в URL.

---

## Кто может менять

Только сам мастер: GM передаёт `userId` из своей cookie-сессии. Массовая смена чужих паролей — по-прежнему HR / Artisan, не этот API.
