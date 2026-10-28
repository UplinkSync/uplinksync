<?php
/**
 * Title: Richer Quiet Light — Client Hub
 * Slug: uplinksync-child/site-hub
 * Categories: uplinksync
 * Description: /hub/ body, one page for both states (owner decision 2026-10-28: the client portal lives at /hub/). Signed out: hero, the six public destination tiles, a "Sign in to your client hub" call to action and the sign-in notes. Signed in: the UplinkPortal shell (uplinkportal_render_portal(), when the plugin is active) at the top, the tiles below as "Everything else". Rendered by uplinksync_hub_page() in inc/hub.php at render time.
 * Inserter: false
 */

// The body depends on who is signed in, so it is not built inside this pattern file (pattern
// content can be cached for the request, review L13). inc/hub.php expands the token when the
// HTML block renders.
?>
<!-- wp:html -->
[uls_hub_page]
<!-- /wp:html -->
