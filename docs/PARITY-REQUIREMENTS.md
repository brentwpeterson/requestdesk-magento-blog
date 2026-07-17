# RequestDesk_Blog (Magento): Feature-Parity Requirements

**Status:** Draft for review
**Author:** Brent Peterson, Content Basis LLC
**Date:** 2026-07-03
**Target module:** `RequestDesk_Blog` (`requestdesk/magento-blog`), currently v1.1.0
**Reference module:** RequestDesk Connector (WordPress), currently v2.24.1
**Scope decision:** *Content + AEO engine* parity. Excludes Content Cucumber marketing chrome and the headless CMS layer.

---

## 1. Goal

Bring the Magento `RequestDesk_Blog` extension up to functional parity with the WordPress **RequestDesk Connector**, so a Magento merchant gets the same RequestDesk-powered content engine a WordPress site gets: a working native blog, bidirectional RequestDesk sync, AI-answer (AEO) optimization, FAQ/Q&A, structured data, and AI-assisted content generation, adapted to Magento's architecture (multi-store, Hyvä storefront, catalog).

Parity means **capability parity**, not a line-by-line port. Where Magento's model differs (store views vs Polylang, catalog vs WooCommerce, `RequestDesk_Core` shared client), we match the *outcome* using the Magento-native mechanism.

---

## 2. Scope

> **Parity has two reference points.** (1) the WordPress **RequestDesk Connector** (the RequestDesk-specific capabilities: sync, AEO, FAQ, generation), and (2) the **table-stakes feature set every mature Magento blog extension ships** (Mageplaza, Amasty, AheadWorks): Categories, Authors, Tags, Comments, blog Widgets/blocks, Import/migration, Configuration, and a User Guide. WordPress gets Authors, Comments, Widgets, and Categories from WordPress core for free, so the Connector plugin never re-implements them. Magento has **no native blog**, so our extension must supply all of it. Authors, Comments, Widgets, Import/migration, and the User Guide were missing from the first draft and are added below. Categories change approach (see P1-1): reuse Magento's native category tree instead of a bespoke blog taxonomy.

### In scope
- **Phase 0** (Foundation repair): make the module actually function (storefront rendering, live bugs, config/auth consistency).
- **Phase 1** (Core content parity + blog table stakes): working storefront, **Magento-native categories** (reuse the catalog category tree, not a new taxonomy), tags, **authors**, **comments**, **blog widgets/blocks**, **import/migration**, inbound-publish consolidation, enriched outbound RAG sync, FAQ/Q&A, structured data + SEO meta output, sync-log visibility, and a **user guide**.
- **Phase 2** (AEO via RequestDesk): the AEO engine (content analysis, scoring, Q&A generation, citation, freshness) runs in the **RequestDesk backend**; the Magento module consumes and displays the results and renders the resulting schema/FAQ. Not reimplemented in Magento (see D7).
- **Phase 3** (AI generation + instant indexing): AI content/Q&A generation (via RequestDesk backend proxy, see §7 D1), IndexNow submission.
- **Companion (parallel track):** a **RequestDesk Blog MCP server** so users can author and publish posts from Claude Code / any MCP client straight into Magento. Rides the module's existing REST endpoints; not Magento code, so it runs alongside the phases rather than inside them (see §5 A5 and §7 D1c).

### Out of scope (this doc)
- **CC marketing components:** partner directory, case studies, audit capture, brand asset hub, ad rotator, homepage hero, stats bar, comparison table, child grid. These are Content Cucumber's own *marketing-website* furniture (host-gated behind `requestdesk_is_cc_site()` in the WP plugin), not blog-module capabilities. A Magento merchant would not use them.
- **Headless CMS API:** WordPress serves JSON to an external SSR frontend. Magento *is* the storefront and renders natively, so this layer is not applicable.
- **Polylang-style translation linking:** superseded by Magento's native store-view scoping (partial coverage already exists via `store_id`). Revisit only if a multilingual client requires it.

---

## 3. Current Magento module state (baseline)

