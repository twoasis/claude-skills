---
name: html-to-elementor
description: Reproduce an existing HTML webpage as PIXEL-EXACT, editable Elementor custom widgets bundled in one WordPress plugin — fonts, spacing, colors, AND all animations/interactions intact (hovers, scroll-reveal, sliders, keyframes, JS demos). Use when the user provides a folder containing a canonical `final.html` plus its assets (images, fonts, design-system CSS) and/or asset links, and wants a 1:1 WordPress/Elementor build. The bar is "identical to the source webpage," not "inspired by." Also covers full-page assembly. SKIP for greenfield designs with no source HTML (use a normal design approach) and for Bricks/Divi/Beaver Builder.
---

# HTML → Elementor (pixel-exact reproduction)

The user has an existing, finished webpage (HTML + CSS + JS + assets) and wants it rebuilt in WordPress/Elementor with **zero visible deviation** and **every animation/interaction intact**. This skill captures the full workflow so each project starts at expertise level.

> Runs in **Claude Code** (local filesystem + Git + Node + `gh` + zip). It builds and packages a plugin; it does not connect to the WordPress site — you install the resulting zip (or let the GitHub auto-updater pull it).

## 🎯 The non-negotiable bar

**Reuse the source code verbatim. Do NOT re-derive styling by eye.** Hand-guessing CSS from a screenshot is exactly what produces "tiny differences," and across 10+ sections that drift compounds. Lift the real values: the actual inline styles, the actual class CSS, the actual keyframes, the actual JS. Recreating guarantees drift; lifting guarantees the match.

## 0. First action — orient, don't guess

- **Ask for the project setup UP FRONT (before building), so the plugin is wired right from the first build — don't defer repo/token to the end:**
  1. **Brand/slug** → plugin folder + prefix (e.g. `acme` → `acme-elementor-widgets`, widgets `acme_*`).
  2. **GitHub repo** (`owner/repo`) for this plugin's auto-updater → bake into the `Update URI` header + `*_GH_OWNER`/`*_GH_REPO` constants immediately. (Create it with `gh` if it doesn't exist.)
  3. **Token constant** for the private repo: default a **shared org token** (`<ORG>_GH_TOKEN`) from `wp-config.php`, with a per-brand fallback. Confirm the name.
  4. **Target WordPress** has Elementor (+ **Elementor Pro** if the design needs native forms).
- Find the canonical source: **`final.html`** in the project folder is the source of truth. Multiple HTML iterations often exist and **disagree** (different copy, light vs. dark treatments). Ignore all others unless the user says otherwise.
- Locate the supporting assets: design-system CSS (e.g. `colors_and_type.css`, a `*-kit.css`), the `fonts/` dir, `images/`/`uploads/`, and note any **external asset URLs** referenced in the HTML.
- If the HTML is a compiled JS/React app (look for `React.createRef`, `componentDidMount`, `sc-for`, data arrays like `cases:[…]`), the rendered markup is generated from **data arrays + templates** — extract those, not just static DOM.

## 1. Inventory the page into sections

Split `final.html` into top-level sections (hero, feature grids, sliders, CTA, …), excluding header/footer unless asked. **Count the id-less sections too** (micro-CTAs, diagrams, callouts) — each becomes its own widget.

## 2. Per section — extract VERBATIM

For each section pull, from `final.html` and its stylesheets:
- exact **markup** + inline styles (keep `clamp()`, the real px paddings/gaps, letter-spacing).
- the **class CSS** it relies on (`:hover`, `transition`, base rules) — grep the stylesheet.
- any **`@keyframes`** it uses.
- any **JS** that drives it: sliders (`scrollBy`, `scroll-snap`), scroll-reveal (`IntersectionObserver`), float loops, SVG demos. Copy the real logic and timings — **do not change its selectors.**
- the **icons** — use the source's actual SVG paths, not FontAwesome look-alikes.
- the **design tokens** — pull hex values and type scale from the design-system CSS; never substitute "neutral greys."

## 3. Assets

- Copy local images into the plugin's `assets/images/` (downscale large hero/photo files for web; keep quality high on prominent ones).
- **Download external image URLs** (CDN / `*.com/wp-content/...`) and bundle them, or default widget media controls to the live URLs.
- Bundle the **font files** actually used (woff2 + woff) into `assets/fonts/` and `@font-face` them. Never link a webfont CDN (CSP/offline risk).

