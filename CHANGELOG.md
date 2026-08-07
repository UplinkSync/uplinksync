# Changelog

Notable user-facing changes to the UplinkSync website (uplinksync.com).
The site is continuously deployed; this file summarizes milestones rather than
every deployment. Dates are `YYYY-MM-DD`.

The format is loosely based on
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Changed
- Drone-services page: describe aerial capture as a plain, open service for
  real estate, inspection, and events — removed the "additional service for
  managed-IT clients and referrals" gating so a first-time buyer isn't told
  the work is a side line.

## [0.1.0] - 2026-07-31

### Added
- Public online store at `/shop/` (WooCommerce) with secure checkout, a
  sectioned catalog, and collection pages.
- Cinematic hero on the homepage.
- Drone-services gallery integrated into the site.

### Changed
- Refreshed store presentation: navy collection headers, matched primary and
  secondary call-to-action buttons, and smoother product browsing.
- Accessibility improvements across the theme: clearer heading structure and
  stronger color contrast.

### Fixed
- Corrected location-page URLs and added redirects for renamed pages so old
  links keep working.
- Keep the shop filter toolbar below the sticky header.
- Homepage reliability: automated render checks in CI catch blank or degraded
  pages before and after deploy.

[Unreleased]: https://github.com/UplinkSync/uplinksync/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/UplinkSync/uplinksync/releases/tag/v0.1.0
