# WorkPages


> 🌍 **Diese Dokumentation in anderen Sprachen lesen:** [🇩🇪 Deutsch](README.de.md)



**The lightweight, self-hosted alternative to Jira and Confluence.** Built for shared hosting. No Docker, no build tools, no cloud lock-in.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.0-8892BF.svg)](#)

---

## 🎮 Live Demo

Want to see WorkPages in action before installing it? You can test the full functionality in our live demo environment. 

👉 **[Try the WorkPages Demo](https://demo-workpages.rueetschli.dev/)**

Log in using one of the following demo accounts to explore different user roles:

| Role | Email / Login | Password |
| :--- | :--- | :--- |
| **Administrator** | `admin@workpages.demo` | `DemoAdmin2026` |
| **Team Member** | `member@workpages.demo` | `DemoMember2026` |

*Note: This is a public demo environment. Please do not enter any sensitive or real company data. The database might be reset periodically.*

## 🛑 Why WorkPages? (The Anti-Cloud Approach)

Are you tired of complex Docker setups, endless Node.js dependencies, and SaaS subscriptions that lock your data in US-based clouds? 

WorkPages is an integrated Wiki and Task Management platform that goes back to the roots of the web. It combines knowledge sharing (Pages) and project tracking (Tasks) into a single, lightning-fast web application. 

- **No US-Cloud, No Telemetry:** Your data belongs to your organization. There is no tracking, no phoning home, and no vendor lock-in.
- **No Docker Required:** WorkPages is specifically designed to run on standard Shared Hosting environments. Just upload the files via FTP, run the web installer, and you are good to go.
- **No Build Pipeline:** Zero npm, Webpack, or frontend frameworks. Pure server-side rendered PHP and Vanilla CSS.

## 🎯 Who is this for?

- **SMEs & Agencies:** Manage client projects, content planning, and internal documentation in one place.
- **Privacy-Conscious Organizations:** Perfect for companies with strict data protection requirements (GDPR / DSG) who need on-premise solutions.
- **Schools & Non-Profits:** Cost-effective internal organization and knowledge management.
- **Pragmatic Admins:** Anyone who appreciates the simplicity of a standard PHP + MySQL stack.

---

## ✨ Features

### 📝 Knowledge Base (Wiki)
- **Markdown Native:** Create hierarchical pages with a powerful Markdown editor.
- **Structure:** Parent-child relationships, clean URL slugs, and breadcrumb navigation.
- **Sharing:** Generate cryptographically secure, read-only share links with expiration dates.
- **Export & Management:** Markdown exports, soft-deletes, and page move/copy capabilities.
- **Templates:** Use predefined page templates for recurring documentation.

### 📋 Task Management & Agile Boards
- **Kanban Boards:** Flexible columns, WIP limits, and drag-and-drop-style status changes.
- **Sprints & Workflow:** Sprint planning, burndown charts, velocity reports, and time estimates.
- **Task Hierarchy:** Organize work via Epics > Features > Tasks.
- **Smart Connections:** Link tasks directly to wiki pages (Many-to-Many).
- **Flow Metrics:** Built-in reporting for Lead Time, Cycle Time, and Throughput.

### 🤝 Collaboration & UI
- **Smart Text:** `@mentions` with autocomplete and `#tag` references.
- **Activity Stream:** Automated activity logs and rich comments on both pages and tasks.
- **Notifications:** In-app badges, email digests, and a flexible watcher system.
- **Modern Design:** Fully responsive layout with native Dark Mode and customizable branding (colors, logos).
- **Internationalization:** Fully translated to English and German.

### 🔒 Security, API & Administration
- **Enterprise-Grade Security:** Strict PDO Prepared Statements, CSRF tokens everywhere, and session hardening (httponly, secure, samesite=Lax).
- **Role-Based Access:** Admin, Member, and Viewer roles, plus Team-based visibility control.
- **Developer Ready:** REST API v1 with Bearer tokens, rate limiting, and HMAC-SHA256 secured Webhooks.
- **System Diagnostics:** Built-in admin dashboard to monitor PHP/DB health, disk space, and migration status.

---

## 🛠 Technical Stack

| Component | Technology |
| :--- | :--- |
| **Backend** | PHP 8.0+ (Vanilla, no frameworks like Laravel or Symfony) |
| **Database** | MySQL 5.7+ or MariaDB 10.3+ |
| **Frontend** | Server-side rendering, Vanilla CSS, minimal Vanilla JS (No React/Vue) |
| **Architecture** | MVC, Front Controller, PDO |
| **Build Process** | **None.** No npm, no Webpack, no bundlers. |
| **Markdown** | Parsedown (PHP), GitHub Markdown CSS, Easy Markdown Editor |

## 🚀 Installation & Hosting Requirements

WorkPages is highly optimized for standard European/Swiss Shared Hosting providers (e.g., Cyon, Hostpoint).

**Requirements:**
- PHP >= 8.0 (with extensions: `PDO`, `pdo_mysql`, `mbstring`, `json`)
- MySQL 5.7+ or MariaDB 10.3+
- Apache (with `mod_rewrite`) or Nginx

**Quick Start:**
1. Upload the repository files to your server via FTP/SFTP.
2. Point your server's Document Root to the `/public` directory.
3. Ensure write permissions for `/storage/` and `/config/`.
4. Navigate to `https://your-domain.com/?r=install` in your browser.
5. Follow the intuitive setup wizard.
6. Log in and start working!

*For detailed instructions, see [docs/INSTALL.md](docs/INSTALL.md).*

---

## 🔄 Updates & Operations

Updating WorkPages is as simple as its installation:
1. Back up your database and the `/storage/` + `/config/` directories.
2. Upload the new release files (your config and storage will remain untouched).
3. Log in as Admin and run the migrations via `?r=admin_migrate`.
4. Check system health under `?r=admin_system`.

---

## 🤝 Contributing

Contributions are highly welcome, but please respect the core philosophy of this project. **Before opening a PR, ensure your code aligns with these principles:**

- Keep it PHP 8+ compatible.
- **No frameworks:** Do not introduce external frameworks (Laravel, Symfony, etc.).
- **No build tools:** Do not add Node.js dependencies, npm, or SPAs.
- **Security first:** Use PDO Prepared Statements for *all* queries. All write actions must be `POST` requests with CSRF protection.
- **Shared Hosting friendly:** Ensure changes do not require root server access or special daemons.

## 📚 Documentation

- [Installation Guide](docs/INSTALL.md)
- [Configuration Reference](docs/CONFIG.md)
- [REST API v1 Documentation](docs/api.md)
- [Development Work Packages (AP1-AP31)](docs/APs.md)

---

## 📄 License & Credits

WorkPages is open-source software licensed under the [MIT License](LICENSE).  
Copyright (c) 2024-2026 WorkPages Contributors.

**Open Source Libraries Used:**
- [Parsedown](https://github.com/erusev/parsedown) (MIT)
- [GitHub Markdown CSS](https://github.com/sindresorhus/github-markdown-css) (MIT)
- [Easy Markdown Editor](https://github.com/Ionaru/easy-markdown-editor) (MIT)


## Print-Screens

![IMG_2817](https://github.com/user-attachments/assets/792a24ef-f444-4d7e-b941-2c64a846c459)
![IMG_2801](https://github.com/user-attachments/assets/50b6fbf8-3f26-42b0-8a51-9f77f3bacc67)
![IMG_2803](https://github.com/user-attachments/assets/9c63956a-0fc3-4b33-8366-8b25ef665d62)
![IMG_2805](https://github.com/user-attachments/assets/1d9c8b66-0952-42e3-95c0-be80e61a9198)
![IMG_2807](https://github.com/user-attachments/assets/98bf2e10-3411-4b38-b3b0-c560a61d7607)
![IMG_2809](https://github.com/user-attachments/assets/e1de73da-79d5-4c42-8f33-cd3273fe805f)
![IMG_2811](https://github.com/user-attachments/assets/fab1a333-428b-4e35-8f79-4da031a9ab9e)
![IMG_2813](https://github.com/user-attachments/assets/ae5e25e9-f4ec-4c9b-b910-cd56fc03ccf2)
![IMG_2815](https://github.com/user-attachments/assets/ca23a451-fe80-4b02-a216-5d01be2d7860)

