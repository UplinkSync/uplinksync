# DRAFT: commissioned-work rights (MSA s.10.2 / Drone Services Addendum s.5)

**Status: draft for counsel. Not live. Not legal advice.** Nothing here changes a signed agreement or the
published Terms. Source: `portal/pricing/PRICING-PROPOSAL.md` §2 and §4, owner decision (b) of 2026-10-28:
UplinkSync keeps copyright in commissioned work; the client gets a licence for the stated use; Exclusive
×1.5; Full buy-out ×2.5 (minimum +$400); raw footage +50%.

## 1. Current wording (to be replaced)

Drone Services Addendum s.5 (published source `tos/source/drone-services-addendum.md`):

> - **Ownership before final payment:** All drone deliverables (including footage/photos and reports) remain the property of UplinkSync until final payment is received in full.
> - **Transfer after final payment:** Upon final payment, UplinkSync assigns ownership of the paid deliverables to Client.
> - **UplinkSync retained rights:** UplinkSync retains all rights in its drone software, tools, methodologies, checklists, workflows, and operating techniques.

MSA s.10.2 (per the 2026-10-28 audit, `CURRENT-STATE.md` §3): deliverables stay UplinkSync property until
final payment, then ownership is assigned to the client, subject to s.10.3 and third-party limits.

## 2. Proposed replacement: MSA s.10.2

> **10.2 Deliverables.** UplinkSync owns all copyright in the Deliverables. They are not works made for
> hire. On payment in full, UplinkSync grants Client a perpetual, worldwide, non-exclusive (or exclusive,
> where Exclusive Use is purchased) licence to use the Deliverables for the Permitted Use stated in the
> Quote. Client may share the Deliverables with its listing brokerage, MLS and successors in title to the
> property, but may not otherwise sub-license or resell them. UplinkSync may use the Deliverables in its
> portfolio and promotion. Where Full Buy-out is purchased, UplinkSync assigns its copyright on payment in
> full and keeps a non-exclusive portfolio licence. The licence does not take effect, and may be
> suspended, while any amount is overdue.

Keep s.10.3, the client indemnity, and the client's duty to obtain releases and permissions as they are.

## 3. Proposed replacement: Drone Services Addendum s.5

> ## 5) Intellectual Property, Licence and Payment Holdback
>
> - **Copyright stays with UplinkSync.** All drone deliverables (including footage, photos and reports)
>   are UplinkSync's copyright works and are not works made for hire.
> - **Your licence.** On final payment in full, Client receives the licence set out in MSA s.10.2 for the
>   Permitted Use stated in the Quote (Standard Use unless the Quote says Exclusive Use or Full Buy-out).
>   Until final payment, no licence is granted, and the licence may be suspended while any amount is overdue.
> - **Standard Use (included).** Perpetual, worldwide, non-exclusive use for the stated purpose (for
>   example, marketing the named property or business on web, social, MLS, print and Client's own paid
>   ads). It may be shared with the listing brokerage, the MLS and the property's next buyer.
> - **Exclusive Use (×1.5 of the creative subtotal).** UplinkSync will not license the deliverables to
>   anyone else. Portfolio rights stay with UplinkSync.
> - **Full Buy-out (×2.5 of the creative subtotal, minimum +$400).** UplinkSync assigns its copyright on
>   payment in full and keeps a non-exclusive licence to use the deliverables in its portfolio.
> - **Raw or unedited footage (+50% of the shoot fee).** Supplied only when listed on the Quote, under the
>   same licence as the edited deliverables.
> - **Portfolio and stock.** UplinkSync may use the deliverables in its portfolio and promotion, and may
>   license **exteriors and landscapes** as stock, never interiors or identifiable people. On request, at no
>   charge, UplinkSync will embargo stock use until the listing closes or for 90 days.
> - **Mapping, survey and inspection deliverables.** These are Client data under a **Project licence**:
>   unrestricted use by Client, the owners, insurers, contractors and regulators for that project.
>   UplinkSync will never publish or reuse Client data without written consent. If Client's policy
>   requires ownership, UplinkSync assigns it on request at no extra charge.
> - **UplinkSync retained rights.** UplinkSync retains all rights in its drone software, tools,
>   methodologies, checklists, workflows, and operating techniques.

## 4. Quote / rate-sheet line items (match the website estimator)

| Usage rights option (creative lines) | Price | Estimator (`uls_estimator_uav_rates()['rights']`) |
|---|---|---|
| Standard Use licence for the stated use | included | `standard` ×1.0 |
| Exclusive Use | ×1.5 of creative subtotal | `exclusive` ×1.5 |
| Full Buy-out (copyright assigned) | ×2.5 of creative subtotal, min +$400 | `buyout` ×2.5, `minAdd` 400 |
| Raw / unedited footage | +50% of creative subtotal | `raw.pct` 0.5 |
| Third-party, broadcast or national campaign use | quote | not in estimator |

Travel is never marked up; the rush multiplier applies last: `(max(S×mult, S+minAdd) + raw + travel) × rush`.

## 5. Questions for counsel

1. Replacing the s.10.2 / s.5 assignment with a licence grant, plus the portfolio licence-back on buy-out.
2. Clients who signed under the old assignment wording: do they keep ownership (assume yes) and how to
   transition renewals.
3. Licence suspension for non-payment: enforceable as drafted?
4. Stock resale of commissioned exteriors; interaction with the client's releases and indemnity.
5. Whether "not works made for hire" plus a licence is enough, or a separate copyright notice is needed.
6. Stock licence v2.0 (`wp-content/mu-plugins/uplinksync-licence-tiers/licence-page.php`): credit as a
   condition vs a covenant; the 2× credit-lapse fee (liquidated damages vs penalty); disclosure to
   Exclusive buyers; licensee definition and one-end-client agency rule; AI-training ban wording; whether
   the licence page overriding site Terms §6 works.
