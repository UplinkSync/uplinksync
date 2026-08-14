#!/usr/bin/env bash
# mirror_sync_check.sh — catch a silent GitLab->GitHub push-mirror stall (UPLAA-446)
#
# WHY THIS EXISTS
# ---------------
# Deploy path: GitLab main -> GitHub push-mirror -> GitHub Actions rsync -> live prod.
# In UPLAA-443 the GitLab push-mirror (remote id 44, UplinkSync/uplinksync) sat
# enabled:false / update_status:"none" for SIX DAYS (2026-07-31 -> 2026-08-06).
# Every merge to main was a silent no-op on live prod and NOTHING alerted. The
# freeze was only noticed when a user reported stale content.
#
# WHY IT DOESN'T NEED THE OWNER'S MIRROR-ADMIN TOKEN
# --------------------------------------------------
# The obvious check — GET /projects/3/remote_mirrors for enabled:false /
# last_error / update_status != "finished" — needs an api-scoped GitLab token the
# runtime does not have (paperclip-push-only returns insufficient_scope). But we
# don't need to inspect the mirror's *mechanism*; we can observe its *effect*.
# A stalled mirror means GitHub main falls behind GitLab main. GitHub main is
# readable with a plain unauthenticated `git ls-remote`, so we compare the two
# HEAD SHAs directly. Divergence that persists past the push-race grace window IS
# the silent-disable signal, caught with zero elevated privilege.
#
# NB: observing the *effect* (SHA divergence) is also why this monitor is immune
# to GitLab remote-mirror id churn. The AGB mirror id has moved 44 -> 242 -> 417
# in ~8 days; any check keyed to a fixed /remote_mirrors/{id} would silently stop
# watching the moment the id rolls. The "id 44" above is historical context for
# the UPLAA-443 incident only — nothing here keys off a mirror id. We key off the
# project's branch HEADs (a lookup), so id changes never blind the monitor.
#
# HOW IT ALERTS
# -------------
# Run in a GitLab *scheduled* pipeline (see .gitlab-ci.yml job `mirror_sync_check`).
# On a confirmed divergence the script (a) fires an out-of-band push to
# $MIRROR_ALERT_URL (ntfy/webhook) and (b) exits 1 so the scheduled pipeline shows
# red. It does NOT depend on GitLab email — that channel is dead on this estate
# (no outbound mail since 2026-03-22), so an email-only alert would silently fail
# exactly like the outage it is meant to catch. See MIRROR_ALERT_URL below.
#
# USAGE
#   tools/ci/mirror_sync_check.sh [github_remote_url]
#   GITHUB_MIRROR_URL=https://github.com/UplinkSync/uplinksync.git tools/ci/mirror_sync_check.sh
#
# Exit 0 = in sync, OR inconclusive (GitHub unreachable — can't verify, don't page).
# Exit 1 = GitHub main CONFIRMED behind/diverged from GitLab main past the grace
#          window — the mirror is very likely stalled; investigate now.
#
# Env knobs:
#   GITHUB_MIRROR_URL  GitHub mirror repo (default the UplinkSync public mirror)
#   GITLAB_MAIN_URL    authoritative GitLab source (default: CI checkout / origin)
#   BRANCH             branch to compare (default: main)
#   RETRIES            re-checks before declaring a real stall (default 3)
#   RETRY_SLEEP        seconds between re-checks — absorbs the push race (default 20)
#   MIRROR_ALERT_URL   optional push sink for the FAIL alert (ntfy topic or webhook).
#                      POSTed the alert body on a confirmed stall. See below.
#
# WHY AN INDEPENDENT PUSH SINK (not just job-fail email)
# -----------------------------------------------------
# The obvious alert path — "scheduled job fails, GitLab emails the schedule owner"
# — is itself a SILENT channel here: GitLab on this estate has had no working
# outbound email since 2026-03-22 (missing sendmail, dead Sidekiq mailer jobs).
# A monitor whose only sink is a dead channel reproduces the exact UPLAA-443
# failure mode it is meant to catch. So on a confirmed stall this script ALSO
# fires an active push to $MIRROR_ALERT_URL (an ntfy topic URL, or any webhook
# that accepts a POST body) before exiting 1. If $MIRROR_ALERT_URL is unset the
# script still exits 1 and prints a loud warning that no push sink is configured
# — it degrades safely, never blocks, and the non-zero exit still surfaces in the
# pipeline UI. Set MIRROR_ALERT_URL as a CI variable to arm the out-of-band alert.

