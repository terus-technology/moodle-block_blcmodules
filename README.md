<p align="center">
  <img src="https://img.shields.io/badge/version-4.5.21-blue.svg" alt="Version">
  <img src="https://img.shields.io/badge/moodle-4.5-orange.svg" alt="Moodle">
  <img src="https://img.shields.io/badge/license-GPL%20v3-brown.svg" alt="License">
  <img src="https://img.shields.io/badge/maturity-STABLE-green.svg" alt="Maturity">
</p>
<p align="center">
  <img src="https://img.shields.io/badge/phplint-%E2%9C%93_passed-brightgreen?style=flat-square" alt="PHPLint">
  <img src="https://img.shields.io/badge/phpcs-%E2%9C%93_passed-brightgreen?style=flat-square" alt="PHPCS">
  <img src="https://img.shields.io/badge/phpunit-%E2%9C%93_passed-brightgreen?style=flat-square" alt="PHPUnit">
</p>

# Block BLC Modules

> Easily browse and add SCORM packages from the **Blended Learning Consortium** repository directly into your Moodle courses.

---

## 📖 Table of Contents

- [Block BLC Modules](#block-blc-modules)
  - [📖 Table of Contents](#-table-of-contents)
  - [About](#about)
  - [Requirements](#requirements)
    - [Moodle Prerequisites](#moodle-prerequisites)
  - [Installation](#installation)
    - [Option 1 — Install via Moodle UI (recommended)](#option-1--install-via-moodle-ui-recommended)
    - [Option 2 — Manual installation](#option-2--manual-installation)
  - [Updating](#updating)
    - [Option 1 — Via Moodle UI](#option-1--via-moodle-ui)
    - [Option 2 — Manual update](#option-2--manual-update)
  - [Configuration](#configuration)
  - [Features](#features)
  - [Changelog](#changelog)
  - [Support](#support)
  - [License](#license)

---

## About

**Block BLC Modules** (`block_blc_modules`) is a Moodle block plugin that integrates with the [Blended Learning Consortium](http://blc-fe.org) API. It allows teachers and administrators to search, preview, and import SCORM packages into any course with just a few clicks.

> ⚠️ **A valid BLC subscription is required.**  
> Samples and subscription enquiries can be made at [blc-fe.org](http://blc-fe.org).

---

## Requirements

| Requirement | Minimum |
|---|---|
| Moodle | **4.5** |
| PHP | 8.0+ |
| SCORM module | `mod_scorm` ≥ 2024100700 |
| BLC API Key | Provided after registration |

### Moodle Prerequisites

The SCORM setting **"Enable downloaded package type"** (`scorm | allowtypelocalsync`) must be enabled:

> _Site administration → Plugins → Activity modules → SCORM package → Allow downloaded package type_

---

## Installation

### Option 1 — Install via Moodle UI (recommended)

1. Go to _Site administration → Plugins → Install plugins_.
2. Upload the plugin ZIP file.
3. Follow the on-screen prompts and enter your BLC API key when asked.

### Option 2 — Manual installation

1. Download and unzip the plugin.
2. Place the `blc_modules` folder inside your Moodle `blocks/` directory:
   ```
   moodle/
   └── blocks/
       └── blc_modules/
   ```
3. Visit _Site administration → Notifications_ to trigger the installation.
4. Enter your BLC API key on the plugin settings page.

---

## Updating

### Option 1 — Via Moodle UI

Go to _Site administration → Plugins → Install plugins_ and upload the new version. Moodle will handle the upgrade automatically.

### Option 2 — Manual update

Overwrite the existing `blocks/blc_modules/` directory with the new version, then visit _Site administration → Notifications_.

> 💡 **On Moodle 5.0?** Switch to the [`release/moodle50`](https://github.com/terus-technology/moodle-block_blcmodules/tree/release/moodle50) branch.

> 💡 **On Moodle 5.1?** Switch to the [`release/moodle51`](https://github.com/terus-technology/moodle-block_blcmodules/tree/release/moodle51) branch.

> 💡 **Want to use Older Version?** Use [`master`](https://github.com/terus-technology/moodle-block_blcmodules/tree/master) branch.
---

## Configuration

After installation, configure the plugin at:

> _Site administration → Plugins → Blocks → BLC Modules_

| Setting | Description |
|---|---|
| **API Key** | Your unique BLC API key (obtained after registration). |
| **Validate Settings** | Built-in tool to verify your API key and Moodle URL are correctly registered with BLC. |

---

## Features

- 🔍 **Search & browse** SCORM packages by subject or keyword
- 📥 **One-click import** directly into any Moodle course
- 📊 **SCORM usage reports** with interactive charts and data tables
- 🔄 **Bulk update** SCORM modules across courses with real-time progress tracking
- 📝 **Load logging** to audit SCORM package imports
- 🎨 **Bootstrap 4** — native Moodle 4.5 look and feel
- 🔐 **Admin-only** API key management
- 🌐 **Google Drive** support for externally hosted SCORM packages

---

## Changelog

See [`CHANGELOG.md`](./CHANGELOG.md) for a complete, version-by-version history of all notable changes.

---

## Support

- 🌐 **BLC Website:** [blc-fe.org](http://blc-fe.org)
- 🐛 **Issues:** [GitHub Issues](https://github.com/terus-technology/moodle-block_blc_modules/issues)

---

## License

This plugin is licensed under the **GNU GPL v3** or later. See [`COPYING.txt`](../../COPYING.txt) for the full text.

```
Copyright © 2019–2026 Blended Learning Consortium
```