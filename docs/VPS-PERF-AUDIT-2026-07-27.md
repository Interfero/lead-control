# VPS full performance audit — 2026-07-27 21:20 MSK

## Verdict

**Сейчас (VPN выкл, Ethernet): VPS быстрый и здоровее/быстрее shared по login.**  
**Вчерашние лаги** хорошо объясняются путём **через MantaRay VPN** (обрыв после ~16 KB), а не «слабым PHP на VPS».

DNS `lead-control.space` → всё ещё **shared** `31.31.197.5`.

## Client (VPN off)

| Metric | VPS `194.67.92.69` | Shared |
|--------|--------------------|--------|
| Route | Ethernet `192.168.0.100` | Ethernet |
| Login TTFB median (5×) | **~51 ms** | ~113 ms |
| 16 / 64 / 256 KB test | full 59 / 72 / 88 ms | — |
| JS gzip | 55 ms | 73 ms |

## Client (VPN on, earlier same day)

| Metric | Result |
|--------|--------|
| 64 / 256 KB → VPS | stall ~16075 B, 15–60 s |
| Same via Ethernet bind | full &lt; 100 ms |
| Shared CSS 89 KB via VPN | OK |

## Server VPS

- load 0.00 · RAM free ~7 GB · disk 110 GB free  
- nginx, php8.2-fpm, mariadb, redis: active  
- worker RUNNING · health 200 · queue pending/failed 0  
- DB: ping 5.6 ms · 242 orders · 50 rows 0.4 ms  
- localhost login TTFB ~12 ms  
- SESSION_DRIVER=database · CACHE/QUEUE=redis  
- MTU 1280, sendfile off, TCPMSS clamps present  

## Cutover

1. Work without VPN; warn staff.  
2. Phone LTE smoke.  
3. Dump+storage sync day-X.  
4. DNS → VPS only after gates.  
5. REG.RU FIP ticket — **not** with old VPN-based evidence.

Interactive summary: canvas `vps-perf-audit.canvas.tsx`.