set -uo pipefail

GITHUB_MIRROR_URL="${1:-${GITHUB_MIRROR_URL:-https://github.com/UplinkSync/uplinksync.git}}"
BRANCH="${BRANCH:-main}"
RETRIES="${RETRIES:-3}"
RETRY_SLEEP="${RETRY_SLEEP:-20}"

# Authoritative GitLab main SHA — read LIVE.
#
# UPLAA-QW12 (2026-08-14): this previously preferred $CI_COMMIT_SHA, described as
# "the tip of GitLab main". It is the tip only at PIPELINE-CREATION time, not at
# JOB-EXECUTION time, and that difference produced a false page:
#
#   scheduled pipeline 2523 created 06:23 (checkout pinned at 44b28adb)
#   owner merged !150 at 06:36            (GitLab main -> 97bdf347)
#   job finally ran   at 06:39            (delayed by the slow apk this MR fixes)
#   -> compared FROZEN 44b28adb against LIVE GitHub 97bdf347 and paged
#      "mirror STUCK - merges are NOT reaching live prod"
#
# The mirror was perfectly healthy (enabled, last_error=None, synced 06:37:14):
# GitHub was AHEAD of the stale local SHA, not behind. Comparing a frozen value
# against a live one is not a sync check - both sides must be read at the same
# instant. Note the failure direction: this gate cried WOLF, which is exactly
# what erodes trust in the alert it exists to send.
gitlab_head() {
  if [ -n "${GITLAB_MAIN_URL:-}" ]; then
    git ls-remote "$GITLAB_MAIN_URL" "refs/heads/$BRANCH" 2>/dev/null | awk 'NR==1{print $1}'
  else
    git ls-remote origin "refs/heads/$BRANCH" 2>/dev/null | awk 'NR==1{print $1}'
  fi
}

# Last-resort only, and deliberately NOT trusted enough to page on. If the live
# read fails we can no longer distinguish "mirror stalled" from "main moved
# under us", so a mismatch downgrades to inconclusive rather than a false alarm.
gitlab_head_frozen() {
  if [ -n "${CI_COMMIT_SHA:-}" ] && [ "${CI_COMMIT_REF_NAME:-}" = "$BRANCH" ]; then
    printf '%s\n' "$CI_COMMIT_SHA"
  fi
}

github_head() {
  git ls-remote "$GITHUB_MIRROR_URL" "refs/heads/$BRANCH" 2>/dev/null | awk 'NR==1{print $1}'
}

# Fire an out-of-band push alert on a confirmed stall. Deliberately does NOT rely
# on GitLab email (dead since 2026-03-22). Best-effort: a failed POST must not
# change the script's exit code — the exit 1 is the primary, always-present signal.
send_alert() {
  local title="$1" body="$2"
  if [ -z "${MIRROR_ALERT_URL:-}" ]; then
    echo "WARN  MIRROR_ALERT_URL is unset — no out-of-band push alert sent. GitLab email is dead on this" >&2
    echo "      estate (since 2026-03-22), so the pipeline's non-zero exit is the ONLY signal right now." >&2
    echo "      Arm the push alert: set MIRROR_ALERT_URL (ntfy topic URL or webhook) as a CI variable." >&2
    return 0
  fi
  if command -v curl >/dev/null 2>&1; then
    # ntfy accepts a plain-text body plus optional Title/Priority/Tags headers;
    # a generic webhook simply receives the body. Both are satisfied by this POST.
    # UPLAA-QW9: ntfy at ntfy.uplinksync.com runs auth-default-access=deny-all and
    # ANONYMOUS PUBLISH IS REFUSED (verified 2026-08-14: anonymous POST -> 403,
    # token POST -> 200). The Vault doc claiming an "everyone -> write-only"
    # transition safety net is stale. Auth is supplied as a separate MASKED CI
    # variable rather than embedded in the URL, because GitLab refuses to mask a
    # value containing URL punctuation - so a URL-embedded token would sit in the
    # project settings and job logs UNMASKED.
    auth_hdr=()
    [ -n "${NTFY_ALERT_TOKEN:-}" ] && auth_hdr=(-H "Authorization: Bearer ${NTFY_ALERT_TOKEN}")
    if curl -fsS --max-time 15 "${auth_hdr[@]}" \
         -H "Title: $title" -H "Priority: urgent" -H "Tags: rotating_light,git" \
         -d "$body" "$MIRROR_ALERT_URL" >/dev/null 2>&1; then
      echo "ALERT sent to \$MIRROR_ALERT_URL." >&2
    else
      echo "WARN  push to \$MIRROR_ALERT_URL failed — the pipeline's non-zero exit remains the signal." >&2
    fi
  else
    echo "WARN  curl not available — cannot push to \$MIRROR_ALERT_URL; relying on non-zero exit." >&2
  fi
}

