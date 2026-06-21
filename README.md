# TAG Protect for FreePBX

Screen inbound and outbound calls against [TAG Protect](https://protect.tag.org/)
call-blocking lists on FreePBX 16/17 (chan_pjsip). Install with a single command.

- **Inbound & outbound screening** — blocks numbers your users dial and incoming CallerIDs.
- **Always-allow list** — emergency and critical numbers (911, 988, Hatzalah, Poison Control…) are never blocked.
- **Local cache** — repeat numbers are answered instantly (default 24h), minimizing API calls.
- **Fail-open** — if the API is unreachable, calls connect normally; a caller is never stranded.
- **Class of Service (COS)** — assign extensions to groups (Staff, Campers, etc.) and enforce different TAG lists per group.
- **Configurable blocked-call recording** — select any system recording from the module settings page.
- **Extension-aware number tester** — test any number as a specific extension to preview its COS-applied result.
- **Two ways to manage** — a point-and-click FreePBX module, or a CLI.

---

## Installation

Run on the FreePBX server as root and pick **one**.

### Option A — GUI module (recommended)

```bash
curl -fsSL https://raw.githubusercontent.com/Shwerzb/tag-protect-freepbx/main/install-module.sh | sudo bash
```

Adds a **Connectivity → TAG Protect** page. During install you choose:

- **GUI setup** — configure later in the web UI.
- **CLI setup** — enter your API key, lists, and billing ID now.

### Option B — CLI / script

```bash
curl -fsSL https://raw.githubusercontent.com/Shwerzb/tag-protect-freepbx/main/install.sh | sudo bash
```

Installs the screening engine plus a `tagprotect` command for management. No web UI required.

---

## Configuration

You provide three things at setup:

| Setting | Description |
|---------|-------------|
| **API key** | Your TAG Protect `cbk_…` key. |
| **Lists** | `all`, or specific lists (e.g. `standard`, `secular-media`). |
| **Billing / location ID** | A unique ID per physical site. TAG bills **$20/month per unique ID**. |

**GUI:** *Connectivity → TAG Protect* — enter the key (masked, with show/copy), select lists,
set the billing ID, toggle screening on, and choose which inbound routes to screen. Click
**Submit**, then **Apply Config**.

---

## Management (CLI)

```text
tagprotect status            Show configuration, inbound routes, and cache size
tagprotect test <number>     Check a number against TAG
tagprotect resync            Pull the latest always-allow list from GitHub and reload
tagprotect allow <number>    Add a number to this site's local always-allow list
tagprotect update            Update the addon from GitHub
tagprotect wire-in           Route inbound calls through screening
tagprotect unwire-in         Stop inbound screening (restore routes)
tagprotect uninstall         Remove screening and restore inbound routes
```

---

## Always-allow list

Numbers that must never be blocked are managed in two places:

1. **Shared list** — [`always-allow.txt`](always-allow.txt) in this repository. Edit it here,
   then update each PBX:
   - GUI: **Resync allowed numbers** button on the module page.
   - CLI: `tagprotect resync`.
2. **Per-site list** — `/etc/asterisk/tagprotect-emergency.local.txt`, for numbers you don't
   want in the shared repo (`tagprotect allow <number>`). It is never overwritten by a resync.

Matching ignores formatting and handles US `+1` / 10-digit equivalence automatically.

---

## How it works

```
A call is placed (inbound or outbound)
  1. Always-allow?      → allow (never blocked)
  2. Cached (< TTL)?    → use the cached result, no API call
  3. Otherwise          → check TAG (POST /lists/check); on timeout/error → allow
  → blocked → play message and hang up
  → allowed → call continues normally
```

Outbound uses Asterisk's predial hook; inbound is inserted in front of each selected inbound
route's existing destination, so allowed calls reach their normal destination unchanged.

---

## Requirements

- FreePBX 16 or 17 (Asterisk 16+), `chan_pjsip`
- `php-cli`, `php-curl` (required); `php-sqlite3` (recommended, for caching)
- `git`; `sox` (recommended, for audio formatting)

---

## Multi-site

Install on each PBX and give every site its **own billing ID**. Maintain the shared
always-allow list centrally in this repository and run `tagprotect resync` (optionally via
cron) on each box.

---

## Uninstall

```bash
sudo tagprotect uninstall
```
Or, for the GUI module, uninstall it from **Admin → Module Admin**. Both restore inbound
routes to their original destinations.

---

## Notes

- The blocked-call recording is configurable from **Connectivity → TAG Protect** — any system
  recording in FreePBX can be selected. The default fallback is `sounds/tag-blocked.wav`.
- Never place emergency numbers on a blocklist; they belong in the always-allow list.
- The module is self-hosted and unsigned, so FreePBX shows an "unsigned module" notice. This is
  expected; you can disable the check under *Settings → Advanced Settings → Module Signature Checking*.
- **Forking:** if you copy this to another GitHub account, set `GH_REPO` at the top of the
  installers (or `export TAGPROTECT_REPO=user/repo`). Using this repo as-is requires no change.

---

## License

GPLv3+
