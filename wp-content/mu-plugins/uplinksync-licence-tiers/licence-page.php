<?php
/**
 * /image-license/ v2.0 body (plain English, PRICING-PROPOSAL §1 + §4). DRAFT pending counsel review:
 * the admin-only notice is added by the_content filter in ../uplinksync-licence-tiers.php, never public.
 * Pure function so tests can assert the wording.
 */

defined( 'ABSPATH' ) || exit;

/**
 * @return string HTML wrapped in one core/html block (keeps wpautop off).
 */
function uls_lic_page_body() {
	$rows = '';
	foreach ( uls_lic_tiers() as $slug => $t ) {
		$credit = 'required' === $t['credit'] ? 'Required' : ( 'requested' === $t['credit'] ? 'Appreciated, not required' : 'Optional' );
		$rows  .= '<tr><th scope="row"><strong>' . esc_html( $t['label'] ) . '</strong><span>' . esc_html( $t['for'] ) . '</span></th>'
			. '<td class="uls-lic-num">$' . (int) $t['price'] . '</td><td>' . esc_html( $t['allowed'] ) . '</td><td>' . esc_html( $credit ) . '</td></tr>';
	}
	$rows .= '<tr><th scope="row"><strong>Exclusive</strong><span>Brands that need sole rights</span></th><td class="uls-lic-num">Quote</td>'
		. '<td>Terms set in a signed quote, from $1,500. The image is withdrawn from sale; earlier non-exclusive licences continue and are disclosed to you.</td><td>By agreement</td></tr>';

	$credit = esc_html( ULS_LIC_CREDIT_LINE );
	$ver    = esc_html( ULS_LIC_VERSION );

	return '<!-- wp:html --><div class="uls-lic-page">

<p class="uls-lic-lede">Every aerial photo in the shop is a digital download: one full-resolution JPEG, no physical item. When you buy, you choose a licence tier. UplinkSync keeps the copyright; you get the right to use the file in the ways your tier allows.</p>

<h2>Choose the tier that fits your use</h2>
<div class="uls-lic-tablewrap"><table class="uls-lic-table"><thead><tr><th scope="col">Tier</th><th scope="col">Price per photo</th><th scope="col">What you can do</th><th scope="col">Credit</th></tr></thead><tbody>' . $rows . '</tbody></table></div>
<p>Every tier covers <strong>one licensee</strong> (the person or business named at checkout), is <strong>perpetual, worldwide and non-exclusive</strong>, and includes one full-resolution file.</p>

<h2>What every licence includes</h2>
<ul>
<li>UplinkSync keeps the copyright. When you pay, you get a non-exclusive, perpetual, worldwide licence to use the file named on your certificate, for the uses your tier allows.</li>
<li>One licensee: the person or business named at checkout. The licence is not transferable, except that an agency or designer may hand finished work to its one end client.</li>
<li>You may edit, crop and colour the file.</li>
<li>You are responsible for any permissions your use needs (for example, people or property shown).</li>
</ul>

<h2>What no licence allows</h2>
<ul>
<li>Selling, sharing or sub-licensing the file itself, or offering it for others to print or download (stock sites, templates, print-on-demand listings).</li>
<li>Using it in a logo or trademark.</li>
<li>Using it to train AI or machine-learning systems, or including it in a dataset. Minting it as an NFT.</li>
<li>Using it unlawfully, deceptively, or to imply that UplinkSync or anyone shown endorses you.</li>
</ul>

<h2>The tiers in plain English</h2>
<h3>Personal</h3>
<p>For private, non-commercial use only: your home, gifts you don\'t sell, and personal posts and videos that earn no money. Not for any business, client, or monetised channel. We\'d love a credit (&ldquo;Aerial by UplinkSync&rdquo;) but it isn\'t required.</p>
<h3>Commercial with credit</h3>
<p>Everything Commercial allows, on one condition: every use shows &ldquo;Aerial by UplinkSync&rdquo; with a link to uplinksync.com (or the plain URL in print, or on screen or in the credits for video). If a credit is missing, you have 14 days after we tell you to add it. After that, this licence becomes Commercial and the upgrade fee in our price list applies.</p>
<h3>Commercial</h3>
<p>For the named business\'s own marketing, communications, editorial and d&eacute;cor: websites, organic and monetised social, email, presentations, and up to 5,000 printed copies in total. No paid advertising, broadcast, merchandise for sale, or larger print runs (see Extended). Credit optional.</p>
<h3>Extended</h3>
<p>Commercial plus paid advertising in any medium, unlimited print runs, broadcast, streaming and cinema, and up to 10,000 units of merchandise for sale.</p>
<h3>Exclusive</h3>
<p>Terms set in a signed quote. The file is withdrawn from sale, and earlier non-exclusive licences continue. <a href="/contact/?subject=Exclusive%20licence">Request a quote</a>.</p>

<h2>How to credit</h2>
<p>Credit line: <strong>' . $credit . '</strong></p>
<ul>
<li><strong>Web and social:</strong> next to the image or in the caption, with a live link or an @UplinkSync tag.</li>
<li><strong>Print:</strong> next to the image or on the credits page, with the plain URL uplinksync.com.</li>
<li><strong>Video:</strong> on screen for at least 2 seconds or in the end credits, plus the description or caption where the platform has one.</li>
</ul>
<p>Missing credit on a &ldquo;with credit&rdquo; licence: you have 14 days after notice to add it. After that the licence converts to Commercial and an upgrade fee of twice the price difference applies (photo: $80).</p>

<h2>Upgrades</h2>
<p>Any tier can move up at any time for the current price difference, for example Personal to Commercial for $84. <a href="/contact/?subject=Licence%20upgrade">Send us your licence ID</a> and we will send a payment link; a new certificate replaces the old one.</p>

<h2>Your certificate and downloads</h2>
<p>Each image you buy comes with a licence certificate (PDF) showing the licence ID, licensee, image, tier, permitted use and the credit line. It is attached to your order email and is always in <a href="/my-account/">My Account</a>. Downloads need you to be signed in to the account that placed the order; each licence includes 5 downloads within 30 days, and we re-issue on request.</p>

<h2>Refunds and ending the licence</h2>
<p>Because files are delivered immediately, sales are final. If a file is corrupt or you were charged in error, <a href="/contact/">contact us</a> and we will make it right. If you break these terms, the licence ends and you must stop using the file.</p>

<p class="uls-lic-fine"><em>Licence version ' . $ver . '. This licence replaces the site <a href="/terms/">Terms</a> for purchased files. Commissioned drone, photo and video work is covered by your signed agreement, not this page.</em></p>
</div><!-- /wp:html -->';
}