echo "== mirror sync check :: GitLab main -> GitHub($GITHUB_MIRROR_URL) [$BRANCH] =="

gl="$(gitlab_head)"
GL_SOURCE="live"
if [ -z "$gl" ]; then
  gl="$(gitlab_head_frozen)"
  GL_SOURCE="frozen"
fi
if [ -z "$gl" ]; then
  echo "SKIP  cannot resolve GitLab $BRANCH SHA (origin unreachable and no CI_COMMIT_SHA) — inconclusive, not paging." >&2
  exit 0
fi
echo "GitLab  $BRANCH = $gl  (source: $GL_SOURCE)"

attempt=0
while : ; do
  gh="$(github_head)"
  if [ -z "$gh" ]; then
    echo "SKIP  GitHub mirror unreachable (ls-remote returned nothing) — inconclusive, not paging." >&2
    exit 0
  fi

  if [ "$gh" = "$gl" ]; then
    echo "GitHub  $BRANCH = $gh"
    echo "RESULT: PASS — GitHub mirror is in sync with GitLab main. Push-mirror is delivering."
    exit 0
  fi

  # Diverged. The mirror pushes within seconds of a merge, but a merge that
  # landed just before this check can leave GitHub momentarily behind — that is
  # NOT a stall. Re-check a few times before declaring a real problem.
  if [ "$attempt" -lt "$RETRIES" ]; then
    attempt=$((attempt+1))
    echo "diverged (GitHub=$gh != GitLab=$gl) — re-check $attempt/$RETRIES after ${RETRY_SLEEP}s (absorbing push race)" >&2
    sleep "$RETRY_SLEEP"
    # Re-read the GitLab tip too. If main advanced while we were waiting (a merge
    # mid-check), the previous value is stale and comparing against it would
    # report a stall that never happened.
    if [ "$GL_SOURCE" = "live" ]; then
      gl_new="$(gitlab_head)"
      if [ -n "$gl_new" ] && [ "$gl_new" != "$gl" ]; then
        echo "  GitLab $BRANCH advanced mid-check: $gl -> $gl_new (re-basing comparison)" >&2
        gl="$gl_new"
      fi
    fi
    continue
  fi

  # Only a live-vs-live comparison is trustworthy enough to page on.
  if [ "$GL_SOURCE" != "live" ]; then
    echo "GitHub  $BRANCH = $gh"
    echo "SKIP  GitLab tip could only be read from the pipeline's FROZEN CI_COMMIT_SHA," >&2
    echo "      which is the tip at pipeline-creation time, not now. GitHub=$gh differs," >&2
    echo "      but 'mirror stalled' and 'main moved since this pipeline started' are" >&2
    echo "      indistinguishable from here — inconclusive, deliberately not paging." >&2
    exit 0
  fi

  echo "GitHub  $BRANCH = $gh"
  echo "RESULT: FAIL — GitHub mirror is STUCK behind GitLab main after $RETRIES re-checks over ~$((RETRIES*RETRY_SLEEP))s." >&2
  echo "  GitLab main : $gl" >&2
  echo "  GitHub main : $gh" >&2
  echo "  This is the UPLAA-443 signature: the GitLab->GitHub push-mirror has very likely" >&2
  echo "  silently disabled, so merges to main are NOT reaching live prod. Action:" >&2
  echo "    1. GitLab -> Settings -> Repository -> Mirroring repositories: check the" >&2
  echo "       'UplinkSync/uplinksync' push mirror for enabled:false / last_error, hit Update now." >&2
  echo "    2. Confirm GitHub main advances to $gl, then confirm the GitHub Actions deploy ran." >&2
  send_alert "UplinkSync push-mirror STALLED (UPLAA-446)" \
    "GitLab->GitHub push-mirror looks disabled: GitHub main stuck at $gh, GitLab main at $gl (branch $BRANCH) after $RETRIES re-checks. Merges to main are NOT reaching live prod. Fix: GitLab -> Settings -> Repository -> Mirroring repositories, re-enable the mirror + Update now."
  exit 1
done
