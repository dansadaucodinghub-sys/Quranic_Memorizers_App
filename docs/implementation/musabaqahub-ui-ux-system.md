# MusabaqaHub UI/UX System

## Objective

Apply the supplied MusabaqaHub identity across the application without changing route, authorization, tenant, progressive-enhancement, offline, or data contracts.

## Completed correction plan

1. **Brand assets** — installed the supplied primary logo and app icon as application-owned assets.
2. **Product identity** — normalized visible application, page-title, email-sender default, and system metadata names to `MusabaqaHub`; retained internal `QMDB` namespaces and technical identifiers.
3. **Install identity** — added favicon, Apple touch icon, theme color, and a web app manifest.
4. **Design tokens** — established emerald, ivory, gold, surface, border, focus, status, typography, spacing, radius, shadow, and motion tokens.
5. **Application shell** — rebuilt the shared header, navigation, language/theme controls, authenticated workspace menu, footer, and active-route states.
6. **Homepage** — introduced a responsive institutional hero, platform capabilities, supplied logo showcase, readiness summary, and trust-focused content hierarchy.
7. **Page families** — standardized identity forms, validation, workspace selection, community feeds, media, cards, tables, details, status banners, and governed action controls.
8. **P13 and offline operations** — styled pilot, rollout, device, package, sync, conflict, and offline status interfaces without changing their security or mutation hooks.
9. **Responsive behavior** — provided desktop, tablet, and narrow-screen layouts with contained tables and horizontally scrollable primary navigation.
10. **Localization and direction** — retained English/Arabic catalogs and logical CSS properties for correct RTL flow.
11. **Accessibility** — retained skip links and semantic controls; added strong focus treatment, minimum target sizing, reduced-motion, forced-colors, and print behavior.
12. **Theme support** — aligned system, light, dark, emerald-gold, and high-contrast modes with the MusabaqaHub palette.
13. **Regression coverage** — added automated checks for brand assets, manifest metadata, application-shell integration, responsive rules, and accessibility media queries.

## Visual rules

- Deep emerald communicates institutional authority and anchors navigation and governed surfaces.
- Ivory replaces flat white as the primary canvas and keeps dense operational pages calm.
- Gold is reserved for recognition, active states, trust cues, and restrained emphasis.
- Display typography is used for major headings; interface copy remains a highly readable system sans serif.
- Islamic geometric influence is applied as subtle structure, never as decoration that competes with records or controls.
- Cards and tables remain compact enough for operational work while preserving readable spacing and clear grouping.

## Protected application contracts

- Existing URLs and form actions
- Authentication and authorization behavior
- Tenant/workspace context hooks
- CSRF, idempotency, and progressive-form attributes
- AJAX fragment and modal behavior
- Offline queue, synchronization, and service-worker behavior
- Bilingual and no-JavaScript fallbacks

## Canonical assets

- `/assets/brand/musabaqahub-logo.png`
- `/assets/brand/musabaqahub-app-icon.png`
- `/site.webmanifest`

Future UI work should extend the shared tokens and components before introducing page-specific rules.
