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
  5. **Forms** — if the page has a contact/CTA form, ask which system to wire it to: **Elementor Pro Form** or **Contact Form 7**.
     - **CF7** → ask whether they'll use an **existing** form (ask its **ID**) or want a **new** one. Render `do_shortcode('[contact-form-7 id="<id>"]')` inside the section's styled card and scope the field/label/submit CSS (`#cta .wpcf7-form …`) to match the design; expose the ID as a widget control and keep a static placeholder as fallback (with a graceful notice if CF7 is inactive). For a **new** form, **provide a ready-to-paste CF7 form-template** that reproduces the design's layout — wrap paired fields (e.g. First/Last) in a `<div class="form-2">` grid so the scoped CSS renders them two-up, full-width submit, matching labels/placeholders. ⚠️ **You cannot create the CF7 form yourself** — it lives in the WP database. You hand over the markup; the user creates the form in **Contact → Contact Forms**, pastes it, and gives you the resulting ID. Field-tag **names must match the form's Mail tab** (`your-name`, `your-email`, …) or emails break — only restructure the surrounding HTML, never rename tags on an existing form.
     - **Elementor Pro** → render the section header only and let the user drop a native Form widget beneath it.
     - Don't assume — the field markup differs and the choice affects styling.
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

**Native-parity controls — the "Standard" set (Style tab).** Users expect the same knobs a native Heading/Image/Button widget gives them. Add a lightweight, uniform set to every widget under a `TAB_STYLE` section — enough for real editing without a full typography refactor:
- **Heading** → HTML tag (`SELECT` h1–h6/div/p/span) + heading color, on every headed section.
- **Intro/body** → text color.
- **Single-image sections** → image resolution (`Group_Control_Image_Size`, name `image`, default `full`, exclude `custom`) + a border-radius `SLIDER` (default = source radius).
- **Filled-button sections** → button background + text color.

⚠️ **Inline styles beat Elementor's selector-based control CSS**, so a normal `add_control` with a `selectors` mapping silently does nothing here. Instead **inject the control value directly into the inline style string**, defaulting to the source value so the design stays pixel-exact until the user changes it:
```php
$tc  = ! empty( $s['title_color'] ) ? $s['title_color'] : 'var(--brand)';   // fallback = source value
$tag = prefix_tag( $s['title_tag'] ?? 'h2', 'h2' );                         // whitelist-guarded tag helper
$img = Group_Control_Image_Size::get_attachment_image_src( $s['image']['id'] ?? '', 'image', $s );
if ( ! $img ) { $img = $s['image']['url']; }                                // bundled assets have no attachment ID
```
```php
<<?php echo $tag; ?> style="...;color:<?php echo esc_attr( $tc ); ?>;">…</<?php echo $tag; ?>>
```
The tag is structural and resolution swaps the `src` — both work without any CSS conflict. Resolution only resizes Media Library images (needs an attachment ID); plugin-bundled assets fall back to their URL. Ship a small `function_exists`-guarded `<prefix>_tag()` helper (whitelist of allowed tags) so every widget shares it. Full group-typography controls need a class-based refactor (inline styles can't be overridden per-control) — defer unless asked.

**Rich text — WYSIWYG prose + inline-HTML list items.** Users expect to bold a word, add a link, or format a paragraph.
- **Every prose `TEXTAREA` → `WYSIWYG`** (intro, body, descriptions, callouts, quotes, paragraphs — including inside repeaters). ⚠️ The editor wraps content in its own `<p>` tags, so it is **invalid inside a heading or an existing `<p>`**. Render WYSIWYG output inside a **block `<div>` wrapper** (move the section's inline styles onto the div) via a `wp_kses_post()` helper — never `esc_html`, which would print the tags as text:
  ```php
  <div style="…the source paragraph's inline styles…"><?php echo prefix_rich( $s['intro'] ); ?></div>
  ```
- **List-item text fields that render with an icon** (checklist rows, icon cards, numbered steps) → allow **inline HTML** (`<span>/<strong>/<a>/<br>`) via a restricted `wp_kses()` helper — keep the control a `TEXT`/`TEXTAREA` (NOT WYSIWYG: block `<p>` would break a one-per-line split or an inline label):
  ```php
  <span class="…"><?php echo prefix_inline( $line ); ?></span>
  ```
- **Do NOT convert**: heading-as-textarea fields (WYSIWYG injects `<p>` into `<h*>`) and SVG icon-path textareas (WYSIWYG corrupts the path data). Leave those as `TEXTAREA`.
- Ship two more guarded helpers beside `<prefix>_tag()`: `<prefix>_rich()` = `wp_kses_post()`, `<prefix>_inline()` = `wp_kses()` with an inline-only tag whitelist.
- **Fidelity:** the widget-isolation CSS resets `p{margin:0}`, so a single-paragraph WYSIWYG (the default) is pixel-identical to the source. Because prose wrappers are now `<div>`, adjacent `<p>/<ul>/<ol>` siblings only occur inside editor content — add one scoped rule to restore multi-block spacing there (`… :is(p,ul,ol) + :is(p,ul,ol){ margin-top:.9em }` plus list padding) without disturbing the single-paragraph layout.

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
- **Style controls on inline-styled markup**: `selectors`-based controls do nothing (inline styles win). Inject the value into the inline style string, defaulting to the source value.
- **WYSIWYG output belongs in a `<div>`, echoed with `wp_kses_post`** — never inside a heading/`<p>` (nested `<p>` breaks the markup) and never via `esc_html` (prints the tags). Icon list-item fields take inline HTML via a restricted `wp_kses`, and stay `TEXT`/`TEXTAREA`.
- **Font swaps move natural line-breaks.** If you replace the source's brand font (e.g. Sofia Pro → Manrope), any heading that relied on *natural* wrapping to break at a specific word will wrap elsewhere — the new font's metrics differ. Where the mockup shows a deliberate two-line split (e.g. plain phrase / accent phrase), force it with an explicit `<br>` before the accent rather than trusting the wrap.
- **Contact Form 7 + `wpautop`.** CF7 runs the form template through `wpautop`, so a newline between a `<label>` and its field tag becomes a stray `<br>` (extra gap after the label). In the paste-in CF7 template keep each `<label>` and its `[field]` **on one line**; use `<p>`/`<div>` blocks for structure. Same applies to the textarea/submit rows.
