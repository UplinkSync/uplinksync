<?php
/**
 * Plugin Name: UplinkSync — Unified Service Architecture (home) [DISABLED AGAIN]
 * Description: DISABLED 2026-07-22 (second occurrence). The output-buffer rewrite
 *   blanked the production homepage again (HTTP 200, empty body) after MR !38,
 *   even though the render_smoke CI gate PASSED on the preview host. The preview
 *   container does not faithfully reproduce production (different active plugin
 *   set and DB content), so a preview-based render check is NOT sufficient
 *   evidence for this class of change. Do not re-enable without reproducing the
 *   failure on production-like state. Deploy uses rsync WITHOUT --delete, so this
 *   file must be overwritten (not removed) to disable it. Inert: registers nothing.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Intentionally no hooks, no output buffering. Inert.
