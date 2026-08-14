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

# POSIX sh, busybox-only (UPLAA-QW13). No bash, no git, no curl — see ls_remote().
set -u

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
# Read one ref's SHA from a remote over git's SMART-HTTP transport, using nothing
# but busybox wget.
#
# UPLAA-QW13 (2026-08-14): this used `git ls-remote`, which meant the job had to
# `apk add git bash curl` first — and on 2026-08-14 that install failed three
# times running (Alpine package CDN unreachable) while deploy_landed_check, in
# the SAME pipeline, happily reached api.github.com and uplinksync.com over
# HTTPS. General egress was fine; only the package mirror was down. A monitor
# that cannot run without installing software fails for reasons that have
# nothing to do with what it monitors, so the dependency is now gone entirely.
#
# `GET <repo>.git/info/refs?service=git-upload-pack` is the first half of a git
# fetch and needs no git client: the body is pkt-lines of "<4-hex len><sha> <ref>".
# Strip the length prefix and match the ref exactly (an anchored match, so
# refs/heads/main cannot be satisfied by refs/heads/main-something).
# Prints the SHA, or one of two sentinels. The distinction matters: an empty
# result would conflate "cannot reach the host" (inconclusive, do not page) with
# "the host is fine but the branch is GONE" — and a mirror that has lost its main
# branch is precisely the UPLAA-443 silent-disable this job exists to catch, so
# that case must page, not skip.
#
# A temp file rather than $(...) because the payload is binary: command
# substitution strips NUL bytes, which is exactly the delimiter we split on.
ls_remote() {
  _repo=$(printf '%s' "$1" | sed 's/\.git$//')
  _tmp="${TMPDIR:-/tmp}/msc_refs.$$"
  wget -q -O "$_tmp" -T 25 "${_repo}.git/info/refs?service=git-upload-pack" 2>/dev/null
  if [ ! -s "$_tmp" ]; then
    rm -f "$_tmp"
    printf 'UNREACHABLE\n'
    return 0
  fi
  _sha=$(tr '\000' '\n' < "$_tmp" \
    | sed 's/^[0-9a-f]\{4\}//' \
    | grep "^[0-9a-f]\{40\} refs/heads/$2\$" \
    | head -1 \
    | cut -d' ' -f1)
  rm -f "$_tmp"
  if [ -n "$_sha" ]; then
    printf '%s\n' "$_sha"
  else
    printf 'NOBRANCH\n'
  fi
}

gitlab_head() {
  # $CI_REPOSITORY_URL already embeds gitlab-ci-token credentials, so this works
  # against the private repo without handling a secret ourselves.
  if [ -n "${GITLAB_MAIN_URL:-}" ]; then
    ls_remote "$GITLAB_MAIN_URL" "$BRANCH"
  elif [ -n "${CI_REPOSITORY_URL:-}" ]; then
    ls_remote "$CI_REPOSITORY_URL" "$BRANCH"
  else
    ls_remote "$(git config --get remote.origin.url 2>/dev/null)" "$BRANCH"
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
  ls_remote "$GITHUB_MIRROR_URL" "$BRANCH"
}

