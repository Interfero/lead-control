# VPS network — corrected analysis (2026-07-27 evening)

## Correction

Earlier conclusion «Floating IP REG.RU ломает все клиентские закачки >16 KB» was **incomplete / misleading**.

Re-test with traffic forced out of **Ethernet** (bypass VPN):

| Path | 64 KB | 256 KB |
|------|-------|--------|
| Default route (**MantaRay Tun VPN**) → VPS | stall ~16075 B / 15–20 s | stall |
| **`--interface 192.168.0.100` (Ethernet)** → VPS | **full 65529 / 0.07 s** | **full 262116 / 0.09 s** |
| Shared → VPS (DC) | full / fast | full / fast |
| VPS localhost | full / fast | full / fast |
| Default → shared CSS 89 KB | full / OK | — |

## Root cause of our false positive

On the analyst PC, default IPv4 to both `194.67.92.69` and `31.31.197.5` goes via **MantaRay Tun** (MTU 1420; also Tailscale/Radmin present).  
Through that VPN, VPS large HTTPS stalls; **without VPN (Ethernet) VPS is fine**.

So: **do not open a REG.RU ticket claiming FIP is globally broken for Internet clients** — evidence does not support that from a clean path.

## What remains true

- App/PHP/nginx on VPS are not the bottleneck.
- Path-dependent stalls exist (VPN / some overlays ↔ this VPS IP).
- Original user “lags after cutover” may mix: VPN users, pre-MTU state, or other factors — needs re-check **without VPN** and from 1–2 clean networks (mobile LTE without VPN).

## Before cutover / before REG.RU

1. Disconnect MantaRay / Tailscale / other VPN.
2. From clean path: open `https://194.67.92.69/_regru_256k.txt` and CRM via hosts → VPS.
3. Repeat from phone (LTE, no VPN).
4. Only if **clean networks** still stall → then ticket to REG.RU with those results.

## Ticket status

`docs/REGRU-TICKET-FIP-PMTU.md` — **do not send as-is**; rewrite only if clean-path failure is confirmed.
