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

# Authoritative GitLab main SHA. Inside a scheduled pipeline running on main,
# $CI_COMMIT_SHA is the tip of GitLab main — the exact commit the mirror is
# supposed to have pushed. Outside CI, fall back to ls-remote of the git origin
# (or an explicit GITLAB_MAIN_URL) so the script is runnable/testable anywhere.
gitlab_head() {
  if [ -n "${GITLAB_MAIN_URL:-}" ]; then
    git ls-remote "$GITLAB_MAIN_URL" "refs/heads/$BRANCH" 2>/dev/null | awk 'NR==1{print $1}'
  elif [ -n "${CI_COMMIT_SHA:-}" ] && [ "${CI_COMMIT_REF_NAME:-}" = "$BRANCH" ]; then
    printf '%s\n' "$CI_COMMIT_SHA"
  else
    git ls-remote origin "refs/heads/$BRANCH" 2>/dev/null | awk 'NR==1{print $1}'
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
    if curl -fsS --max-time 15 \
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
if [ -z "$gl" ]; then
  echo "SKIP  cannot resolve GitLab $BRANCH SHA (no CI_COMMIT_SHA and origin unreachable) — inconclusive, not paging." >&2
  exit 0
fi
echo "GitLab  $BRANCH = $gl"

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
    continue
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
