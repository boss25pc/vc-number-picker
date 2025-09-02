\# VC Number Picker (VaultComps)



Custom WooCommerce Lottery number picker plugin.



\## Features

\- Dark, responsive number grid (59, 100 etc).

\- Grey-out sold numbers; 10-minute reservations.

\- Multi-select supported; each pick becomes a cart line.

\- Numbers saved into orders.

\- Optional skill question with answer validation.

\- Compliance notice:  

&nbsp; \*“No purchase necessary. Free postal entry available – see Terms \& Conditions.”\* :contentReference\[oaicite:0]{index=0}



\## Styling / UX

\- Background: `#0b1020`, text: `#e9eef8`. :contentReference\[oaicite:1]{index=1}

\- Board classes: `.vc-cell`, `.is-picked`, `.is-res`, `.is-sold`. :contentReference\[oaicite:2]{index=2}

\- Responsive: 10/8/6/5 columns desktop → mobile. :contentReference\[oaicite:3]{index=3}

\- Astra mobile drawer forced dark.



\## AJAX Endpoints

\- `vc\_np\_state` (get sold/reserved)  

\- `vc\_np\_reserve` (reserve numbers)  

\- `vc\_np\_release` (release numbers)  

\- `vc\_np\_add\_to\_cart` (add reserved numbers) :contentReference\[oaicite:4]{index=4}



\## JS Behaviour

\- Polls every 6s, reconciles state without clobbering picks. :contentReference\[oaicite:5]{index=5}

\- Releases my numbers on navigation unload. :contentReference\[oaicite:6]{index=6}



\## CSS Behaviour

\- Picked cells show ✓ and accent border.  

\- SOLD/RES pills in top-right corner. :contentReference\[oaicite:7]{index=7}



---



\## Release Checklist

1\. Bump plugin version + update CHANGELOG.  

2\. Lint PHP/JS/CSS.  

3\. Test reservations/cart/orders locally.  

4\. Check board responsiveness on mobile.  

5\. Test in 2 browsers.  

6\. Deploy to staging → retest.  

7\. Backup production → deploy plugin.  

8\. Monitor logs 24h. :contentReference\[oaicite:8]{index=8}