# Fire an out-of-band push alert on a confirmed stall. Deliberately does NOT rely
# on GitLab email (dead since 2026-03-22). Best-effort: a failed POST must not
# change the script's exit code — the exit 1 is the primary, always-present signal.
send_alert() {
  _title="$1"; _body="$2"
  if [ -z "${MIRROR_ALERT_URL:-}" ]; then
    echo "WARN  MIRROR_ALERT_URL is unset — no out-of-band push alert sent. GitLab email is dead on this" >&2
    echo "      estate (since 2026-03-22), so the pipeline's non-zero exit is the ONLY signal right now." >&2
    echo "      Arm the push alert: set MIRROR_ALERT_URL (ntfy topic URL or webhook) as a CI variable." >&2
    return 0
  fi
  # ntfy accepts a plain-text body plus optional Title/Priority/Tags headers;
  # a generic webhook simply receives the body. Both are satisfied by this POST.
  # UPLAA-QW9: ntfy at ntfy.uplinksync.com runs auth-default-access=deny-all and
  # ANONYMOUS PUBLISH IS REFUSED (verified 2026-08-14: anonymous POST -> 403,
  # token POST -> 200). The Vault doc claiming an "everyone -> write-only"
  # transition safety net is stale. Auth is supplied as a separate MASKED CI
  # variable rather than embedded in the URL, because GitLab refuses to mask a
  # value containing URL punctuation - so a URL-embedded token would sit in the
  # project settings and job logs UNMASKED.
  #
  # UPLAA-QW13: busybox wget, not curl — this script no longer installs anything.
  # busybox wget has no repeatable --header short form issue; each header is its
  # own flag. --post-data makes it a POST.
  if [ -n "${NTFY_ALERT_TOKEN:-}" ]; then
    wget -q -O /dev/null -T 15 \
      --header="Authorization: Bearer ${NTFY_ALERT_TOKEN}" \
      --header="Title: $_title" \
      --header="Priority: urgent" \
      --header="Tags: rotating_light,git" \
      --post-data="$_body" "$MIRROR_ALERT_URL" 2>/dev/null
  else
    wget -q -O /dev/null -T 15 \
      --header="Title: $_title" \
      --header="Priority: urgent" \
      --header="Tags: rotating_light,git" \
      --post-data="$_body" "$MIRROR_ALERT_URL" 2>/dev/null
  fi
  if [ $? -eq 0 ]; then
    echo "ALERT sent to \$MIRROR_ALERT_URL." >&2
  else
    echo "WARN  push to \$MIRROR_ALERT_URL failed — the pipeline's non-zero exit remains the signal." >&2
  fi
}

echo "== mirror sync check :: GitLab main -> GitHub($GITHUB_MIRROR_URL) [$BRANCH] =="

