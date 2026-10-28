<?php
/** wp eval-file install-interim-snippet.php <path-to-interim-snippet.php> <logdir>  — installs + activates (idempotent by name). */
$name = 'ULS licence tiers — interim item-page text (retire when MR !189 is live)';
$code = file_get_contents( $args[0] );
if ( ! function_exists( '\Code_Snippets\save_snippet' ) ) { fwrite( STDERR, "Code Snippets API missing\n" ); exit( 1 ); }
$id = 0;
foreach ( \Code_Snippets\get_snippets() as $s ) { if ( $s->name === $name ) { $id = $s->id; } }
$cls = class_exists( '\Code_Snippets\Model\Snippet' ) ? '\Code_Snippets\Model\Snippet' : '\Code_Snippets\Snippet'; // 4.x vs 3.x
$sn  = new $cls( array( 'id' => $id, 'name' => $name, 'code' => $code, 'scope' => 'front-end', 'desc' => 'Interim: Licence tab + "Available soon" text for pa_licence products. Source: wp-site-uplinksync tools/licence-tiers/interim-snippet.php', 'active' => false ) );
$saved = \Code_Snippets\save_snippet( $sn );
$sid   = is_object( $saved ) ? $saved->id : (int) $saved;
$r     = \Code_Snippets\activate_snippet( $sid );
echo "snippet id={$sid} activate=" . ( is_object( $r ) ? 'ok' : var_export( $r, true ) ) . "\n";
file_put_contents( rtrim( $args[1], '/' ) . '/snippet-id.txt', $sid . "\n" );
