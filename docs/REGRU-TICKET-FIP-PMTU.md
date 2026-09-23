# Тикет REG.RU Cloud — битый путь до Floating IP (PMTU / stall ~16 KB)

**Кому:** поддержка REG.RU VPS / Cloud (OpenStack)  
**VPS:** `cv7694633.novalocal` · публичный IP **`194.67.92.69`** · приватный `192.168.0.172/24` · интерфейс `ens3`  
**Продукт:** CRM `lead-control.space` (сейчас на shared `31.31.197.5`, VPS — целевой прод)

Скопируйте блок ниже в обращение в панели REG.RU.

---

## Тема

VPS OpenStack: TCP-ответы через Floating IP обрываются / «зависают» после ~16 KB у клиентов из интернета; с датацентрового хоста до того же IP всё быстро. Просим проверить FIP/DNAT/PMTU.

## Суть проблемы

На VPS приложение и nginx отвечают мгновенно (localhost < 10 ms).  
При обращении **из интернета (домашний/офисный ПК)** на публичный IP `194.67.92.69` по HTTPS:

- маленькие ответы (**≤ ~16 KB**) — OK (~0.7–0.8 с);
- ответы **> ~16 KB** — TTFB нормальный, далее закачка **стопорится**, за 25–60 с приходит только ~16 075 байт (или таймаут).

При этом **с другого сервера REG.RU (shared `31.31.197.5`) на тот же `194.67.92.69`** файлы **1 МБ** скачиваются за **~0.13 с** без потерь.

Вывод: проблема не в PHP/Laravel/диске VPS, а в **сетевом пути клиент → Floating IP / DNAT OpenStack** (классический PMTU blackhole / фильтрация крупных сегментов).

## Что уже сделано на VPS (не помогло для residential-клиентов)

- MTU `ens3` = **1280** (netplan), `net.ipv4.tcp_mtu_probing = 1`
- nginx: `sendfile off`, gzip, `ssl_buffer_size 4k`
- iptables mangle: **TCPMSS clamp** 1160–1200 на SYN

После этого симптом с домашнего ПК **сохраняется**.

## Воспроизведение

На VPS (под root) создать тестовые файлы:

```bash
python3 - <<'PY'
from pathlib import Path
p = Path('/var/www/html')  # или DocumentRoot сайта
p.mkdir(parents=True, exist_ok=True)
for n in (16, 64, 256):
    (p / f'_speed_{n}k.txt').write_text(('X'*80+'\n') * (n*1024//81))
print('ok', p)
PY
```

(У нас DocumentRoot Laravel: `/var/www/lead-control/public/`.)

### А) С домашнего ПК (ломается)

```bash
curl -sk -o NUL -w "16k ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" --connect-timeout 10 -m 25 https://194.67.92.69/_speed_16k.txt
curl -sk -o NUL -w "64k ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" --connect-timeout 10 -m 25 https://194.67.92.69/_speed_64k.txt
curl -sk -o NUL -w "256k ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" --connect-timeout 10 -m 25 https://194.67.92.69/_speed_256k.txt
```

**Факт 2026-07-27 (клиент Windows → VPS):**

| Файл | TTFB | Total | Size получено | Ожидание |
|------|------|-------|---------------|----------|
| 16k  | ~0.77 s | ~0.77 s | **16362** | полный файл |
| 64k  | ~0.74 s | **25–60 s** | **~16075** | 65529 |
| 256k | ~0.72 s | **25 s** | **~16073** | 262116 |

### Б) С shared REG.RU `31.31.197.5` (работает)

```bash
curl -sk -o /tmp/dl.bin -w "64k ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" -m 30 https://194.67.92.69/_speed_64k.txt
curl -sk -o /tmp/dl.bin -w "1m ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" -m 30 https://194.67.92.69/_speed_1024k.txt
```

**Факт:** 64 KB и **1 MB** полностью, total **0.04–0.13 s**.

### В) На самом VPS (localhost) — OK

```bash
curl -sk -o /dev/null -w "%{time_total} size=%{size_download}\n" https://127.0.0.1/_speed_256k.txt
```

## Просьба к REG.RU

1. Проверить **Floating IP / DNAT / security groups / path MTU** для `194.67.92.69` (инстанс `cv7694633`).
2. Устранить обрыв TCP payload для клиентов из интернета (ICMP Fragmentation Needed / корректный MTU на пути FIP, либо иной способ выдачи публичного IP без blackhole).
3. Подтвердить тест: с внешней сети `curl` 256 KB+ с публичного IP отдаёт **полный** файл за разумное время (&lt; 3–5 с на обычном канале).
4. Если FIP неисправим на этом пуле — предложить **замену публичного IP / другой сетевой офер** без данной деградации.

## Критерий закрытия тикета

С **двух разных** внешних сетей (не из ДЦ REG.RU):

```text
curl -sk -o NUL -w "size=%{size_download} total=%{time_total}\n" -m 15 https://194.67.92.69/_speed_256k.txt
```

→ `size=262116` (или актуальный размер файла), `total` стабильно низкий, без зависания на ~16 KB.

После этого можем перенести DNS `lead-control.space` на VPS и отказаться от shared.

## Контакты / доступ

- SSH: по ключу, пользователь `root` (хост в панели VPS)  
- Готовы предоставить временный тестовый URL/`Host` и повторить замер вместе с инженером  

---

## Внутренние заметки (не в тикет)

- Пока тикет открыт: **не** переключать DNS A на голый `194.67.92.69`.
- Fallback, если REG.RU затянет: Cloudflare orange-cloud → origin VPS (shared всё равно можно выключить).
- Подробности диагностики: `docs/VPS-NETWORK-LAG.md`