gl="$(gitlab_head)"
GL_SOURCE="live"
# UNREACHABLE or NOBRANCH both mean "no trustworthy live value" on the GitLab
# side, so both fall back to the frozen SHA (which then cannot page — see below).
if [ -z "$gl" ] || [ "$gl" = "UNREACHABLE" ] || [ "$gl" = "NOBRANCH" ]; then
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
  if [ -z "$gh" ] || [ "$gh" = "UNREACHABLE" ]; then
    echo "SKIP  GitHub mirror unreachable (no refs returned) — inconclusive, not paging." >&2
    exit 0
  fi

  # Host answered, but there is no such branch. That is NOT ambiguous and NOT a
  # network problem: the mirror has lost $BRANCH entirely, which is the
  # UPLAA-443 signature in its most severe form. Page immediately — retrying
  # cannot make a deleted branch reappear.
  if [ "$gh" = "NOBRANCH" ]; then
    echo "RESULT: FAIL — the GitHub mirror is reachable but has NO $BRANCH branch at all." >&2
    echo "  GitLab $BRANCH : $gl" >&2
    echo "  The push-mirror has almost certainly been disabled or reset. Check" >&2
    echo "  GitLab -> Settings -> Repository -> Mirroring repositories." >&2
    send_alert "UplinkSync mirror MISSING $BRANCH" \
      "GitHub mirror is reachable but has no $BRANCH branch. GitLab $BRANCH is $gl. Deploys are NOT reaching production."
    exit 1
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
      if [ "$gl_new" = "UNREACHABLE" ] || [ "$gl_new" = "NOBRANCH" ]; then
        gl_new=""
      fi
      if [ -n "$gl_new" ] && [ "$gl_new" != "$gl" ]; then
        echo "  GitLab $BRANCH advanced mid-check: $gl -> $gl_new (re-basing comparison)" >&2
        gl="$gl_new"
      fi
    fi
    continue
  fi

  # Frozen source: resolve the ambiguity instead of giving up on it.
  #
  # UPLAA-QW14 (2026-08-14): QW12 made a frozen mismatch "inconclusive, not
  # paging" because "mirror stalled" and "main moved under us" looked
  # indistinguishable. The first green scheduled run then revealed the cost:
  # the live GitLab read TIMES OUT inside CI, so the job runs permanently in
  # frozen mode — and a gate that can never page is the same "passes while
  # doing nothing" failure this project has already hit three times.
  #
  # Why the live read fails: $CI_REPOSITORY_URL carries the EXTERNAL
  # gitlab.uplinksync.com host, which the runner container cannot reach (the
  # documented hairpin problem — the runner clones via a clone_url override
  # instead). It answers fine from outside, which is what made this look
  # healthy.
  #
  # The ambiguity is resolvable WITHOUT any GitLab connectivity, because git
  # history is a DAG and GitHub will tell us the relationship directly:
  #
  #   GET /repos/<o>/<r>/compare/<our-sha>...<branch>
  #     "ahead"/"identical" -> GitHub CONTAINS our commit and has moved on or
  #                            matches. The mirror is delivering. PASS.
  #     "behind"/"diverged" -> GitHub is genuinely missing our history. STALL.
  #     404                 -> GitHub has never seen our commit at all, which is
  #                            the strongest possible stall signal.
  #
  # api.github.com is demonstrably reachable from this runner: deploy_landed_check
  # uses it every run.
  if [ "$GL_SOURCE" != "live" ]; then
    echo "GitHub  $BRANCH = $gh"
    echo "  GitLab tip is the pipeline's frozen CI_COMMIT_SHA; asking GitHub how the two relate." >&2

    _api_repo="${GITHUB_API_REPO:-$(printf '%s' "$GITHUB_MIRROR_URL" | sed -e 's|.*github\.com[:/]||' -e 's|\.git$||')}"
    _cmp="${TMPDIR:-/tmp}/msc_cmp.$$"
    _rel=""
    if wget -q -O "$_cmp" -T 25 \
         "https://api.github.com/repos/${_api_repo}/compare/${gl}...${BRANCH}" 2>/dev/null \
       && [ -s "$_cmp" ]; then
      # Top-level "status" is at two-space indent; per-FILE "status" fields are
      # nested deeper, so anchoring to line start keeps them out.
      _rel=$(grep -o '^  "status": "[a-z]*"' "$_cmp" | head -1 | sed 's/.*: "\([a-z]*\)".*/\1/')
    else
      _rel="notfound"
    fi
    rm -f "$_cmp"

    case "$_rel" in
      ahead|identical)
        echo "  GitHub $BRANCH is '$_rel' relative to $gl — it CONTAINS our commit." >&2
        echo "RESULT: PASS — GitHub mirror contains GitLab main; push-mirror is delivering."
        exit 0
        ;;
      behind|diverged)
        echo "RESULT: FAIL — GitHub $BRANCH is '$_rel' relative to GitLab main." >&2
        echo "  GitLab main : $gl" >&2
        echo "  GitHub main : $gh" >&2
        send_alert "UplinkSync mirror STALLED" \
          "GitHub $BRANCH is $_rel relative to GitLab main $gl (GitHub at $gh). Merges are not reaching production."
        exit 1
        ;;
      notfound)
        echo "RESULT: FAIL — GitHub has never seen commit $gl (compare returned no result)." >&2
        echo "  The push-mirror has not delivered this commit at all." >&2
        send_alert "UplinkSync mirror STALLED" \
          "GitHub does not contain GitLab main $gl at all. Merges are not reaching production."
        exit 1
        ;;
      *)
        echo "SKIP  could not determine the relationship from the GitHub compare API" >&2
        echo "      (unexpected status '$_rel') — inconclusive, not paging." >&2
        exit 0
        ;;
    esac
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
