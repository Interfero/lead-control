# Дизайн-система Lead Control

Документ описывает токены, компоненты и правила использования интерфейса. Исходные значения задаются в [resources/css/app.css](../resources/css/app.css). Базовая тема визуально совпадает с [Deep Purple на tweakcn](https://tweakcn.com/themes/cmlh0x713000104jrgmds6vcd), за исключением случаев из раздела 8.

## 1. Токены (CSS-переменные)

Переменные объявлены в `:root` (светлая тема) и переопределяются в `.dark` (тёмная тема). В Tailwind они доступны как семантические цвета (`bg-background`, `text-primary` и т.д.) через блок `@theme inline`.

### 1.1. Семантические цвета (контент и оболочки)

| Переменная | Tailwind (примеры) | Назначение |
|------------|-------------------|------------|
| `--background` | `bg-background` | Фон страницы |
| `--foreground` | `text-foreground` | Основной текст |
| `--card` | `bg-card` | Карточки, панели |
| `--card-foreground` | `text-card-foreground` | Текст на карточке |
| `--popover` | `bg-popover` | Выпадающие слои, тултипы, поповеры |
| `--popover-foreground` | `text-popover-foreground` | Текст на поповере |
| `--primary` | `bg-primary`, `text-primary` | Акцент, основные кнопки |
| `--primary-foreground` | `text-primary-foreground` | Текст на фоне primary |
| `--secondary` | `bg-secondary` | Вторичные блоки |
| `--secondary-foreground` | `text-secondary-foreground` | Текст на secondary |
| `--muted` | `bg-muted` | Приглушённые области |
| `--muted-foreground` | `text-muted-foreground` | Второстепенный текст |
| `--accent` | `bg-accent` | Подсветка при наведении / выделении |
| `--accent-foreground` | `text-accent-foreground` | Текст на accent |
| `--destructive` | `bg-destructive`, `text-destructive` | Ошибки, опасные действия |
| `--destructive-foreground` | `text-destructive-foreground` | Текст на destructive |
| `--border` | `border-border` | Рамки |
| `--input` | `border-input`, `bg-input` | Обводка/фон полей ввода |
| `--ring` | `ring-ring`, `focus-visible:ring-ring` | Кольцо фокуса |

### 1.2. Графики и бейджи (chart)

| Переменная | Tailwind | Назначение |
|------------|----------|------------|
| `--chart-1` … `--chart-5` | `bg-chart-1` … `text-chart-5` | Серии графиков, цветные акценты, бейджи статусов |

### 1.3. Навбар и зона «sidebar» (токены `--sidebar*`)

В разметке приложения классы `bg-sidebar`, `text-sidebar-foreground` и связанные используются для **верхней навигации** ([layouts/app.blade.php](../resources/views/layouts/app.blade.php), [layout.css](../resources/css/layout.css)). Имена совпадают с tweakcn/shadcn, но в светлой теме значения намеренно отличаются от экспорта tweakcn (см. раздел 8).

| Переменная | Tailwind (примеры) | Назначение в CRM |
|------------|-------------------|------------------|
| `--sidebar` | `bg-sidebar` | Фон полосы навбара |
| `--sidebar-foreground` | `text-sidebar-foreground` | Текст и иконки в навбаре |
| `--sidebar-primary` | `bg-sidebar-primary` | Акценты в навбаре (бейджи и т.п.) |
| `--sidebar-primary-foreground` | `text-sidebar-primary-foreground` | Текст на акценте навбара |
| `--sidebar-accent` | `bg-sidebar-accent` | Фон активного пункта меню |
| `--sidebar-accent-foreground` | `text-sidebar-accent-foreground` | Текст на accent навбара |
| `--sidebar-border` | `border-sidebar-border` | Разделители в навбаре |
| `--sidebar-ring` | `ring-sidebar-ring` | Фокус внутри навбара |

### 1.4. Legacy-семантика (вне tweakcn)

Заданы в `:root` и `.dark` в [app.css](../resources/css/app.css), используются в [layout.css](../resources/css/layout.css) для классов `.alert-*` и согласованности со старыми экранами. В `@theme inline` не продублированы — в разметке предпочтительны `x-ui.alert` и токены темы; при необходимости цвет: `color: var(--success)` и т.д.

| Переменная | Назначение |
|------------|------------|
| `--success` | Успех (алерты, подтверждения) |
| `--warning` | Предупреждение |
| `--danger` | Ошибка / опасность (рядом с destructive) |

### 1.5. Типографика

- **Sans:** Plus Jakarta Sans (`font-sans`, по умолчанию), подключение через Bunny Fonts.
- **Mono:** JetBrains Mono (`font-mono`).
- **Serif:** Georgia (`font-serif`).
- **Tracking:** утилиты `tracking-tighter` … `tracking-widest` (база `--tracking-normal: -0.02em`).

### 1.6. Радиусы и тени

- **Радиусы:** `rounded-sm` … `rounded-xl` от `--radius` (база `1rem`); витрина на `/ui-kit`.
- **Тени:** шкала `shadow-2xs` … `shadow-2xl` из переменных `--shadow-*`; витрина на `/ui-kit`.

### 1.7. Темы light / dark

- На `<html>` задаётся класс `dark` для тёмной темы.
- Предпочтение: поле `users.theme`, дублирование в `localStorage`, синхронизация через `PATCH /settings/theme`.
- Глобально: `window.leadControlSetTheme('dark'|'light')`, событие `leadcontrol:set-theme` с `detail.theme`.

## 2. Правила использования цвета

1. Не использовать «сырые» палитры Tailwind для бренда (`bg-blue-600`), кроме редких случаев в **бейджах статусов**.
2. Предпочитать семантические классы: `bg-card`, `text-muted-foreground`, `border-border`.
3. Инлайн-стили для цветов — не использовать без причины.

## 3. Состояния интерактивных элементов

| Элемент | Hover / Focus / Disabled |
|---------|---------------------------|
| Кнопка primary | `hover:bg-primary/90`, `focus-visible:ring-2 focus-visible:ring-ring`, `disabled:opacity-50` |
| Поле ввода | `focus:border-ring focus:ring-1 focus:ring-ring` |
| Ссылка | `hover:text-foreground` / `hover:text-primary` |

## 4. Каталог UI-компонентов

Расположение: `resources/views/components/ui/`.

| Компонент | Назначение |
|-----------|------------|
| `x-ui.button` | Кнопки и ссылки-кнопки (`variant`, `size`, `tag`, `href`) |
| `x-ui.input` | Текст, дата, пароль (`size` sm/md, `error`) |
| `x-ui.select` | Выпадающий список |
| `x-ui.textarea` | Многострочный ввод |
| `x-ui.label` | Подпись поля |
| `x-ui.form-group` | Лейбл + контрол + сообщение об ошибке |
| `x-ui.card` | Карточка (`padding`, `shadow`) |
| `x-ui.alert` | Уведомления (success, error, warning, info) |
| `x-ui.checkbox` | Чекбокс с подписью |

## 5. Чеклист перед коммитом (фронт)

- [ ] Нет новых хардкод-цветов вне обоснованных бейджей.
- [ ] Новые формы по возможности используют `x-ui.*`.
- [ ] Проверены светлая и тёмная тема на затронутых страницах.
- [ ] Сборка фронта проходит (`npm run build`).

## 6. Витрина компонентов (UI Kit)

Маршрут `GET /ui-kit` (имя `ui-kit`), доступ только роли **developer** (`middleware('role:developer')`).

Файл: [resources/views/ui-kit/index.blade.php](../resources/views/ui-kit/index.blade.php).

| Секция (порядок на странице) | Содержимое |
|------------------------------|------------|
| Семантические цвета | Все токены из §1.1–1.3: фоны, пары «фон + текст», chart, sidebar |
| Радиусы | `rounded-sm` … `rounded-xl` |
| Тени | `shadow-2xs` … `shadow-2xl` |
| Трекинг | `tracking-tighter` … `tracking-widest` |
| Типографика | Масштабы текста, mono |
| Кнопки, поля, алерты, карточки, бейджи | Примитивы `x-ui.*` |
| Таблица, пагинация | Shell и Bootstrap pagination |
| График, календарь, вкладки | Демо на токенах |
| Паттерны (tweakcn-ориентир) | Карточка метрик, список с аватарами, radio-группа, progress, разделитель, оболочка модалки |

Переключатель светлой/тёмной темы на странице синхронизирует класс `dark` на `<html>` и `localStorage` (`theme`) для предпросмотра без сохранения в профиль.

## 7. Legacy-стили

Файл `public/css/app.css` — общие классы (кнопки, формы, таблицы, модалки, мультиселект, часть экранов заказов/дашборда/промо). Оболочка навбара и пагинации — в `resources/css/layout.css` (подключается из `resources/css/app.css`). Инлайн-`<style>` в лейаутах убраны в пользу Vite + переменных темы; полный перевод всех страниц на `x-ui.*` — по мере рефакторинга.

## 8. Соответствие tweakcn и отклонения

Эталон темы: [Deep Purple — tweakcn](https://tweakcn.com/themes/cmlh0x713000104jrgmds6vcd). CSS-токены в [resources/css/app.css](../resources/css/app.css) совпадают с экспортом tweakcn по палитре контента (`background`, `primary`, `card`, тёмная тема, тени, радиусы, шрифты, `@theme inline`), кроме строк таблицы ниже.

### 8.1. Таблица расхождений

| Область | Экспорт tweakcn (светлая тема) | В CRM сейчас | Комментарий |
|---------|------------------------------|--------------|-------------|
| `--sidebar`, `--sidebar-foreground`, `--sidebar-accent*`, `--sidebar-border`, `--sidebar-primary*` | Светлый «dashboard»-сайдбар (например фон `#ffffff`) | Тёмная полоса навбара (`#1f2937` и сопутствующие значения в `:root`) | Осознанное отклонение: те же имена токенов, другое назначение — верхнее меню. Приведение к белому сайдбару tweakcn без отдельных `--navbar-*` не выполнялось; решение о разделении токенов — отдельная задача. |
| `--success`, `--warning`, `--danger` | Нет в типовом экспорте | Есть в `:root` / `.dark` | Для legacy `.alert` и `layout.css`. |
| `body` в `@layer base` | Часто только фон, текст и tracking | Дополнительно `font-sans antialiased` | Единый шрифт и сглаживание по всему приложению. |
| `--shadow-2xl` в тёмной теме | Запись с альфой `1.50` | `1.5` | Численно то же значение. |

### 8.2. Как не потерять эталон tweakcn

- Полный набор цветовых токенов и демо радиусов, теней, tracking — на `/ui-kit`.
- Паттерны в духе галереи tweakcn (метрики, списки, формы) — в секции «Паттерны» на UI Kit; при появлении второго и третьего экрана с тем же UI имеет смысл вынести повторяющееся в `resources/views/components/ui/`.
