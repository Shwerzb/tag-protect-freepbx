# TAG Protect — FreePBX Module

A FreePBX 16/17 (chan_pjsip) module that screens inbound and outbound calls against
[TAG Protect](https://callblocker.overcloud.us) lists, with a local cache, an always-allow
list, and a recorded block message.

## Installation

**Recommended** (downloads and installs in one step):

```bash
curl -fsSL https://raw.githubusercontent.com/Shwerzb/tag-protect-freepbx/main/install-module.sh | sudo bash
```

**Manual:**

```bash
cp -a tagprotect /var/www/html/admin/modules/
fwconsole ma install tagprotect
```

## Configuration

Open **Connectivity → TAG Protect** and set your API key, lists, and billing/location ID,
enable screening, and choose which inbound routes to screen. Submit, then Apply Config.

## Documentation

Full project documentation, CLI usage, and the shared always-allow list:
<https://github.com/Shwerzb/tag-protect-freepbx>

## License

GPLv3+