## 4. Build ONE plugin, many widgets — ALWAYS per-section editable widgets

🚫 **NEVER ship the page as a single monolithic "whole page" widget.** Standing requirement: **every section is its own editable Elementor widget** (selectable, reorderable, with content controls). A one-widget page is not an acceptable deliverable (only an optional extra). Build per-section from the start — the "fast monolithic page first" detour always forces a full rebuild.

> 🧩 **Use the bundled reference code — do NOT re-derive it.** This skill ships proven, working copies at **`reference/class-github-updater.php`** and **`reference/class-template-registrar.php`**. **Copy them VERBATIM** into the plugin's `includes/` and only change the `namespace` line to the plugin's namespace. Re-writing the updater from prose reliably breaks silent, hard-to-debug things (private-repo authenticated download, not-forwarding-auth-to-the-redirect, and the `?force-check` cache bypass) → backend updates won't appear.

Structure (reuse a previously generated plugin as a template if available):
```
<brand>-elementor-widgets/
  <brand>-elementor-widgets.php       ← category + registers every section widget; registers shared assets; inits updater
  includes/class-github-updater.php    ← COPY VERBATIM from this skill's reference/; only change the namespace
  includes/class-template-registrar.php← COPY VERBATIM from this skill's reference/; only change the namespace
  widgets/class-<section>-widget.php   ← ONE editable widget per section (incl. id-less sections)
  assets/css/<brand>-system.css        ← tokens + the source's component CSS, lifted VERBATIM (one shared stylesheet)
  assets/js/<brand>-interactions.js    ← the source's behaviors, lifted VERBATIM (one shared script)
  assets/fonts/  assets/images/  templates/<page>.json
```

**Shared verbatim system (this is what makes per-section editable + 1:1 possible):**
- Extract the source page's whole `<style>` block + design tokens → `<brand>-system.css` (one file, verbatim). Extract its behavior JS (scroll-reveal, hover-steal, sliders, hero/scan, demos) → `<brand>-interactions.js`, adapted to run on `DOMContentLoaded` against the rendered classes/ids.
- **Every** widget does `get_style_depends() => ['<brand>-system']` and `get_script_depends() => ['<brand>-interactions']`.
- Each widget renders the section's **exact source markup with the source's class names AND ids** (e.g. `#proof .rail`, `.hero-media`) so the shared JS finds and animates it. **Do not invent BEM names** — the lifted JS targets the originals, and renaming silently breaks interactions (e.g. a slider whose buttons bind to nothing).
- Page-level JS (scroll-reveal walking `section > div > children`, hover-steal on `#id .card`) works automatically once each widget outputs `<section id="…"><div>…`.

**Theme isolation (REQUIRED — themes will otherwise override your styles).** Per-section widgets lose the source page's single outer wrapper, so any element relying on *inherited* font/color falls back to the **theme**, and theme element-selectors (`.entry-content h2`) outrank single-class component rules. Append an isolation block to the shared CSS, scoped to your widgets via Elementor's auto class so nothing leaks out to the theme:
```css
[class*="elementor-widget-<prefix>_"] > .elementor-widget-container{
  font-family:var(--font-body)!important; color:var(--fg-2)!important; line-height:1.5; margin:0; padding:0;
}
[class*="elementor-widget-<prefix>_"] .elementor-widget-container *{ box-sizing:border-box; }
[class*="elementor-widget-<prefix>_"] .elementor-widget-container :is(h1,h2,h3,h4,h5,h6,p,figure,blockquote,ul,ol){ margin:0; }
[class*="elementor-widget-<prefix>_"] .elementor-widget-container a{ text-decoration:none; }
[class*="elementor-widget-<prefix>_"] .elementor-widget-container button,[class*="elementor-widget-<prefix>_"] .elementor-widget-container input,[class*="elementor-widget-<prefix>_"] .elementor-widget-container textarea{ font-family:inherit; }
/* CRITICAL — many themes ship img{height:auto!important;max-width:100%!important},
   which overrides even inline styles and collapses every object-fit:cover image.
   Re-assert the exact sizing patterns the markup uses, keyed to the inline style,
   with !important — one line per pattern (height:100%, and any fixed heights like 260px). */
[class*="elementor-widget-<prefix>_"] .elementor-widget-container img[style*="height:100%"]{ height:100%!important; }
[class*="elementor-widget-<prefix>_"] .elementor-widget-container img[style*="object-fit:cover"]{ object-fit:cover!important; }
```
`!important` is on the **container** (parent) so it only governs inheritance — dark sections that set `color:#fff` on their own `<section>` and headings with inline display-font are unaffected; it just stops un-set text from inheriting the theme. (`<prefix>` = the widget name prefix, e.g. `acme_`.)

