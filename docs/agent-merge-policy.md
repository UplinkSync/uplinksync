# Agent merge policy — wp-site-uplinksync

Owner-approved 2026-10-14. Applies to the Paperclip agents (honeydew, project "UplinkSync Digital Presence"),
which act through the GitLab project access token `paperclip-agents-20261014` (role Developer).

## What agents may merge on their own
An agent may merge an MR into `main` only when **all** of the following are true:

1. The MR has the label `low-risk`. Apply it only when the diff is page/post content, docs, CSS, fonts,
   images, front-end JS, block templates/parts/patterns, or must-use plugins under `wp-content/mu-plugins/`
   (not code-owned since 2026-10-14, owner decision; they run on every request — keep them small and render-tested).
2. The MR changes no path listed in `.gitlab/CODEOWNERS` (CI/deploy/automation, `.github/`, `tools/ci/`,
   config and secrets, theme `functions.php` and `inc/`, `wp-content/plugins/`,
   root/core PHP).
3. The MR's head pipeline is green — including `owner_review_gate`, `php_lint`, the secret scans and
   `render_smoke`.
4. Every discussion thread is resolved.

Merge with `PUT /api/v4/projects/3/merge_requests/<iid>/merge`. If GitLab refuses, stop and hand the MR to
the owner — never retry with other options or edit the CI/CODEOWNERS files to get a merge through.

## How it is enforced
| Control | Where |
|---|---|
| Agents cannot push to `main` | Protected branch: push = Maintainers, merge = Developers + Maintainers, no force push |
| Green pipeline required | Project setting "Pipelines must succeed" (skipped pipelines do not count) |
| Threads resolved | Project setting "All threads must be resolved" |
| Owner review of sensitive paths | `owner_review_gate` CI job: reads `.gitlab/CODEOWNERS` from the target branch and fails unless the owner (dirwin) approved after the latest push |

This GitLab is Community Edition, so GitLab's own "Require approval from code owners" and approval rules
(Premium features) are not available; `owner_review_gate` replaces them. It runs from the MR's own CI file, so
it is a guardrail rather than a hard boundary: an MR that edits `.gitlab-ci.yml` is itself code-owned and
must never be merged by an agent.

## Owner: approving a code-owned MR
Review the MR, click **Approve**, then re-run the `owner_review_gate` job (or the pipeline). The merge
button unlocks once the pipeline is green.

## Production impact
A merge to `main` deploys `wp-content/` to production (GitLab → GitHub mirror → Hostinger). Keep MRs small
and check the live page after merging.
