# Altus Site Redesign

A WordPress plugin that applies a complete dotlogics-style redesign (Altus blue) over an existing site — hand-coded PHP/HTML presentation layer with WordPress as the backend. Deactivating the plugin restores the original design, so the redesign is fully reversible.

## What it does

- Replaces theme templates with bespoke, hand-coded sections (hero, services, case studies, testimonials, CTAs)
- Consistent design system: typography, spacing scale, button styles, section padding
- Keeps the existing footer and all content intact
- Bespoke approved design for key landing pages, with old content backed up to options before replacement

## Tech stack

- PHP (WordPress template overrides, hooks/filters)
- Custom CSS/JS assets, no page-builder dependency for redesigned sections

## Structure

```
alx-site-redesign.php   Plugin bootstrap
parts/                  Template parts (hero, sections, cards)
templates/              Full-page templates
assets/                 CSS/JS
bundled/                Bundled assets
```

## Install

1. Upload to `wp-content/plugins/` and activate
2. Deactivate any time to restore the original theme design

Built by [Altus Web Solutions](https://altuswebsolutions.com).