**Works:** blog post entity + repository + resource/collection; admin post grid; admin edit form (WYSIWYG); admin CRUD controllers; **product↔post linking** (Magento's unique strength); product/category/CMS export service to RequestDesk `/api/public/magento/sync`; post import service + hourly cron; exposed REST API (`webapi.xml`); config section with test-connection; multi-store scoping. Declarative schema (`db_schema.xml`), PHP 8.1, Magento 2.4.7+.

**Broken / stubbed / buggy (Phase 0 targets):**
1. **Storefront blog does not render.** Layouts `view/frontend/layout/hyva_blog_*.xml` reference `RequestDesk\Blog\Block\ListPosts` and `RequestDesk\Blog\Block\PostView` and templates `hyva/list.phtml` / `hyva/post/view.phtml`, but **those Block classes don't exist** and there is **no frontend `routes.xml` or `Controller/`**. `/blog` and `/blog/post/...` do not resolve.
2. **Admin Save writes a non-existent field.** `Controller/Adminhtml/Post/Save.php:86` calls `setIsActive()`; the model field is `status`. Status never persists, so posts silently stay draft. `Save.php` also never sets `store_id`.
3. **Grid column/field mismatch.** Listing uses `is_active` / `sync_status` / `requestdesk_id`; DB fields are `status` / `requestdesk_sync_status` / `requestdesk_post_id`. Those columns render empty and don't filter.
4. **Endpoint inconsistency.** `config.xml` default `https://api.requestdesk.ai` vs service hardcoded fallback `https://app.requestdesk.ai` vs local-dev docs `http://cbtextapp-backend-1:3000`.
5. **Two redundant inbound "create post" paths** with different auth: `/V1/requestdesk/blog/posts` (ACL/token via `PostManagement`) and `/V1/requestdesk/external/blog/posts` (header-key via `ExternalBlog`).
6. **Categories fully stubbed:** tables `requestdesk_blog_category` + `requestdesk_blog_post_category` exist, no code; `categoryIds` dropped with `// TODO` in `PostManagement.php:107`.
7. **Tags accepted but never stored.**

**Adjacent modules:** `RequestDesk_Core` (`requestdesk/magento-core`) is a shared config + generic `ApiClient` (Bearer auth, `/api/v1/*`) that the blog module currently **bypasses** (blog rolls its own header-key services against `/api/public/*`). `requestdesk-schema` is a planning-only AEO PDP-scoring module. The `old-blog` directory is legacy third-party blog software being displaced (not our code).

---

## 4. Parity matrix

| # | Capability | WordPress Connector | Magento today | Target phase |
|---|---|---|---|---|
| 1 | Blog entity + storefront rendering | Full (theme-rendered) | Entity present; **rendering broken**; categories/tags stubbed | **P0 + P1** |
| 2 | Product ↔ post linking | WooCommerce schema only | Native (Magento's strength) | Keep |
| 3 | Inbound publish (RD → store) | Rich: image sideload, author/date fidelity, dedup, i18n | Works but two redundant paths, thin | P1 |
| 4 | Outbound RAG sync (store → RD) | AEO-enriched, incremental, change-detect, bulk, pull endpoints | Catalog/CMS push exists; **payloads not enriched**; no blog-post push | P1 |
| 5 | FAQ / Q&A blocks + FAQPage schema | Canonical owner; shortcode + auto-append | None | P1 |
| 6 | Structured data (JSON-LD) + SEO meta output | 8-type auto-detect at `wp_head`; Yoast/RankMath passthrough | None (storefront dead) | P1 |
| 7 | Sync log + admin visibility | Sync-log table + recent activity + AEO dashboard | Grid (buggy), sync/import pages; no log table | P1 |
| 8 | AEO suite (freshness, citation, content analysis, bulk optimize) | Full (in-plugin) | Roadmap only | P2 (computed in RD, consumed by Magento) |
| 9 | AI content/Q&A generation | Direct Anthropic Claude API + model select | None (leans on RD backend) | P3 |
| 10 | IndexNow instant indexing | Full (Bing/Yandex/Seznam/Naver) | None | P3 |
| 11 | Headless CMS API | Full + usage metering | N/A, Magento is the storefront | **Out** |
| 12 | CC marketing components | Partners, case studies, audit capture, brand assets, ad rotator, hero, stats, comparison | None | **Out** |
| 13 | Multilingual | Polylang translation linking | `store_id` scoping (native) | Deferred |
| 14 | Blog categories taxonomy | WP core categories | Bespoke tables stubbed → **reuse native Magento categories** | P1 |
| 15 | Authors (profiles + author archive) | WP core authors | None | P1 |
| 16 | Comments + moderation | WP core comments | None | P1 |
| 17 | Blog blocks via Widget / CMS | WP widgets + blocks | None | P1 |
| 18 | Import / migration (WP, other Magento blog ext, CSV) | WP importers | None (legacy `old-blog` being displaced) | P1 |
| 19 | User guide / extension docs | Plugin readme/docs | None | Each release |

*Rows 14 through 19 are the standard Magento blog-extension table stakes WordPress covers via CMS core. They are gaps because Magento has no native blog.*

---

## 5. Cross-cutting architecture decisions

> **Prerequisite flag: the module family is not yet well-defined.** We currently have three RequestDesk Magento modules with unclear boundaries:
> - **`RequestDesk_Core`** (`requestdesk/magento-core`, v1.0.0): intended shared foundation. Own config, encrypted API key/URL, and a generic `ApiClient` using **Bearer auth** against `/api/v1/*`.
> - **`RequestDesk_Blog`** (`requestdesk/magento-blog`, v1.1.0): the blog module. **Does not depend on Core**; it rolls its own services with **header-key auth** (`x-requestdesk-api-key`) against **different endpoints** (`/api/public/*`).
> - **`requestdesk-schema`** (`requestdesk/magento-schema`): planning-only, no code; declares a dependency on Core but nothing enforces it.
>
> So Core and Blog are two parallel implementations of the same integration that share no client, config, or auth convention, and the schema module is a stub. **Defining the responsibility split (what Core owns vs what Blog owns) and standardizing the client/auth is a prerequisite for Phase 0/1, not just an open question.** See A1 and D2. This is the first thing to settle with the team.

These apply across phases and should be resolved as part of Phase 0/1:

- **A1. Consolidate the API client.** Adopt (or align to) `RequestDesk_Core`'s `ApiClient` as the single outbound HTTP layer, resolving the three-way endpoint conflict and the two auth conventions (`x-requestdesk-api-key` header vs `Authorization: Bearer`). Decision needed: standardize on **one** endpoint base + **one** auth scheme. *Recommendation:* have Blog depend on Core, use Core's config for endpoint/key, and pick the auth scheme the RequestDesk backend prefers for these routes.
- **A2. Single inbound publish path.** Collapse `/blog/posts` and `/external/blog/posts` into one authenticated endpoint (header-key for RD-machine callers), retire the redundant one.
- **A3. Storefront target is Hyvä.** Existing frontend DI registers with Hyvä's `CompatModuleRegistry`. Build the storefront blog for Hyvä (Tailwind templates). Confirm whether a Luma fallback is required for any client.
- **A4. Versioning.** Bump MINOR for each phase's new-feature set; PATCH for the Phase 0 bug-fix release. Keep `db_schema_whitelist.json` current with every schema change.
- **A5. MCP authoring channel (companion package).** Ship a standalone **RequestDesk Blog MCP server** that wraps the module's REST surface (`/V1/requestdesk/external/blog/posts` for publish, `/V1/requestdesk/export/*` for store context) plus RequestDesk RAG, so an MCP client (Claude Code, Claude Desktop) can pull store context, draft a post, and publish it into Magento. This is **complementary** to the in-admin Generate button (D1 a/b), not a replacement: both write through the same consolidated publish endpoint (P0-5, P1-3). Because it lives outside the Magento extension, it can be built in parallel and depends only on Phase 0/1 hardening the REST endpoints it consumes.

---

## 6. Requirements by phase

### Phase 0. Foundation repair (bug-fix release, target v1.2.0)

**P0-1 Working storefront blog (Hyvä)**
- Create `Block/ListPosts.php` and `Block/PostView.php` (or rename/retarget existing layout refs) with the getters the templates call: `getPosts()`, `getPostUrl()`, `getPost()`, `getBackUrl()`, pagination.
- Add frontend `etc/frontend/routes.xml` (route id `requestdesk_blog`, front name `blog`) and `Controller/Index/Index`, `Controller/Post/View`, `Controller/Category/View`.
- Wire list, single-post, and category-archive templates; respect `posts_per_page`, `url_prefix`, store scope, published filter.
- **Acceptance:** `/blog` lists published posts for the current store; `/blog/<url_key>` renders a single post; pagination works; drafts hidden; 404 for unknown slug.

**P0-2 Fix admin Save + status persistence**
- `Save.php` uses `setStatus()` (not `setIsActive()`), sets `store_id`, and maps the form field consistently.
- **Acceptance:** creating/editing a post in admin persists the chosen status and store; re-opening the record shows the saved values.

**P0-3 Fix admin grid columns**
- Grid columns reference real fields: `status`, `requestdesk_sync_status`, `requestdesk_post_id` (align UI component + form field names to the model/DB).
- **Acceptance:** Status / Sync Status / RequestDesk ID columns display and filter correctly.

**P0-4 Resolve config/endpoint/auth inconsistency (see A1)**
- One endpoint default, one auth scheme, no hardcoded fallbacks that contradict config.
- **Acceptance:** test-connection succeeds using only admin-config values; no code path uses a different base URL than configured.

**P0-5 Consolidate inbound publish path (see A2)**
- One documented inbound endpoint; the other retired or aliased.
- **Acceptance:** RequestDesk can create/update a Magento post through a single documented, authenticated endpoint; duplicate path removed from `webapi.xml`.

---

### Phase 1. Core content parity (feature release, target v1.3.0)

**P1-1 Blog categories (reuse native Magento categories):** do **not** build a separate blog-category taxonomy. Retire or repurpose the bespoke `requestdesk_blog_category` + `requestdesk_blog_post_category` tables and instead associate posts with the **native Magento catalog category tree** (`catalog_category_entity`). Add a post↔category relation (many-to-many to catalog categories), a config option for an optional "blog root category" to scope which categories are offered for blog use, and a storefront category archive that lists a category's published posts. Benefit: one category tree for the whole store, and blog posts can share categories with products for commerce SEO (a category page can surface related posts).
- **Acceptance:** an admin assigns a post to one or more existing Magento categories (no separate blog-category CRUD); `/blog/category/<url_key or path>` lists that category's published posts; no duplicate taxonomy is created.

**P1-2 Tags:** persist and render tags (storage + post form field + storefront tag display/archive).
- **Acceptance:** tags supplied via API or admin are stored, displayed on the post, and browsable.

**P1-3 Inbound publish fidelity (RD → store):** match WP `/publish` richness: featured-image ingest from URL, author + publish-date fidelity, update-by-id, and **dedup by url_key/title** to prevent duplicates.
- **Acceptance:** a post pushed twice updates rather than duplicates; featured image, author, and original publish date survive the round-trip.

**P1-4 Enriched outbound RAG sync (store → RD):** extend `DataExport`/export services to (a) also push **blog posts** (not just catalog/CMS), (b) enrich document payloads with Q&A pairs / citation stats / freshness where available, (c) support **incremental sync** (`modified_since` / per-record change detection via a last-sync timestamp), and (d) a **bulk "sync all"** admin action with success/skip/fail tallies.
- **Acceptance:** editing a synced product/post and re-running sync pushes only changed records; blog posts appear in the RequestDesk knowledge base; bulk sync reports tallies.

**P1-5 FAQ / Q&A:** FAQ storage (post-scoped Q&A pairs), admin editing, storefront rendering, and **FAQPage JSON-LD** output. This mirrors WP's canonical FAQ ownership.
- **Acceptance:** Q&A pairs entered/imported for a post render on the storefront and emit valid FAQPage structured data (passes Rich Results test).

**P1-6 Structured data + SEO meta output:** emit `BlogPosting`/`Article` JSON-LD + `BreadcrumbList` on post pages, and render `meta_title` / `meta_description` / canonical / OG tags from the post's SEO fields.
- **Acceptance:** post pages output valid Article + Breadcrumb JSON-LD and correct meta/OG tags.

**P1-7 Sync-log persistence + admin visibility:** a `requestdesk_blog_sync_log` table (record id, direction, entity, status, message, timestamp) surfaced as a "Recent Sync Activity" admin view; import/export services write to it.
- **Acceptance:** every inbound/outbound sync operation is logged and visible in admin with status and any error.

**P1-8 Authors:** an author entity/profile (name, bio, avatar, optional link to an admin user or customer), an author byline on posts, and an author archive page listing that author's posts. Admin grid + form under the Blog menu.
- **Acceptance:** posts display an author byline linking to an author archive; authors are managed in admin; the RD inbound-publish path can set an author by name.

**P1-9 Comments + moderation:** **native** reader comments on posts (decided, see D6) with admin moderation (approve / spam / delete), optional threading, spam controls (honeypot + rate limit), and a per-store enable/disable toggle. Third-party (Disqus) mode deferred, not built now.
- **Acceptance:** a visitor can submit a comment; it appears per the configured moderation policy; admins moderate from a grid; comments can be disabled store-wide.

**P1-10 Blog blocks via Widget / CMS:** Magento Widget types (Recent Posts, Posts by Category, Featured Post) placeable in CMS pages/blocks via the Widget UI and in layout XML, with matching layout handles.
- **Acceptance:** a merchant inserts a "Recent Posts" (and category/featured) block into any CMS page or block through the Widget UI and it renders on the storefront.

**P1-11 Import / migration:** import existing posts from (a) the legacy `old-blog` and other Magento blog extensions (Mageplaza / Amasty / AheadWorks) and (b) WordPress export / CSV. Map title, body, author, categories (to native Magento categories, per P1-1), tags, images, dates, and URL keys, with 301 redirects where slugs change. Distinct from the RequestDesk AI-content import (P1-3).
- **Acceptance:** a dry-run reports what will import; a run creates posts with mapped fields and preserves URL keys (or sets 301s); re-running is idempotent (no duplicates).

**P1-12 User guide:** a merchant/admin user guide (install, configure, connect to RequestDesk, author posts, categories/authors/tags/comments, widgets, import, sync) shipped with the extension and linked from the admin menu (the "User Guide" item in the screenshot).
- **Acceptance:** the admin "User Guide" menu opens current documentation covering every admin feature; updated each feature release.

---

### Phase 2. AEO via RequestDesk (feature release, target v1.4.0)

**Architecture (recommended direction, see D7):** the AEO *engine* lives in the **RequestDesk backend**, not reimplemented in Magento. RequestDesk already ingests store content through the RAG sync (P1-4), so it computes AEO scores, extracts Q&A pairs, finds citation-ready stats, and tracks freshness there. The Magento module is a **thin consumer**: it requests analysis, pulls results back, stores a lightweight copy, surfaces scores in admin, and renders the resulting FAQ/schema on the storefront (rendering stays in Magento; computing moves to RD). This keeps one AEO brain shared across WordPress and Magento instead of two divergent implementations.

**P2-1 AEO results store (consume, don't compute):** a lightweight per-post record cached from RequestDesk (score, ai_questions, faq_data, citation_stats, content_type, optimization_status, last_analyzed). No analysis logic in Magento; this is a read cache of RD output.

**P2-2 Request analysis + surface scores:** an admin action (and an on-sync hook) that asks RequestDesk to analyze a post/PDP and stores the returned score + content-type; the admin grid/form shows the AEO score and any gaps.

**P2-3 Freshness surfacing:** display RequestDesk's freshness signal (or compute content-age locally, since it is trivial date math) and flag stale posts in admin.

**P2-4 Citation data:** consume the citation-ready stats RequestDesk extracts and (a) show them in admin, (b) feed them into the FAQ/schema output. Already flows in the RAG payload (P1-4b).

**P2-5 Bulk AEO refresh:** admin action to (re)analyze all posts via RequestDesk with progress + tallies (async via cron/queue); pulls updated scores back.

- **Acceptance (phase):** each post shows an AEO score + content-type sourced from RequestDesk; stale posts are flagged; citation/Q&A data renders on the storefront; a bulk refresh reanalyzes the catalog without timeout. **No AEO scoring/generation logic is implemented in the Magento module itself.**
- **Dependency:** RequestDesk backend must expose AEO **compute** endpoints (analyze content, return score/Q&A/citation/freshness) and **retrieval** endpoints for the module to pull results. Confirm the backend contract before starting.

---

### Phase 3. AI generation + instant indexing (feature release, target v1.5.0)

**P3-1 AI content / Q&A generation:** generate Q&A pairs and/or draft content. **Design decision D1 (see §7):** route generation through the **RequestDesk backend** (consistent with the existing import flow and centralized keys) rather than a direct Anthropic key in Magento config. Provide a "Generate Q&A" / "Analyze" admin action on the post form.
- **Acceptance:** an admin can generate Q&A pairs for a post from the edit screen; results save to the AEO store (P2-1) and render via FAQ (P1-5).

**P3-2 IndexNow submission:** auto-generate + serve the site key file, submit post/category URLs to IndexNow on publish/update, bulk-submit with pacing, and keep a submission log.
- **Acceptance:** publishing a post submits its URL to IndexNow; the key file is served; bulk submit works in batches; submissions are logged.

---

### Companion. RequestDesk Blog MCP server (parallel track)

A standalone MCP server (not part of the Magento extension) that lets a user author and publish blog posts from Claude Code / any MCP client. It **reuses the module's existing REST surface** and RequestDesk RAG, so it adds no new Magento code beyond the Phase 0/1 endpoint hardening it depends on.

**C-1 Store-context tools:** expose read tools backed by `/V1/requestdesk/export/{products,categories,cms-pages}` and RequestDesk RAG so the model can ground a post in real catalog data.
**C-2 Publish tool:** expose a write tool backed by the consolidated inbound publish endpoint (P0-5) so a drafted post is created/updated in Magento with full fidelity (P1-3: image, author, date, categories, tags).
**C-3 Auth:** authenticate with the same header-key scheme resolved in A1/A2; keys supplied via MCP server config.
- **Acceptance:** from Claude Code, a user can pull catalog/category context, generate a post, and publish it to a target Magento store; the post appears in the admin grid and renders on the storefront.
- **Dependency:** Phase 0 (P0-5 single publish path) and Phase 1 (P1-3 inbound fidelity, P1-1 categories). Can be built in parallel once those endpoints are stable.

---

## 7. Open questions / design decisions

- **D1. AI generation path:** three options, and (a/b) vs (c) are not mutually exclusive.
  - **(a) RequestDesk-backend proxy:** Magento admin "Generate" button calls the RD backend; centralized keys; matches the existing import flow. *(Recommended for the in-admin path.)*
  - **(b) Direct Anthropic key in Magento config:** matches WordPress exactly; merchant supplies their own key. More config surface, decentralized keys.
  - **(c) RequestDesk Blog MCP server (Brent's proposal):** authoring happens in Claude Code / any MCP client, publishing straight into Magento via the existing REST endpoints (see A5 and the Companion track). Complementary to (a); needs almost no new Magento code. *Recommended as a parallel companion track.*
  - *Default assumption:* ship **(a) + (c)**, in-admin generation via the RD proxy for merchants plus the MCP for devs/power-users. Decide (b) later only if a client wants their own key.
- **D2. Core vs Blog module boundary (A1), decide first.** The module family is not yet well-defined (see the prerequisite flag in §5). Decide: does `RequestDesk_Blog` depend on `RequestDesk_Core` for the shared API client/config/auth, or does Blog stay self-contained and we retire/repurpose Core? And where does the planned `requestdesk-schema` module sit? This split needs a clear owner-per-concern (Core = shared API client + config + auth; Blog = blog entity + storefront + FAQ + sync; Schema = PDP AEO) before other work is safe to build on. *Default assumption: adopt Core as the shared foundation; Blog and Schema depend on it.*
- **D3. Auth scheme (A1/A2):** standardize inbound + outbound on which header/scheme the RequestDesk backend actually expects for these routes? Needs confirmation against the backend.
- **D4. Storefront theme (A3):** Hyvä-only, or is a Luma fallback needed for any target client?
- **D5. AEO scope overlap:** does the planned `requestdesk-schema` PDP-AEO module absorb P2 PDP scoring, or does Blog own AEO for posts and Schema own it for products?
- **D6. Comments implementation: DECIDED.** Build **native Magento comments** with built-in moderation + spam controls. Third-party (Disqus) mode is deferred, not built now; revisit only if a client needs it.
- **D7. AEO computed in RequestDesk (Brent's proposal): recommended.** The AEO engine runs in the RequestDesk backend; the Magento module consumes results rather than reimplementing analysis/scoring/generation in PHP (only schema/FAQ rendering stays in Magento). Rationale: RD already ingests the content via RAG sync, it centralizes one AEO brain across WordPress + Magento, and it matches the D1 proxy pattern. *Default direction: yes, handle AEO in RequestDesk.* Requires RD to expose AEO compute + retrieval endpoints (see §8). This also largely resolves D5: RD owns AEO computation for both posts and PDPs; the Magento `requestdesk-schema` module (if kept) becomes a storefront renderer, not a second engine.

**Decided (Brent, 2026-07-03):**
- Blog categories **reuse the native Magento category tree** (P1-1), not a bespoke blog taxonomy.
- Comments are **native** (P1-9); Disqus deferred.
- All other standard blog-extension features (Authors, Comments, Widgets, Import/migration, User Guide) are in scope for Phase 1.

---

## 8. Risks & dependencies

- **Backend contract:** Phases 1 through 3 depend on the RequestDesk backend exposing the matching endpoints (enriched RAG ingest, **AEO compute + retrieval for Phase 2**, generation proxy, sync-status reconciliation). Confirm the backend side per phase. Phase 2 in particular is gated on RequestDesk exposing AEO analysis + results endpoints (D7).
- **Hyvä coupling:** storefront work assumes Hyvä; a Luma client would add rendering scope (D4).
- **Local dev environment:** local dev requires the Magento container joined to the RequestDesk Docker network after every `docker-compose` cycle.
- **Foundation-first:** Phases 1 through 3 (and the MCP companion) must not start until Phase 0 lands, or features get built on a non-rendering storefront / unstable endpoints.

---

## 9. Suggested sequencing

1. **v1.2.0, Phase 0:** make it work; foundational, unblocks everything.
2. **v1.3.0, Phase 1:** the real "parity" release for a merchant: working blog + native categories + authors + tags + comments + widgets + import/migration + FAQ + schema + clean sync + user guide. This is now large; consider splitting into **1a** (storefront, categories, authors, tags, comments, widgets, import, user guide, the "looks like a real blog" release) and **1b** (RD sync fidelity, enriched RAG, FAQ, structured data, sync log).
3. **v1.4.0, Phase 2:** AEO via RequestDesk (module consumes RD-computed AEO; gated on the RD backend exposing AEO endpoints).
4. **v1.5.0, Phase 3:** AI generation + IndexNow.
5. **MCP companion:** build in parallel once Phase 0/1 endpoints are stable; ships on its own cadence.

CC marketing components (row 12) and headless (row 11) remain out of scope by decision.
