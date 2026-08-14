#!/bin/sh
# ---------------------------------------------------------------------------
# deploy_landed_check.sh — is production actually serving GitHub main?
#
# WHY THIS EXISTS
# The delivery chain is:  GitLab main -> GitHub push-mirror -> GitHub Actions
#                         -> rsync/SSH -> Hostinger production
#
# Each link has failed at least once, and every one failed QUIETLY:
#   * GitLab -> GitHub   : mirror sat auth-failed; covered by mirror_sync_check.sh
#   * GitHub -> Hostinger: 2026-08-14, deploy died at "Set up SSH". The GitLab
#                          pipeline was FULLY GREEN - prod_render_smoke included -
#                          because the site was up and serving the PREVIOUS
#                          version. Nothing was watching this link.
#
# ZERO RUNTIME DEPENDENCIES (UPLAA-QW12)
# This script deliberately uses ONLY busybox tools present in a bare alpine
# image: wget, grep, sed. It does NOT need git, bash, curl, or any `apk add`.
#
# Reason: on 2026-08-14 this job burned a FULL HOUR and failed when
# `apk add git bash curl` hung on the runner's flaky container egress - while
# mirror_sync_check in the SAME pipeline succeeded. A monitoring job that cannot
# survive its own dependency install is not monitoring; it is noise. Removing
# the install removes that whole failure class.
#
# `git ls-remote` is replaced by the GitHub commits API (60 req/hr unauthenticated
# is ample for a 2-hourly job); `curl` is replaced by busybox `wget`.
#
# EXIT CODES
#   0  in sync, or marker not yet present (see GRACE), or GitHub unreadable
#   1  CONFIRMED drift - production is serving a different commit than GitHub main
#
# ENV
#   BASE_URL           production base (default https://uplinksync.com)
#   GITHUB_API_REPO    default UplinkSync/uplinksync
#   MIRROR_ALERT_URL   optional ntfy topic / webhook for an out-of-band push
#   NTFY_ALERT_TOKEN   optional bearer token for that push (masked CI variable)
#   SETTLE_RETRIES     re-checks before declaring drift (default 3)
#
# GRACE: an ABSENT marker exits 0 with a loud warning rather than failing. A
# missing file is ambiguous (never deployed / path moved), and a monitoring job
# that cries wolf gets ignored - the exact failure mode this family of checks
# exists to prevent. A MISMATCH is unambiguous and does fail.
# ---------------------------------------------------------------------------
set -u

BASE_URL="${BASE_URL:-https://uplinksync.com}"
GITHUB_API_REPO="${GITHUB_API_REPO:-UplinkSync/uplinksync}"
SETTLE_RETRIES="${SETTLE_RETRIES:-3}"
MARKER_URL="$(printf '%s' "$BASE_URL" | sed 's#/$##')/wp-content/deploy-marker.txt"
API_URL="https://api.github.com/repos/${GITHUB_API_REPO}/commits/main"

echo "== deploy landed check =="
echo "   github : $API_URL"
echo "   marker : $MARKER_URL"

# busybox wget: -q quiet, -O - to stdout, -T connect/read timeout
fetch() {
  wget -q -O - -T 20 --header="Cache-Control: no-cache" "$1" 2>/dev/null
}

gh_json="$(fetch "$API_URL")"
gh_sha="$(printf '%s' "$gh_json" | grep -o '"sha"[[:space:]]*:[[:space:]]*"[0-9a-f]\{40\}"' | head -1 | grep -o '[0-9a-f]\{40\}')"

if [ -z "${gh_sha:-}" ]; then
  echo "WARN  could not read GitHub main (network, rate limit, or API shape change)." >&2
  echo "      Inconclusive - NOT failing, because an unreachable API says nothing" >&2
  echo "      about whether the deploy landed." >&2
  exit 0
fi
echo "   GitHub  main = $gh_sha"

prod_sha=""
i=1
while [ "$i" -le "$SETTLE_RETRIES" ]; do
  prod_sha="$(fetch "${MARKER_URL}?cb=$(date +%s)-$i" | tr -d '[:space:]')"
  if [ "$prod_sha" = "$gh_sha" ]; then
    echo "   prod    marker = $prod_sha"
    echo "RESULT: PASS - production is serving GitHub main. Deploy chain is delivering."
    exit 0
  fi
  [ "$i" -lt "$SETTLE_RETRIES" ] && sleep 20
  i=$((i + 1))
done

if [ -z "${prod_sha:-}" ]; then
  echo "WARN  deploy marker is ABSENT or unreachable at $MARKER_URL" >&2
  echo "      Expected once a site-mode deploy has run since UPLAA-QW8." >&2
  echo "      Treated as inconclusive (exit 0) so this job does not cry wolf." >&2
  exit 0
fi

echo "   prod    marker = $prod_sha"
echo "RESULT: FAIL - production is NOT serving GitHub main." >&2
echo "  GitHub main : $gh_sha" >&2
echo "  production  : $prod_sha" >&2
echo "" >&2
echo "The GitHub -> Hostinger deploy has stalled. Check the most recent" >&2
echo "'Deploy to WordPress host' run. A transient ssh-keyscan failure in" >&2
echo "'Set up SSH' has caused exactly this and cleared on a plain re-run." >&2

if [ -n "${MIRROR_ALERT_URL:-}" ]; then
  # ntfy runs auth-default-access=deny-all; anonymous publish is REFUSED
  # (verified 2026-08-14: anonymous 403, token 200). The token is a separate
  # MASKED CI variable rather than embedded in the URL, because GitLab refuses
  # to mask a value containing URL punctuation.
  body="Production is serving $(printf '%s' "$prod_sha" | cut -c1-8), GitHub main is $(printf '%s' "$gh_sha" | cut -c1-8). The GitHub->Hostinger deploy has not landed."
  if [ -n "${NTFY_ALERT_TOKEN:-}" ]; then
    wget -q -O /dev/null -T 15 \
      --header="Authorization: Bearer ${NTFY_ALERT_TOKEN}" \
      --header="Title: UplinkSync deploy STALLED" \
      --header="Priority: urgent" \
      --header="Tags: rotating_light,ship" \
      --post-data="$body" "$MIRROR_ALERT_URL" 2>/dev/null \
      && echo "      out-of-band alert pushed to MIRROR_ALERT_URL" >&2 \
      || echo "WARN  alert push to MIRROR_ALERT_URL failed" >&2
  else
    echo "WARN  NTFY_ALERT_TOKEN unset - ntfy refuses anonymous publish, so no" >&2
    echo "      push alert was sent. This job going red is the only signal." >&2
  fi
else
  echo "WARN  MIRROR_ALERT_URL unset - no push alert sent. GitLab email is dead on" >&2
  echo "      this estate, so this job going red is currently the ONLY signal." >&2
fi
exit 1
