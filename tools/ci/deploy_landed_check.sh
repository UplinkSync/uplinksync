#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# deploy_landed_check.sh — is production actually serving GitHub main?
#
# WHY THIS EXISTS
# The delivery chain is:  GitLab main -> GitHub push-mirror -> GitHub Actions
#                         -> rsync/SSH -> Hostinger production
#
# Each link has now failed at least once, and every one of them failed QUIETLY:
#   * GitLab -> GitHub   : mirror sat auth-failed; covered by mirror_sync_check.sh
#   * GitHub -> Hostinger: 2026-08-14, deploy died at "Set up SSH" (transient
#                          ssh-keyscan failure). The GitLab pipeline was FULLY
#                          GREEN — including prod_render_smoke — because the site
#                          was up and simply serving the previous version. A plain
#                          re-run then succeeded. NOTHING was watching this link.
#
# mirror_sync_check.sh covers the first hop. This covers the second, using the
# marker stamped by .github/workflows/deploy-wordpress.yml.
#
# It needs no credentials: GitHub main is readable with an unauthenticated
# git ls-remote, and the marker is a public file.
#
# EXIT CODES
#   0  in sync, or marker not yet present (see GRACE below)
#   1  CONFIRMED drift — production is serving a different commit than GitHub main
#
# ENV
#   BASE_URL           production base (default https://uplinksync.com)
#   GITHUB_REPO_URL    default https://github.com/UplinkSync/uplinksync.git
#   MIRROR_ALERT_URL   optional ntfy topic / webhook for an out-of-band push
#   SETTLE_RETRIES     re-checks before declaring drift (default 3)
#
# GRACE: an ABSENT marker exits 0 with a loud warning rather than failing. A
# missing file is ambiguous (never deployed yet / path moved), and a monitoring
# job that cries wolf gets ignored — which is the failure mode this whole family
# of checks exists to prevent. A MISMATCH is unambiguous and does fail.
# ---------------------------------------------------------------------------
set -uo pipefail

BASE_URL="${BASE_URL:-https://uplinksync.com}"
GITHUB_REPO_URL="${GITHUB_REPO_URL:-https://github.com/UplinkSync/uplinksync.git}"
SETTLE_RETRIES="${SETTLE_RETRIES:-3}"
MARKER_URL="${BASE_URL%/}/wp-content/deploy-marker.txt"

echo "== deploy landed check =="
echo "   github : $GITHUB_REPO_URL [main]"
echo "   marker : $MARKER_URL"

gh_sha="$(git ls-remote "$GITHUB_REPO_URL" main 2>/dev/null | awk '{print $1}' | head -1)"
if [ -z "${gh_sha:-}" ]; then
  echo "WARN  could not read GitHub main (network/rate-limit). Inconclusive — not failing." >&2
  exit 0
fi
echo "   GitHub  main = $gh_sha"

fetch_marker() {
  curl -fsS -m 20 -H 'Cache-Control: no-cache' -H 'Pragma: no-cache' \
    "${MARKER_URL}?cb=$(date +%s)-$1" 2>/dev/null | tr -d '[:space:]'
}

prod_sha=""
for i in $(seq 1 "$SETTLE_RETRIES"); do
  prod_sha="$(fetch_marker "$i")"
  if [ "$prod_sha" = "$gh_sha" ]; then
    echo "   prod    marker = $prod_sha"
    echo "RESULT: PASS — production is serving GitHub main. Deploy chain is delivering."
    exit 0
  fi
  [ "$i" -lt "$SETTLE_RETRIES" ] && sleep 20
done

if [ -z "${prod_sha:-}" ]; then
  echo "WARN  deploy marker is ABSENT or unreachable at $MARKER_URL" >&2
  echo "      Expected once a site-mode deploy has run since UPLAA-QW8." >&2
  echo "      Treated as inconclusive (exit 0) so this job does not cry wolf." >&2
  exit 0
fi

echo "   prod    marker = $prod_sha"
echo "RESULT: FAIL — production is NOT serving GitHub main." >&2
echo "  GitHub main : $gh_sha" >&2
echo "  production  : $prod_sha" >&2
echo "" >&2
echo "The GitHub -> Hostinger deploy has stalled. Check the most recent" >&2
echo "'Deploy to WordPress host' run. A transient ssh-keyscan failure in" >&2
echo "'Set up SSH' has caused exactly this and cleared on a plain re-run." >&2

if [ -n "${MIRROR_ALERT_URL:-}" ] && command -v curl >/dev/null 2>&1; then
  if curl -fsS --max-time 15 \
       -H "Title: UplinkSync deploy STALLED" -H "Priority: urgent" -H "Tags: rotating_light,ship" \
       -d "Production is serving ${prod_sha:0:8}, GitHub main is ${gh_sha:0:8}. The GitHub->Hostinger deploy has not landed." \
       "$MIRROR_ALERT_URL" >/dev/null 2>&1; then
    echo "      out-of-band alert pushed to MIRROR_ALERT_URL" >&2
  else
    echo "WARN  alert push to MIRROR_ALERT_URL failed" >&2
  fi
else
  echo "WARN  MIRROR_ALERT_URL unset — no push alert sent. GitLab email is dead on" >&2
  echo "      this estate, so this job going red is currently the ONLY signal." >&2
fi
exit 1
