# uplinksync.com

Business site of UplinkSync LLC (MSP/IT, cybersecurity, AI automation, web, drone/photo/video services).

This repo is the **source of truth for the live WordPress site at [https://uplinksync.com](https://uplinksync.com)**:
WordPress root files plus tracked `wp-content/` (themes — active: `hostinger-ai-theme` — mu-plugins, plugins).
Uploads, caches and other runtime state are deliberately untracked; deploys never delete live-only files.

## CI/CD pipeline

- **CI**: shared template from [`***`](https://***/***) — php lint (themes + mu-plugins), gitleaks secret scan, wp-config `getenv()` enforcement, per-MR review app.
- **Previews**: every MR gets a disposable WordPress at `https://<branch-slug>.***`
  ("View app" on the MR; basic-auth credentials in Vault `***`).
- **Merges**: `main` is protected; merging requires a green pipeline; humans merge, agents don't.
- **GitHub**: CI-verified `main` auto-mirrors to [`github.com/***`](https://github.com/***), where GitHub Actions rsyncs `wp-content/` (site mode, no deletions) to the live site on Hostinger shared hosting.

## Contributing

Branch from `main` (*** agents: `***/<task-id>`), open an MR, let the pipeline
run, use the preview link to check the result. Never commit literal secrets — `wp-config.php` must keep sourcing all credentials from `getenv()` (CI blocks violations).

Full runbook: `***` → `docs/103-detail-gitlab-github-hostinger-pipeline.md`.
