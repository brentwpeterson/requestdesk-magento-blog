# RequestDesk_Blog — Feature Backlog (for Jeel)

Goal: build `RequestDesk_Blog` up to a full blog module — the feature set in the reference admin menu (Posts, Categories, Authors, Tags, Comments, Blog Blocks via Widget, Import) — **rendering on both Luma and Hyvä**.

This is a separate work-stream from the AEO scoring module (`RequestDesk_Aeo`). They connect at one point: the blog is where "supporting content to enhance a product page" lives, and blog posts should emit clean schema and link to/from products.

---

## Where the module stands today (gap analysis)

| Feature | State today | What's missing |
|---|---|---|
| **Posts** | ✅ Full admin CRUD, repository, `featured_image` **field exists** in the table | Featured image is stored but never rendered; no Luma view |
| **Categories** | ⚠️ **Data only** — `requestdesk_blog_category` + `requestdesk_blog_post_category` tables exist | No admin CRUD, no post-assignment UI, no category pages |
| **Product linking** | ✅ `requestdesk_blog_product` table + Sync Products | — |
| **Import** | ✅ Import Posts admin controller | Verify parity with the reference menu's Import |
| **Authors** | ❌ Posts have a plain `author` varchar only | No author entity, admin, bylines, or author pages |
| **Tags** | ❌ None | Entire feature |
| **Comments** | ❌ None | Entire feature |
| **Blog Blocks via Widget** | ❌ None | Entire feature |
| **Frontend theme coverage** | ⚠️ **Hyvä only** — `hyva_blog_*` layouts, no `routes.xml`, no controllers | No Luma frontend at all; no frontend route |

**The load-bearing gap:** the module ships no frontend route/controller and only Hyvä templates. Nothing renders on Luma today. Item B1 below is the foundation everything else's frontend depends on.

---

## Backlog

Each item tagged by layer so the frontend/backend split is explicit. Jeel owns **frontend**; the **backend/full-stack** items need a decision on who builds them (flag to Brent).

### B1 — Luma frontend + frontend route  `[backend + frontend]`  · FOUNDATION
The blog has no frontend route or controllers, and only Hyvä templates. Add the frontend route (`etc/frontend/routes.xml`), the index + post-view controllers, and **Luma** layout handles + templates alongside the existing Hyvä ones.
- **AC:** a published post renders at a real URL on a Luma store; the same URL renders on Hyvä; both read the same data/ViewModels; no duplicated logic between themes.

### B2 — Featured image (render + signals)  `[frontend + light backend]`
The `featured_image` field exists but is never output. Render it as the post hero on both themes, and drive `og:image` and the post's schema `image` from the same field.
- **AC:** hero shows on Luma and Hyvä; `<meta property="og:image">` and JSON-LD `image` both resolve to the featured image; empty field degrades cleanly.

### B3 — Categories (finish the feature)  `[full-stack]`
Data tables exist. Add admin CRUD, a post→category assignment UI on the post form, category listing pages, and category nav.
- **AC:** create/edit/delete categories in admin; assign posts; category page lists its posts on both themes; breadcrumb + schema.

### B4 — Tags  `[full-stack]`
New entity. Tag table, admin CRUD, post tagging UI, tag archive pages.
- **AC:** tag posts in admin; tag page lists tagged posts on both themes.

### B5 — Authors  `[full-stack]`
Replace the flat `author` varchar with an author entity (name, bio, avatar, links). Admin CRUD, post→author assignment, byline on the post, author archive pages.
- **AC:** author byline renders on the post (both themes); author page lists their posts; author feeds schema `author`.

### B6 — Comments  `[full-stack]`
New. Comment entity, frontend comment form + threaded display, admin moderation queue (approve/spam/delete), a spam guard (honeypot or similar), and gating (guest vs customer per config).
- **AC:** visitors submit comments; admin moderates; approved comments render on both themes; no comment renders before approval.

### B7 — Blog Blocks via Widget  `[full-stack]`
A Magento widget to embed blog content (recent posts, posts by category/tag, related-to-this-product) into CMS pages, content, or the PDP.
- **AC:** widget is insertable via the admin widget UI; renders on both themes; a "related posts" mode can surface on a product page (this is the AEO cross-link).

### B8 — Import (verify + extend)  `[backend]`
Import exists. Confirm it matches the reference menu's Import and covers categories/tags/authors once those land.
- **AC:** import brings in posts with their categories/tags/authors/featured image intact.

---

## Cross-cutting requirements (apply to every item with a frontend)

- **Both themes, one source.** Every frontend item ships a Luma template AND a Hyvä template, both reading the same ViewModel/data. No schema/business logic duplicated in templates. (Same rule as the AEO storefront handoff in `../requestdesk-aeo/FRONTEND.md`.)
- **AEO alignment.** Posts emit clean structured data (Article/BlogPosting, plus FAQ where the content is Q&A), and cross-link with products (post → product, and related-posts widget on the PDP). Featured image, alt text, and headings are answer-engine signals — treat them as first-class.
- **No dead frontend.** Don't ship an admin feature (a category, an author) with no page to view it on. Data + admin + both-theme rendering land together per item.

## Suggested order

B1 (foundation) → B2 (featured image, quick win) → B3 (categories, data already there) → B5 (authors) → B4 (tags) → B7 (widget/related-posts, the AEO cross-link) → B6 (comments, heaviest) → B8 (import parity, last).

## Open question for Brent

Jeel owns frontend. B3–B6 are full-stack (new entities, admin CRUD, migrations). Decide whether Jeel builds these end-to-end or only the frontend half while the data/admin layer is built on the main dev track.
