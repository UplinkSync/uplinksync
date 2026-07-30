#!/usr/bin/env bash
# prod_render_smoke.sh — production-REPRESENTATIVE render smoke (***-171)
#
# WHY THIS EXISTS
# ---------------
# The upstream `render_smoke` gate (*** wordpress-site template)
# renders in a CLEAN ROOM: repo wp-content + a clean WP DB, WITHOUT the live
# auto-updated plugin stack or the real block content from the production DB.
# On MR !38 that clean render PASSED while the production homepage served
# HTTP 200 with an EMPTY body (an output-buffer plugin blanked <main>). That
# is the root cause of two customer-visible homepage outages in one class.
#
# This script fixes the representativeness gap the only way that is actually
# representative: it fetches the REAL, already-deployed URL (real plugin set,
# real DB, real caches) and asserts the page is not just 200 but non-blank and
# structurally intact. Run it as a BLOCKING post-deploy step so a green
# pipeline can no longer coexist with a blank prod.
#
# It also asserts CONTENT, not just status:
#   - a <main> region is present AND its inner text clears a word-count floor
#     (the specific miss on !38: 200 + chrome intact + empty <main>),
#   - an <h1> is present,
#   - the footer is present,
#   - the whole document clears a byte floor.
#
# USAGE
#   tools/ci/prod_render_smoke.sh https://uplinksync.com
#   BASE_URL=https://uplinksync.com tools/ci/prod_render_smoke.sh
#
# Exit 0 = all checked pages healthy. Exit 1 = at least one page blank/degraded.
#
# Env knobs (defaults chosen from live prod on 2026-07-30):
#   PATHS         space-separated paths to check (default: "/ /services/ /contact/")
#   MIN_BYTES     minimum full-document bytes            (default 20000)
#   MIN_MAIN_WORDS minimum words inside <main>           (default 40)
#   TIMEOUT       per-request seconds                    (default 25)
#   RETRIES       retries per page before failing        (default 2)
#   USER_AGENT    override UA (some WAFs 403 curl's UA)

set -uo pipefail

BASE_URL="${1:-${BASE_URL:-}}"
if [ -z "${BASE_URL}" ]; then
  echo "FATAL: no BASE_URL. Usage: $0 https://uplinksync.com" >&2
  exit 2
fi
BASE_URL="${BASE_URL%/}"

PATHS="${PATHS:-/ /services/ /contact/}"
MIN_BYTES="${MIN_BYTES:-20000}"
MIN_MAIN_WORDS="${MIN_MAIN_WORDS:-40}"
TIMEOUT="${TIMEOUT:-25}"
RETRIES="${RETRIES:-2}"
# Default to a real browser UA: WordPress/Hostinger WAFs intermittently
# challenge or 403 obvious bot UAs, which would make this gate flap.
USER_AGENT="${USER_AGENT:-Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0}"

fail=0

# Extract the inner text of the first <main>...</main> and count words.
# Falls back to <body> if the theme ever drops the <main> id we key on.
main_word_count() {
  # stdin: full HTML. stdout: integer word count of <main> inner text.
  awk '
    BEGIN { inmain=0 }
    { buf = buf $0 "\n" }
    END {
      s = buf
      # isolate first <main ...> ... </main>
      i = index(tolower(s), "<main")
      if (i == 0) { print 0; exit }
      s = substr(s, i)
      j = index(tolower(s), "</main>")
      if (j > 0) s = substr(s, 1, j)
      # strip tags and script/style content
      gsub(/<script[^>]*>.*<\/script>/, " ", s)
      gsub(/<style[^>]*>.*<\/style>/, " ", s)
      gsub(/<[^>]*>/, " ", s)
      gsub(/&nbsp;/, " ", s)
      gsub(/&[a-zA-Z#0-9]+;/, " ", s)
      n = split(s, w, /[ \t\r\n]+/)
      c = 0
      for (k=1;k<=n;k++) if (length(w[k])>0) c++
      print c
    }
  '
}

check_page() {
  local url="$1" attempt=1 code body bytes words resp
  while : ; do
    # ONE request per attempt. Firing separate body+status curls (plus retries)
    # hammered the host WAF into serving intermittent challenge pages, which
    # made the structural greps flap. Body and status now come from the same
    # response: status is appended after a sentinel and split back off.
    resp="$(curl -sS -m "$TIMEOUT" -A "$USER_AGENT" -L -w $'\n__HTTP_STATUS__:%{http_code}' "$url" 2>/dev/null)"
    code="${resp##*__HTTP_STATUS__:}"
    body="${resp%$'\n'__HTTP_STATUS__:*}"
    bytes="$(printf '%s' "$body" | wc -c | tr -d ' ')"
    words="$(printf '%s' "$body" | main_word_count)"

    # NB: match with bash's in-process regex, NOT `printf ... | grep -q`.
    # Under `set -o pipefail`, grep -q exits on first match and closes the pipe,
    # so printf takes SIGPIPE (141) and pipefail reports the PIPELINE as failed
    # even though the match succeeded — a nondeterministic false "no match"
    # that made this gate flap. `[[ =~ ]]` has no pipe and no such hazard.
    local lc_body ok=1 reasons=""
    lc_body="${body,,}"   # lowercase once for case-insensitive matching
    [ "$code" = "200" ] || { ok=0; reasons="$reasons http=$code;"; }
    [ "$bytes" -ge "$MIN_BYTES" ] || { ok=0; reasons="$reasons bytes=$bytes<$MIN_BYTES;"; }
    [[ "$lc_body" =~ \<main[[:space:]\>] ]] || { ok=0; reasons="$reasons no-<main>;"; }
    [ "${words:-0}" -ge "$MIN_MAIN_WORDS" ] || { ok=0; reasons="$reasons main-words=$words<$MIN_MAIN_WORDS;"; }
    [[ "$lc_body" =~ \<h1[[:space:]\>] ]] || { ok=0; reasons="$reasons no-<h1>;"; }
    [[ "$lc_body" =~ site-footer|\<footer ]] || { ok=0; reasons="$reasons no-footer;"; }

    if [ "$ok" = 1 ]; then
      printf 'PASS  %-45s http=%s bytes=%s main_words=%s\n' "$url" "$code" "$bytes" "$words"
      return 0
    fi

    if [ "$attempt" -le "$RETRIES" ]; then
      printf 'retry %d/%d %-40s%s\n' "$attempt" "$RETRIES" "$url" "$reasons" >&2
      attempt=$((attempt+1))
      sleep 3
      continue
    fi

    printf 'FAIL  %-45s http=%s bytes=%s main_words=%s ::%s\n' "$url" "$code" "$bytes" "$words" "$reasons"
    return 1
  done
}

echo "== prod render smoke :: $BASE_URL =="
for p in $PATHS; do
  # normalize
  case "$p" in /*) : ;; *) p="/$p" ;; esac
  check_page "${BASE_URL}${p}" || fail=1
  sleep 2   # space requests so we don't trip the host WAF's rate limiter
done

if [ "$fail" -ne 0 ]; then
  echo "RESULT: FAIL — at least one page blank or structurally degraded on the LIVE target." >&2
  exit 1
fi
echo "RESULT: PASS — all checked pages non-blank and structurally intact on the live target."
