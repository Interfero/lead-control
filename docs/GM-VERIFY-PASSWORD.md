# GM: verify-password



`POST /api/v1/gm/users/verify-password`



Server-to-server проверка логина/пароля сотрудника LC для входа в Guild Master.



**Канонический login:** `users.email` (целиком, без учёта регистра) **или** локальная часть email до `@` (например `ivan` для `ivan@company.ru`). Телефон / отдельное поле login **не** используются.



## Auth



```

Authorization: Bearer <GM_API_BEARER_TOKEN>

```



Без пользовательской сессии и CSRF.



## Request



```json

{

  "login": "user@example.com",

  "password": "plain-text-password"

}

```



Принимается и `email` вместо `login`. Логин без `@` ищется как локальная часть email.



## Responses



| Code | Body |

|------|------|

| 200 | см. ниже |

| 401 | `{"ok":false,"error":"invalid_credentials"}` — неверный пароль, пользователь не найден **или пароль не задан** (пустой hash) |

| 403 | `{"ok":false,"error":"forbidden"}` — неактивен / ЧС |

| 422 | `{"ok":false,"error":"validation_error"}` — пустые поля |



### 200 — успешный вход



```json

{

  "ok": true,

  "user": {

    "id": 42,

    "userId": 42,

    "email": "master@example.com",

    "login": "master",

    "displayName": "Иван Иванов",

    "name": "Иван Иванов",

    "role": "MASTER",

    "isActive": true,

    "city": "Казань",

    "branchCity": "Казань",

    "mustChangePassword": true,

    "passwordSet": true

  }

}

```



GM обязан читать числовой `userId` или `id`.



Пароль проверяется через `Hash::check` (bcrypt). Хеши и plaintext не отдаются и не пишутся в лог.



Rate limit: `throttle:gm-verify-password` (10/мин на login+IP, 20/мин на IP) + общий `60/мин` на `/api/v1/gm/*`.



## Как задать пароль мастеру (офис)



1. **HR → сотрудник → «Сбросить пароль»** (гендир / рег / разработчик; руководитель филиала — только мастерам своего города). Показывается одноразовый plaintext; ставится `must_change_password=1`.

2. **Массово (artisan):**  

   `php artisan masters:issue-passwords --city=10 --force --csv=/tmp/masters-passwords.csv`  

   или `--all --force --csv=...`  

   CSV: `user_id,login,email,password,city,must_change_password` — передать ответственному, файл удалить.

3. Мастер может сменить пароль сам в CRM: **Настройки → Изменение пароля** (флаг смены снимается).

4. Из GM: **POST /api/v1/gm/users/change-password** — см. [GM-CHANGE-PASSWORD.md](GM-CHANGE-PASSWORD.md).



Забыл пароль → офис снова жмёт «Сбросить пароль» в карточке HR.