**Editability:** expose content as controls — TEXT/TEXTAREA/WYSIWYG/MEDIA/URL, and a **REPEATER for every card/list/slider collection** (one repeater item = one card). Keep purely procedural bits (interactive SVG demos, exact icon path data) as fixed markup and say so. Source inline styles can stay hard-coded in `render()` (that's the fixed design); swap controls in only for text/images/links.

**Page assembly — DON'T auto-create a published page.** Default to making the page **template** available; the user assembles when ready:
- Ship `templates/<page>.json` (Elementor export: array of `section > column > widget`, one per section, `page_settings.template = elementor_canvas`) for manual import (Elementor → Templates → Import).
- Optionally register that JSON into **Elementor's Saved Templates library** on `admin_init` (post_type `elementor_library`, `_elementor_data` = the JSON `content`, term `page` in `elementor_library_type`) so it appears with no file upload — idempotent: create once, re-create if deleted, never clobber edits.
- Do **NOT** build an auto-create-published-page button unless explicitly asked.

**Architecture = scaffold:** every site gets ONE self-contained plugin stamped from this template — no shared/runtime dependency between sites. The reusable core is the *bootstrap + updater + template-registrar + verbatim system files + packaging*; the widgets/fonts/tokens are per-project (or styles bleed across clients).

**GitHub auto-updater:** copy `includes/class-github-updater.php` verbatim; in the main file set an `Update URI:` header + `*_GH_OWNER`/`*_GH_REPO` constants and `new GitHub_Updater([...])` (admin only). For a **private** repo, read a read-only token from `wp-config.php` (the shared `<ORG>_GH_TOKEN` agreed in step 0, with a per-brand fallback) and download the release with auth — and do NOT forward the auth header to GitHub's signed-redirect URL (fetch with `redirection => 0`, then GET the `Location` without the header). Release flow: push → tag `vX.Y.Z` → attach `<slug>.zip` as a release asset (else zipball fallback + `upgrader_source_selection` rename) → site shows one-click Update. Tag must be **>** the installed version. Bypass the updater's own cache on `?force-check=1` so manual checks are instant.

- Honor `prefers-reduced-motion` in every animation. Match light/dark treatment exactly.

## 5. Verify before declaring done

Build a **self-contained preview Artifact per section** (inline fonts as data: URIs + downscaled images) and have the user compare against the source. Fix drift before moving on. A live Elementor render can shift slightly with theme CSS — re-check on the actual site.

## 6. Package — forward-slash zip ONLY

**Never use PowerShell `Compress-Archive`** — on Windows PS 5.1 it writes backslash path separators, which WordPress's extractor mishandles → "Plugin file does not exist." Use Info-ZIP via Bash:
```
cd <plugins dir> && rm -f <name>.zip && zip -r -q <name>.zip <name> -x "*.DS_Store" -x "<name>/.git/*"
```
Verify entries show forward slashes (`unzip -l`). Install: Plugins → Add New → Upload → Activate.

## Gotchas learned the hard way

- **ALWAYS per-section editable widgets** — never a monolithic page widget.
- **`final.html` is canonical.** Other variants in the folder mislead you (wrong colors/copy).
- **Lift, don't re-derive.** Every "tiny difference" traces to guessing instead of reading the source.
- **Keep the source's class names AND ids verbatim**; the lifted shared JS targets them. Renaming = dead interactions.
- **When porting JS, don't change its selectors.**
- **Match the treatment exactly** — a section may be light even if a thumbnail reads dark. Trust the source CSS tokens.
- **Real icons** — source SVG paths, not FontAwesome substitutes.
- **External images** must be downloaded/bundled or the widget breaks offline.
- **Compress-Archive backslash bug** breaks activation — always Info-ZIP.
- **Private-repo updater**: don't forward the auth header to the signed-redirect download URL.
