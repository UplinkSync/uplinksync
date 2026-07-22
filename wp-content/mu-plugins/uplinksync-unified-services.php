<?php
/**
 * Plugin Name: UplinkSync — Unified Service Architecture (home) [DISABLED]
 * Description: TEMPORARILY DISABLED 2026-07-22. The output-buffer rewrite in the
 *   original version blanked the homepage on production (HTTP 200, empty body).
 *   Neutralised to an inert stub to restore the live site. The deploy uses
 *   rsync WITHOUT --delete, so removing the file from the repo does NOT remove
 *   it from the server — the file must be overwritten with a safe version like
 *   this one. Re-implement under ***-132 with server-side verification before
 *   re-enabling. No hooks are registered here, so this file does nothing.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Intentionally no hooks, no output buffering. Inert.
