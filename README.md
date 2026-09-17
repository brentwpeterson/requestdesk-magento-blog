# RequestDesk Blog Extension for Magento 2

[![Magento 2.4.7 – 2.4.9](https://img.shields.io/badge/Magento-2.4.7%20–%202.4.9-orange.svg)](https://magento.com)
[![PHP 8.1 – 8.5](https://img.shields.io/badge/PHP-8.1%20–%208.5-blue.svg)](https://php.net)
[![License: OSL-3.0](https://img.shields.io/badge/License-OSL--3.0-green.svg)](https://opensource.org/licenses/OSL-3.0)

A native blog extension for Magento 2 with full [RequestDesk](https://requestdesk.ai) integration. Create AI-powered blog content in RequestDesk and automatically sync it to your Magento store, or sync your product catalog to RequestDesk for AI-assisted content creation.

**[Get Started with RequestDesk for Magento →](https://requestdesk.ai/magento)**

## Why This Extension?

Unlike Shopify or WordPress, **Magento has no built-in blog functionality**. This extension provides:

- **Complete Blog System** - Posts, categories, SEO metadata, and frontend templates
- **Product-to-Post Linking** - A critical Magento feature for e-commerce SEO
- **Bidirectional Sync** - Push products to RequestDesk, pull blog posts back
- **AI Content Integration** - Leverage RequestDesk's AI to generate product-focused blog content
- **Multi-Store Support** - Full store scoping for Magento multi-store setups

## Features

### Blog Management
- Full CRUD for blog posts via admin panel (Content → RequestDesk Blog → Posts)
- SEO fields: meta title, meta description, URL keys
- **Short Description** — a second WYSIWYG field, above Content, that supplies the
  teaser on the listing cards. Left empty, a card falls back to an automatic
  plain-text excerpt of the post body, so the field can be filled in gradually.
  An authored value is emitted as **markup**, not escaped text, so hiding an
  element in the editor takes effect on the card exactly as it does on the post;
  its length is trimmed visually by CSS rather than truncated in PHP
- Featured images, plus a proper Open Graph head block (`og:title/description/image`)
- Draft/Published status workflow via an Active toggle
- Per-post **Allow Comment** toggle
- **Paginated listing** — `?p=` for the page and `?limit=` for a per-request page
  size, capped so `?limit=99999` cannot pull the whole table
- Store-scoped content

### Taxonomy & authorship (reuse-first)
This extension reuses native Magento constructs instead of inventing parallel ones:
- **Categories** reuse **native Magento categories** — assign posts to real catalog
  categories on the post form; a post links back to its category page and the blog
  can be filtered by category.
- **Authors** reuse **native admin users**, extended by a public **Author Profile**
  (display name, bio, avatar, link). Managed at Content → RequestDesk Blog → Authors;
  bylines, author pages, and schema all resolve through it, with a free-text fallback.
- **Tags** are a blog-owned taxonomy with their own admin grid
  (Content → RequestDesk Blog → Tags), tag archive pages, and schema keywords.

### Comments
- Guest comment form with form-key CSRF protection and a honeypot spam guard
- Moderation grid (pending / approved / spam) with mass Approve / Spam / Delete
- Approved-only display; `commentCount` and `comment[]` added to post schema

### Answer Engine Optimization (AEO) by default
- Every post emits **`BlogPosting`** JSON-LD
- Posts with attached Q&A also emit **`FAQPage`** JSON-LD and render a visible FAQ
- Q&A is powered by the shared **[RequestDesk_Qa](https://github.com/brentwpeterson/requestdesk-magento-qa)**
  library, so the same pair can appear on a post *and* a product

### Blocks / widget
- Native Magento widget: recent posts, by-category, or **related-to-current-product**
  (an AEO cross-link that surfaces posts sharing the product's categories on the PDP)

### RequestDesk Integration
- **Product Export**: Sync your Magento product catalog to RequestDesk's knowledge base
- **Post Import**: Pull AI-generated blog posts from RequestDesk. Import matches an
  incoming author name to a native admin user (else keeps the free-text byline) and
  auto-creates + links tags.
- **Sync Status Tracking**: Monitor which posts are synced, pending, or failed
- **Automated Import**: Hourly cron job for automatic post imports
- **API Key Authentication**: Secure communication via `X-RequestDesk-Key` header

### Product Linking
- Link blog posts to related products
- Display related posts on product pages
- Show related products within blog posts
- Semantic search via RequestDesk RAG for smart product-post matching

### REST API
Complete API for headless/PWA implementations and RequestDesk communication.

### Frontend Templates
- Responsive blog listing page with its own route (`/blog`)
- Individual post view, author pages, tag archives, category-filtered listing
- **Hyvä Theme Support**: templates for Hyvä-based stores

## Requirements

- Magento Open Source or Adobe Commerce 2.4.7 – 2.4.9
- PHP 8.1 – 8.5
- **[`requestdesk/magento-qa`](https://github.com/brentwpeterson/requestdesk-magento-qa)** — optional. The shared Q&A library that powers on-post FAQ + FAQPage schema. It is declared under `suggest`, not `require`, so Composer does **not** pull it automatically: install it yourself with `composer require requestdesk/magento-qa` when you want on-post FAQs. The blog runs standalone without it.
- RequestDesk account with API key (only needed for the RequestDesk sync/import features)

### Version support, and what has actually been tested

The composer constraints (`php: ^8.1`, `magento/framework: ^103.0`) already
resolve against every release below — nothing needs widening to install on the
newest Magento.

| Magento | ships framework | supported PHP (per Magento) | our status |
|---|---|---|---|
| 2.4.7-p3 | 103.0.7-p3 | 8.1 – 8.3 | **runtime-tested** — grids, post form, migration, config structure |
| 2.4.8 | 103.0.8 | 8.2 – 8.4 | static only |
| 2.4.9 | 103.0.9 | 8.3 – 8.5 | static only |

PHP 8.5 is supported by Magento from **2.4.9** onward; 2.4.8 stops at 8.4. So a
PHP 8.5 target means a 2.4.9 target — the two move together.

"Static only" means: every `.php` and `.phtml` file compiles under a real PHP
8.5 runtime, and the module is clean against the PHP 8.4 implicit-nullable
deprecation and every statically-detectable deprecation in php-src's `UPGRADING`
for PHP 8.5 (non-canonical casts, `case ...;`, backtick exec, `curl_close`,
`finfo_close`, `DATE_RFC7231`, `__sleep`/`__wakeup`, `__debugInfo` returning
null, `get_defined_functions($exclude_disabled)`). For scale, Magento 2.4.7's own
`magento/framework` has 493 hits across those same checks.

What static analysis cannot settle, and what a 2.4.9 + PHP 8.5 install still
needs to confirm: output inside user output handlers, constant redeclaration,
incrementing non-numeric strings, `null` used as an array offset, and closure
binding/rebinding. Those are runtime-shaped. Do not read the table above as
"certified on 2.4.9" until that install exists.

### Optional companion

- **[`requestdesk/magento-aeo`](https://github.com/brentwpeterson/requestdesk-magento-aeo)** — recommended, not required. Adds product AEO scoring and product FAQ schema from the same shared Q&A library. The blog has no code dependency on it, so you can disable `RequestDesk_Aeo` or swap in your own AEO module and the blog keeps working. Declared via composer `suggest`.

## Installation

### Via Composer (Recommended)

`requestdesk/magento-qa` is optional and is **not** pulled in automatically. Add it separately if you want the on-post FAQ and FAQPage schema.

```bash
composer require requestdesk/magento-blog
# add the optional AEO companion too, if you want it:
# composer require requestdesk/magento-aeo
bin/magento module:enable RequestDesk_Qa RequestDesk_Blog
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

### Manual Installation

Install the QA library **first** (blog depends on it):

1. Copy the modules into `app/code/RequestDesk/`:
   - `RequestDesk/Qa`  (required)
   - `RequestDesk/Blog`
   - `RequestDesk/Aeo`  (optional)

2. Enable and install (QA must be enabled before or with Blog):
```bash
bin/magento module:enable RequestDesk_Qa RequestDesk_Blog
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:clean
```

### Verify Installation

```bash
bin/magento module:status RequestDesk_Blog
# Should output: Module is enabled
```

## Upgrading

### If `setup:upgrade` aborts in SchemaBuilder, run the repair command first

Installs created before 1.4.0 can carry an orphaned foreign key: the
`requestdesk_blog_post_category` table holds an FK pointing at
`requestdesk_blog_category`, a table that was dropped without removing the
constraint. On an affected install two things happen — every category insert
fails with MySQL error 1452, and `setup:upgrade` itself dies inside
`SchemaBuilder` before any patch gets a chance to run.

That last part is why the repair ships as a **console command** rather than a
schema patch: on an affected install, patches never execute. Run it *before*
`setup:upgrade`:

```bash
bin/magento requestdesk:blog:repair-schema
bin/magento setup:upgrade
```

The command is safe to run on a healthy install — it inspects the constraint and
exits without changing anything if there is nothing to repair.

### Authors are backfilled automatically, once

From 1.4.2 a data patch creates one author record per distinct byline found on
your posts and points the posts at it. Before that, authors only existed if they
were linked to a Magento admin account, so most installs showed an empty Author
dropdown. Nothing is required of you; the legacy byline column is left in place
as a fallback and is not dropped.

### If you migrated from Amasty before 1.6.4, repair the author links

The patch above runs **once** and is then recorded in `patch_list`, so it cannot
help posts that arrived afterwards. Any post migrated from Amasty by a pre-1.6.4
build carries a broken author link: that migration wrote an `admin_user.user_id`
into `requestdesk_blog_post.author_id`, which is a foreign key onto
`requestdesk_blog_author.author_id`. Posts end up either pointing at an author
record that does not exist, or at nothing at all, and the Author grid stays
empty.

`setup:upgrade` will not tell you. Declarative schema runs its DDL with
`foreign_key_checks` disabled, so it adds the author foreign key straight over
the top of violating rows. The constraint ends up present while the data beneath
it does not satisfy it.

Run the repair, then upgrade:

```bash
bin/magento requestdesk:blog:repair-authors --dry-run   # report only
bin/magento requestdesk:blog:repair-authors
bin/magento setup:upgrade
```

It rebuilds each link from the post's byline, reusing an existing author of the
same name rather than duplicating one, and clears the dangling id on any post
that has no byline to rebuild from. Safe and idempotent on a healthy install:
it reports nothing to repair and writes nothing.

### Backfilling Short Description onto posts you already migrated

`short_description` is new, so every post migrated before it existed has the
column empty. The Amasty migration used to skip any post whose `url_key` was
already present, which meant a re-run could never fill it in — the only way to
get the field was to delete the post and import it again.

It now examines an existing post instead of passing over it. If the Amasty row
has a teaser and yours is still empty, that one column is filled in place with a
direct `UPDATE`; everything else about the post is untouched, and a value that is
already there is never overwritten. So the command is safe to re-run and safe to
run over posts you have since edited by hand.

```bash
bin/magento setup:upgrade                                  # creates the column
bin/magento requestdesk:blog:migrate-amasty --dry-run      # reports, writes nothing
bin/magento requestdesk:blog:migrate-amasty
```

The summary gains a `short descs:` line counting the posts filled in.

Two limits worth knowing. The command only reads Amasty rows with
`status = 2` (published), so a post whose Amasty source has since been
unpublished is not backfilled. And `--limit` applies to the source rows it reads,
so a limited run only backfills within that slice.

Amasty's teaser column has been spelled differently across releases, so it is
probed rather than assumed — `short_content`, `short_description`, `post_teaser`,
`teaser`, `excerpt`, in that order. If your install uses another name the command
backfills nothing and does **not** error. Check with:

```sql
SHOW COLUMNS FROM amasty_blog_posts LIKE '%short%';
```

and add the name to `AMASTY_SHORT_COLUMNS` in
`Console/Command/MigrateAmastyCommand.php` if it is not in the list.

### Backfilling publish dates onto posts you already migrated

The migration never read Amasty's `published_at`, so `created_at` on every
migrated post took its `CURRENT_TIMESTAMP` default — the moment the import ran.
On a real archive that collapses years of posts onto one or two days and makes
every freshness signal on the blog wrong. It now carries the source date across
on create, and corrects posts that were already migrated.

```bash
bin/magento requestdesk:blog:migrate-amasty --dry-run      # reports, writes nothing
bin/magento requestdesk:blog:migrate-amasty
```

The summary gains a `publish dates:` line counting the posts corrected.

`published_at` is preferred, falling back to the Amasty row's own `created_at`
where `published_at` was never set. Zero dates are rejected rather than parsed.

**What it will and will not overwrite.** `created_at` can never be "empty" the
way `short_description` can — the column defaults to `CURRENT_TIMESTAMP` — so
emptiness cannot be the test for whether a value is ours to replace. The test is
that the stored date is **later** than the source's: an import stamp always is,
because it was written long after the post was published. A date someone moved
deliberately to an earlier point is left alone. Once corrected the two match, so
a re-run is a no-op rather than a rewrite.

The same limits as the short-description backfill apply: only Amasty rows with
`status = 2` are read, and `--limit` applies to the source rows, so a limited run
only corrects within that slice.

### Moving blog images off Amasty's folder (1.10.2)

Amasty keeps blog images under `pub/media/amasty/blog` and stores a featured
image as a path relative to that folder (`MM26IN.png`,
`uploads/2022/05/Evrig_Homepage.png`). Migrated verbatim, those resolved to
`/media/<file>` and 404'd. Post bodies link into the same folder through
`{{media url=...}}` directives, `.renditions` copies and absolute `/media/` URLs.

The blog's images now live in `pub/media/blog`. Two steps, in this order.

**1. Copy the files on the server** (a copy, not a move, while Amasty Blog is
still serving the old pages). From the Magento root:

```bash
rsync -a --exclude 'cache/' pub/media/amasty/blog/ pub/media/blog/
rsync -a pub/media/.renditions/amasty/blog/ pub/media/.renditions/blog/
find pub/media/amasty/blog -type f -not -path '*/cache/*' | wc -l   # these two
find pub/media/blog -type f | wc -l                                  # should match
```

`cache/` is Amasty's resized copies and is not referenced by the blog.

**2. Repoint the posts.** New migrations write `blog/<file>` and rewrite body
links as they go. Posts migrated before 1.10.2 are repaired by re-running the
migration:

```bash
bin/magento requestdesk:blog:migrate-amasty --dry-run      # reports, writes nothing
bin/magento requestdesk:blog:migrate-amasty
```

The summary gains an `image paths:` line. A featured image is moved only while
it still holds the Amasty value (equal to the source `post_thumbnail`, or naming
`amasty/blog/`); one picked by hand after the migration is left alone. Body
links are rewritten only where `amasty/blog/` follows `{{media url=` or
`/media/` (optionally through `.renditions/`), so prose and links to amasty.com
are untouched. Only the changed columns are written, and a re-run is a no-op.

Delete `pub/media/amasty/blog` only after Amasty Blog is switched off.

### Blog URLs in the XML sitemap (1.10.2)

On a store moving off Amasty Blog, blog sitemap entries came from the
`amasty/blog-sitemap` add-on, so switching Amasty off drops the blog out of the
sitemap. The module now adds its own URLs to Magento's XML sitemap through
`Magento\Sitemap\Model\ItemProvider\Composite`, so the sitemap you already
generate (Marketing > Site Map, or the sitemap cron) includes them. Nothing new
to schedule.

It emits `/blog`, each published post, and each category, tag and author archive
with at least one published post, with `lastmod` from the newest post in each.
Settings are under **Stores > Configuration > Catalog > XML Sitemap > Blog
Options (RequestDesk)**: on/off (default on), frequency (default weekly) and
priority (default 0.5). Regenerate the sitemap after upgrading.

## Configuration

Navigate to **Stores > Configuration > RequestDesk > Blog**

### General Settings

| Setting | Description |
|---------|-------------|
| Enable Blog | No takes the blog off the storefront: every blog page returns 404, the comment endpoint refuses posts, blog widgets render nothing and the blog leaves the XML sitemap. The admin, the REST API and the RequestDesk import keep working |
| Blog Title | Title displayed on blog listing page |
| Posts Per Page | Number of posts per page (default: 10) |

### RequestDesk API Configuration

| Setting | Description |
|---------|-------------|
| API Key | Your RequestDesk API key (encrypted in database) |
| RequestDesk API Endpoint | API URL (default: `https://app.requestdesk.ai`) |
| Test Connection | Button to verify API connectivity |

### Automated Import

| Setting | Description |
|---------|-------------|
| Enable Automatic Import | Import published posts from RequestDesk every hour |

### Blog URL Prefix

`news` puts the listing at `/news`, posts at `/news/<url-key>`, archives at
`/news/category/<url-key>` and the id forms at `/news/post/view/id/N`. Links,
pagination, the comment form, the JSON-LD `@id` and the XML sitemap all follow,
and the old `/blog` addresses return 404 rather than serving the same pages at a
second URL.

One path segment, lowercase letters, numbers, hyphens and underscores. The admin
refuses a value with a slash or a space, and one that is already another
module's front name (`checkout`, `customer`), because the standard router would
reach that module first and the blog would never answer. `config:set` runs the
same validation; a value written straight into `core_config_data` does not, and
a malformed one is logged as an error while the blog stays on `/blog`.

The setting is per store view, so two store views can run the blog on different
prefixes.

What it does not touch: links to the blog in your own theme, a top menu item or
a CMS block still point where they were written. Flush the full page cache after
changing it, which the admin marks as invalid for you.

### SEO Settings

| Setting | Description |
|---------|-------------|
| Blog URL Prefix | First path segment of every blog address. See below. Empty means `blog` |
| Default Meta Title | Default meta title for blog listing |
| Default Meta Description | Default meta description for blog listing |

## Admin Panel

### Content > RequestDesk Blog > Posts

Manage all blog posts with:
- Grid view with filtering and sorting
- Edit/View/Delete actions
- Sync status indicators
- RequestDesk Post ID tracking

### Content > RequestDesk Blog > Import Posts

Manual import interface:
- Test API connection
- Import posts by status (published/draft)
- View import results

### Content > RequestDesk Blog > Sync Products

Export products to RequestDesk:
- Test API connection
- Sync all products or limited batches
- View sync statistics

### Content > RequestDesk Blog > Tags

Create, edit, and delete blog tags (auto-generated URL keys). Tags are assigned
to posts on the post form and drive tag archive pages and schema keywords.

### Content > RequestDesk Blog > Authors

Manage public author profiles that extend native admin users (display name, bio,
avatar, link). A post's author is assigned on the post form; the profile enriches
the byline, author page, and schema.

### Content > RequestDesk Blog > Comments

Moderate reader comments: filter by status, and mass Approve / Spam / Delete.
Only approved comments render on the frontend.

### Content > Q&A Library > Q&A Pairs

Provided by the required `RequestDesk_Qa` module. Create reusable Q&A pairs, then
attach them to posts (and products) to drive on-page FAQ and `FAQPage` schema.

## REST API Endpoints

### Blog Post Management (JWT Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/V1/requestdesk/blog/posts` | Create or update post |
| `GET` | `/V1/requestdesk/blog/posts` | List all posts |
| `GET` | `/V1/requestdesk/blog/posts/:postId` | Get single post |
| `DELETE` | `/V1/requestdesk/blog/posts/:postId` | Delete post |
| `PUT` | `/V1/requestdesk/blog/posts/:postId/sync-status` | Update sync status |

### Product Linking (JWT Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/V1/requestdesk/blog/posts/:postId/products` | Link products to post |
| `GET` | `/V1/requestdesk/blog/posts/:postId/products` | Get linked products |
| `GET` | `/V1/requestdesk/blog/products/:productId/posts` | Get posts for product |

### Data Export (API Key Auth via `X-RequestDesk-Key`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/V1/requestdesk/export/test` | Test connection |
| `GET` | `/V1/requestdesk/export/products` | Export products |
| `GET` | `/V1/requestdesk/export/categories` | Export categories |
| `GET` | `/V1/requestdesk/export/cms-pages` | Export CMS pages |

### External Blog API (API Key Auth via `X-RequestDesk-Key`)

These endpoints allow RequestDesk to push content to Magento:

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/V1/requestdesk/external/blog/test` | Test connection |
| `POST` | `/V1/requestdesk/external/blog/posts` | Create blog post |
| `GET` | `/V1/requestdesk/external/blog/posts` | List blog posts |
| `GET` | `/V1/requestdesk/external/blog/posts/:postId` | Get single post |
| `PUT` | `/V1/requestdesk/external/blog/posts/:postId` | Update post |
| `DELETE` | `/V1/requestdesk/external/blog/posts/:postId` | Delete post |

## Database Schema

### `requestdesk_blog_post`

Main blog posts table with RequestDesk sync tracking.

| Column | Type | Description |
|--------|------|-------------|
| `post_id` | int | Primary key |
| `title` | varchar(255) | Post title |
| `content` | mediumtext | Post content (HTML) |
| `short_description` | mediumtext | Listing-card teaser (HTML, nullable). Null means "never written" — the Amasty backfill relies on that being distinguishable from an empty string |
| `url_key` | varchar(255) | SEO-friendly URL slug |
| `meta_title` | varchar(255) | SEO meta title |
| `meta_description` | text | SEO meta description |
| `featured_image` | varchar(255) | Featured image path |
| `status` | smallint | 0=Draft, 1=Published |
| `comments_enabled` | smallint | 0=No, 1=Yes (default 1, so posts predating the column keep comments on) |
| `author` | varchar(255) | Author name (free-text fallback byline) |
| `author_id` | int | FK to `requestdesk_blog_author.author_id` (nullable, `SET NULL`). Not `admin_user.user_id` — that confusion is what 1.6.4's `repair-authors` command exists to undo |
| `store_id` | int | Magento store ID |
| `requestdesk_post_id` | varchar(50) | RequestDesk post ID |
| `requestdesk_sync_status` | varchar(20) | synced/pending/failed |
| `requestdesk_last_sync` | timestamp | Last sync timestamp |
| `created_at` | timestamp | Creation date |
| `updated_at` | timestamp | Last update date |

### `requestdesk_blog_author_profile`

Public profile that extends a native admin user (keyed by `admin_user_id`).

| Column | Type | Description |
|--------|------|-------------|
| `admin_user_id` | int | Primary key, FK to `admin_user.user_id` (`CASCADE`) |
| `display_name` | varchar(255) | Public byline name (overrides admin name) |
| `bio` | text | Author bio |
| `avatar` | varchar(255) | Avatar image path |
| `url` | varchar(255) | Author link (site / social) |

### `requestdesk_blog_post_category`

Links posts to **native Magento categories** — `category_id` is an FK to
`catalog_category_entity.entity_id` (`CASCADE`). There is no separate blog
category table; the invented taxonomy was removed in favor of catalog reuse.

### `requestdesk_blog_tag` / `requestdesk_blog_post_tag`

Blog-owned tags (`tag_id`, `name`, `url_key`) and their many-to-many link to
posts. Deleting a tag cascades its post links.

### `requestdesk_blog_comment`

Reader comments: `comment_id`, `post_id` (FK, `CASCADE`), `author_name`,
`author_email`, `content`, `status` (pending/approved/spam), timestamps.

> Q&A pairs live in the shared `RequestDesk_Qa` module
> (`requestdesk_qa_pair` + polymorphic `requestdesk_qa_link`), not in this schema.

### `requestdesk_blog_product`

Product-to-post linking (critical for Magento e-commerce SEO).

| Column | Type | Description |
|--------|------|-------------|
| `id` | int | Primary key |
| `post_id` | int | Blog post ID |
| `product_id` | int | Magento product entity ID |
| `position` | int | Display position |

## Cron Jobs

| Job | Schedule | Description |
|-----|----------|-------------|
| `requestdesk_blog_import_posts` | Every hour (`0 * * * *`) | Imports published posts from RequestDesk |

Enable/disable via **Stores > Configuration > RequestDesk > Blog > Automated Import**.

## Frontend URLs

| Route | Description |
|-------|-------------|
| `/blog` | Blog listing page |
| `/blog?p=2` | Listing, page 2. `&limit=25` overrides the configured page size |
| `/blog/:urlKey` | Single post view (preferred) |
| `/blog/post/view/id/:postId` | Single post view, id form — still routed, so old links keep working |
| `/blog/category/:urlKey` | Category listing |
| `/blog/author/:urlKey` | Author archive |
| `/blog/tag/:urlKey` | Tag archive |

`/blog/:urlKey` is served by `Controller\Router`, which runs only after Magento's
standard router has failed to match. Nothing is written to `url_rewrite` and there
are no redirects to maintain.

> **Router priority.** The router registers at `sortOrder 50` in
> `etc/frontend/di.xml`. Amasty_Blog registers its own router for the same `/blog`
> prefix at `60`, and on a tie Amasty is reached first — which sends every pretty
> post URL to the legacy Amasty page instead of this module's. If you do not have
> Amasty_Blog installed, `60` is equally fine.

## ACL Permissions

| Resource | Description |
|----------|-------------|
| `RequestDesk_Blog::blog` | Access RequestDesk Blog section |
| `RequestDesk_Blog::view` | View blog posts |
| `RequestDesk_Blog::manage` | Create/edit/delete blog posts |
| `RequestDesk_Blog::sync` | Sync products to RequestDesk |
| `RequestDesk_Blog::import` | Import posts from RequestDesk |
| `RequestDesk_Blog::config` | Access configuration |

## How It Works

### Product Sync Flow (Magento → RequestDesk)

```
1. Admin clicks "Sync Products" in Magento
2. Extension collects visible products with:
   - Name, SKU, price, description
   - Categories, images, attributes
3. Products sent to RequestDesk API
4. RequestDesk stores in knowledge base
5. AI can now generate content about your products
```

### Post Import Flow (RequestDesk → Magento)

```
1. Create blog post in RequestDesk (manually or AI-generated)
2. Set post status to "Published"
3. Hourly cron job runs OR admin clicks "Import Posts"
4. Extension fetches posts via RequestDesk API
5. Posts created/updated in Magento
6. Sync status reported back to RequestDesk
```

### API Key Authentication

External API endpoints use header-based authentication:

```bash
curl -X GET "https://your-store.com/rest/V1/requestdesk/export/products" \
  -H "X-RequestDesk-Key: your-api-key"
```

## Hyvä Theme Support

The extension includes optimized templates for [Hyvä Theme](https://hyva.io/):

- `view/frontend/templates/hyva/list.phtml` - Blog listing
- `view/frontend/templates/hyva/post/view.phtml` - Post detail
- `view/frontend/layout/hyva_blog_*.xml` - Layout handles

These templates use Alpine.js and Tailwind CSS patterns consistent with Hyvä.

> **If a template edit appears to do nothing, check for a theme override first.**
> A file at `app/design/frontend/<Vendor>/<theme>/RequestDesk_Blog/templates/...`
> wins over the module's copy at the same path, and Magento gives no warning that
> the module file is being shadowed. Two separate bugs on this install came from
> editing the module template while the theme copy was the one rendering.
>
> ```bash
> find app/design -path '*RequestDesk_Blog/templates/*' -name '*.phtml'
> ```

## Troubleshooting

### "Invalid security or form key" Error

Admin URLs require form keys. Always navigate via the admin menu:
**Content > RequestDesk Blog > Posts**

### API Connection Failed

1. Verify API key in configuration
2. Check endpoint URL (default: `https://app.requestdesk.ai`)
3. Use "Test Connection" button to diagnose
4. Check `var/log/system.log` for detailed errors

### Posts Not Importing

1. Ensure cron is running: `bin/magento cron:run`
2. Check "Enable Automatic Import" is set to Yes
3. Verify posts are "Published" status in RequestDesk
4. Check `var/log/system.log` for import errors

### Products Not Syncing

1. Verify products are enabled and visible
2. Check API key permissions in RequestDesk
3. Review `var/log/system.log` for sync errors

## Development

### Running Tests

The unit suite runs standalone. It mocks its dependencies, so it needs no
Magento installation, no database and no store, and finishes in well under a
second.

```bash
composer install
vendor/bin/phpunit
```

**Remove `vendor/` again before running `setup:di:compile`.** These dependencies
install into the module's own directory, so when the module sits inside a
Magento installation the compiler scans them as application code, finds a second
copy of packages Magento already ships, and dies with `Cannot redeclare trait
phpseclib3\Crypt\EC\Formats\Keys\Common`. Developer mode never compiles, so
this only bites when you do:

```bash
rm -rf vendor
bin/magento setup:di:compile
```

Magento packages are not published on packagist.org, so `composer.json` declares
the public Mage-OS mirror as a repository. No credentials are needed. Composer
ignores a `repositories` block in an installed dependency, so this affects local
development and CI only, never a store that requires this module.

CI runs the same suite on every push, pull request and tag, against PHP 8.1 (the
floor `composer.json` declares) and PHP 8.3.

**What is covered.** `Model/PostContent` — the class that turns stored content
into something safe to render or excerpt. Each test is written against a defect
seen in real data rather than against the implementation:

- `<script>` and `<style>` elements dropped whole, because `strip_tags()` removes
  the tag and keeps the text, which is how editor CSS such as `#html-body {...}`
  used to appear inside excerpts
- Page Builder markup stored HTML-escaped decoded before stripping
- excerpts truncated on a word boundary, and the deliberate refusal to use one
  before 60% of the limit so a single long token cannot collapse the excerpt
- excerpt length counted in characters, not bytes
- a Page Builder wrapper unwrapped, while unrelated sibling divs and nested
  blocks are left alone
- clean content returned byte-identical, so a caller can use a strict comparison
  to decide whether a row needs writing at all
- `normalizeForStorage()` idempotent, so the repair applied on read and the
  repair written to the database cannot disagree
- `render()` falling back to unfiltered content when the filter throws, so a
  malformed directive cannot blank a whole post body

The suite is verified by breaking the code, not only by watching it pass:
removing the script/style strip reproduces the original defect and fails its
test, and removing the `render()` fallback fails its own.

Tests are not shipped. `.gitattributes` marks `Test/`, `phpunit.xml.dist` and
the CI workflow `export-ignore`, so they stay in the repository and out of
`vendor/`.

Other classes are not covered yet. The `url_key` generation and API key
decryption paths live in private methods behind config and database access;
testing those means changing production code, which is a deliberate decision
rather than an oversight.

### Code Quality

This extension follows Magento coding standards:
- PSR-4 autoloading
- Proper dependency injection (no ObjectManager anti-pattern)
- Service contracts via interfaces
- Declarative schema

## Support

- **Magento Integration Guide**: [requestdesk.ai/magento](https://requestdesk.ai/magento)
- **Documentation**: [docs.requestdesk.ai](https://docs.requestdesk.ai)
- **Issues**: [GitHub Issues](https://github.com/brentwpeterson/requestdesk-magento/issues)
- **Email**: support@requestdesk.ai

## License

This extension is licensed under the [Open Software License 3.0 (OSL-3.0)](https://opensource.org/licenses/OSL-3.0).

Copyright (c) 2025 Content Basis LLC

## Roadmap

### WYSIWYG Editor (Planned)

Full rich-text editing for blog posts directly in the Magento admin.

- TinyMCE integration (Magento native)
- Image upload and media gallery integration
- Product widget insertion
- HTML source editing
- Responsive preview

---

### Brand Analyzer & Content Scoring (Planned)

A comprehensive brand consistency and content quality analyzer for your entire Magento store.

**Content Types Analyzed:**
- CMS Pages
- Category Descriptions
- Product Descriptions
- Blog Posts

**Scoring Dimensions:**
| Dimension | Description |
|-----------|-------------|
| Brand Voice | Consistency with defined brand tone and messaging |
| SEO Quality | Meta tags, keyword usage, heading structure |
| Readability | Reading level, sentence complexity, clarity |
| Completeness | Required fields, content length, media presence |
| Uniqueness | Duplicate content detection across pages |

**Features:**
- Dashboard with store-wide content health score
- Individual page scores with improvement suggestions
- Brand voice guidelines integration from RequestDesk personas
- Bulk analysis via cron for large catalogs
- Score history tracking over time
- Export reports for stakeholders

**Integration with RequestDesk:**
- Pull brand guidelines from your RequestDesk persona
- AI-powered suggestions for content improvements
- One-click content regeneration for low-scoring pages

---

### AEO Score - AI Search Optimization (Planned)

Optimize your content to be found and cited by AI assistants (ChatGPT, Claude, Perplexity, Google AI Overviews).

**What is AEO?**
Answer Engine Optimization (AEO) is the practice of structuring content so AI systems can easily understand, extract, and cite it in responses. As more users search via AI, traditional SEO alone isn't enough.

**AEO Scoring Dimensions:**
| Dimension | Description |
|-----------|-------------|
| Question Targeting | Content answers specific questions users ask AI |
| Structured Data | Schema.org markup for AI comprehension |
| Concise Answers | Clear, quotable statements AI can extract |
| Authority Signals | E-E-A-T factors that make AI trust your content |
| Source Attribution | Proper citations and references |
| Content Freshness | Recent updates that AI systems prefer |

**Features:**
- Per-page AEO score with specific recommendations
- Question extraction: "What questions does this page answer?"
- AI citation checker: See if your content appears in AI responses
- Structured data generator for products and articles
- Competitor AEO comparison
- "AI-ready" content templates

**Why This Matters:**
- 40% of Gen Z prefers TikTok/AI over Google for search
- AI Overviews now appear in 30%+ of Google searches
- Content not optimized for AI will become invisible

## Changelog

### 1.10.3 (2026-09-17)

- **Fix: Enable Blog did nothing.** Set to No, every blog page kept answering.
  Nothing in the module read `requestdesk_blog/general/enabled` except the
  sitemap provider, so the switch in the admin had no visible effect for four
  releases. The storefront controllers, the comment endpoint, the router and the
  posts widget now go through `Model\StorefrontGate`
- **Fix: Blog URL Prefix did nothing.** The field had been in the admin since
  the first release with no code reading it; the address was hard-coded to
  `/blog` in every link, pager, redirect and sitemap entry the module writes. Every blog URL is now built under the configured
  prefix (`Block\BlogUrl`), `Controller\Router` serves the blog there, and the
  `/blog` copies close. A malformed prefix is refused on save
  (`Model\Config\Backend\UrlPrefix`) rather than breaking the storefront
- **Note for upgrades:** both settings start doing what they say, so check their
  stored values before upgrading. A store that left Blog URL Prefix filled in
  with something other than `blog`, or Enable Blog set to No, changes behavior
  on this upgrade
- Unit suite 118 -> 144

### 1.10.2 (2026-09-14)

- **Fix: every migrated featured image 404'd on the live store.** The migration
  copied Amasty's `post_thumbnail` verbatim, a path relative to
  `pub/media/amasty/blog`, and `ImageUrl` resolved it against the media root.
  Checked against production: the same values load under `/media/amasty/blog/`
  and 404 under `/media/`. Images now live in `pub/media/blog`; new migrations
  write `blog/<file>` and rewrite body links, and re-running `migrate-amasty`
  repairs posts migrated earlier. Server copy steps are under Upgrading
- **New: blog URLs in the XML sitemap.** `Model\Sitemap\BlogItemProvider`
  registers with Magento's sitemap composite, replacing what
  `amasty/blog-sitemap` provided. Configurable under Catalog > XML Sitemap.
  Adds `magento/module-sitemap` to `require` and `Magento_Sitemap` to the module
  sequence

- **Fix: every blog page rendered twice on Hyva.** Hyva loads a `hyva_`-prefixed
  handle *in addition to* the base one, not instead of it, and all five
  `hyva_blog_*.xml` handles declared a second block under a different name
  (`blog.list` beside `requestdesk.blog.list`, and so on). The result was two
  grids and two `h1`s on every page, Luma markup stacked above Tailwind markup.
  They now `referenceBlock` the block the base handle already defines and swap
  only its template: one block, one render, and every argument and head addition
  on the base handle applies without a second copy
- **Correction to the 1.10.0 note about `og:` tags and JSON-LD on Hyva.** That
  entry said the Hyva post page emitted neither. It was wrong — read off the
  layout file rather than a rendered page, because the module had no runnable
  Hyva install to check against. The base `blog_post_view.xml` handle loads on
  Hyva too, so both were already being emitted; the duplicate block that 1.10.0
  added to the Hyva handle produced *two* JSON-LD blocks. Both are back to one
- **This was the first run against a working Hyva install.** The theme could not
  be rendered on the development store — the Hyva modules were disabled and the
  `Hyva/reset` and `Hyva/default` theme rows carried stale flags from an imported
  database — so the Hyva half of the module had been shipping unverified. All
  twelve blog routes are now confirmed on both themes

### 1.10.0 (2026-09-10)

- **Fix: the 1.9.6 listing footer was never wired.** `PostList::getPostDate()`
  and `getPostCategories()` were added and no template called either one, so both
  were dead code and every card still printed `$post->getCreatedAt()` — the raw
  SQL datetime, `2026-09-09 18:59:18` — with no category anywhere. Both listing
  templates now call them
- **Fix: the same raw datetime on the post detail page,** on both themes.
  `PostView::getPostDate()` added to match `PostList`, so a card and the post it
  opens read the same date
- **Fix: Hyva showed a different author than Luma for the same post.** The Hyva
  card read `$post->getAuthor()`, the flat varchar, where Luma resolves the author
  entity through `getAuthorName()`. The Hyva post byline had the same split and
  now uses `getAuthorData()`, so the name links to the author archive
- **Fix: migrated posts all claimed to be published on import day.** The Amasty
  migration never read the source's `published_at`, so `created_at` took its
  `CURRENT_TIMESTAMP` default — on the Evrig data, 271 of 274 posts landed on one
  of two days in 2026 and collapsed a four-year archive. The migration now carries
  the date across on create, and corrects it on posts already migrated. See
  *Backfilling publish dates* below
- **Fix: Hyva post pages emitted no `og:` tags and no `BlogPosting` JSON-LD.**
  `hyva_blog_post_view.xml` carried neither the Open Graph block nor the schema
  view model that `blog_post_view.xml` has had since the schema work landed, so
  the theme a store actually runs was the half answer engines could not read.
  The Hyva post page also gained the category links Luma shows
- **Fix: full page cache was off for the entire blog on Hyva.** All five
  `hyva_blog_*` layouts set `cacheable="false"`; none of the Luma handles ever
  did. Removed, which puts the heaviest queries in the module back behind FPC
- **New: pretty URLs for the three archives.** `/blog/category/:urlKey`,
  `/blog/tag/:urlKey` and `/blog/author/:urlKey` now resolve — the README has
  documented them for some time, but nothing served them and every link still
  read `/blog/category/view/id/77`. `Controller\Router` resolves all three, and
  the new `Block\ArchiveUrl` gives the four call sites that built these URLs by
  hand (`PostCategoryResolver`, `TagResolver` twice, `AuthorResolver`) one rule,
  the same way `Block\PostUrl` did for posts. The id form stays routed
- **Category keys are resolved among blog categories only.** Blog categories are
  native catalog categories, and Magento enforces `url_key` uniqueness only among
  siblings — the Evrig catalog has two categories keyed `ecommerce` and two keyed
  `hyva`. Restricting the lookup to categories with at least one post attached
  settles it, since that is the only set whose archive has anything to show. A
  genuine tie resolves to the lowest id rather than row order
- **Cleanup: `view/frontend/templates/tag/view.phtml` removed.** Orphaned by the
  same refactor that deleted `category/view.phtml` in 1.9.6 — `blog_tag_view.xml`
  points at `list.phtml`, so nothing had rendered it
- **Tests: 81 → 94.** `ArchiveUrlTest` pins the pretty form, the id fallback and
  the per-type segment; `MigrateAmastyPublishDateTest` pins the date rule in both
  directions, since getting it wrong the other way would silently rewrite
  hand-edited dates across a whole blog

### 1.9.6 (2026-09-10)

- **New: category + author + date footer on the listing cards.** Moved off
  the top of the card to a footer row under a divider: the locale-formatted
  date (`August 12, 2026`) and the post's native categories as links
  (`Category: General, Adobe Commerce`) on the left, `by Author` on the
  right. Each part hides itself when the post has none
- **New: `PostCategoryResolver::getCategoriesForPosts()`** resolves
  categories for a whole page in one pass — one link-table query plus one
  load per unique category — and `PostList::getPostCategories()` memoizes
  it, so the card loop adds no per-post queries. The existing single-post
  `getCategoriesForPost()` now delegates to it
- **Cleanup: dead constructor removed from `AuthorView`.** It promoted
  nothing and only reordered parent arguments — Magento DI injects by type,
  not position, so the reorder had no effect. `PostList` promotes
  `PostCategoryResolver`; `CategoryView`/`TagView` forward it

### 1.9.5 (2026-09-01)

- **Fix: updating a post through the External Blog API silently dropped
  `categoryIds` and `publishedAt`.** 1.9.0 added both parameters to
  `updatePost()`, but the `$data` array handed to `updateExistingPost()`
  never carried either key, so `updateExistingPost()` read `null` for both
  and the request returned success while categories and the archive date
  went untouched. Now passed through; `published_at` reuses the same
  `applyPublishedAt()` helper `createPost()` already had
- **Fix: `--parent-category` on `requestdesk:blog:migrate-amasty` was never
  validated**, despite a comment claiming it fails up front. A non-numeric
  value silently cast to `0` and fell through to auto-create-or-find instead
  of failing; a nonexistent numeric id passed the guard and only surfaced
  deep inside `mapCategory()`, where every failure is caught and logged
  rather than surfaced — the run finished `SUCCESS` reporting `0` links with
  nothing on stdout to say the categories were lost. The option is now
  checked against `AmastyCategoryMapper::categoryExists()` and the command
  refuses to run rather than silently discarding categories
- **Fix: an Amasty category with no store-0 row broke re-runnability.**
  `fetchSourceCategory()` only read the default (store 0) localization row;
  an install that had only ever written per-store rows got a `null`
  name/url_key back, `mapCategory()` fell back to an empty url_key, and
  `findChildByUrlKey()` refuses to match an empty one on purpose — so a
  second run could never find what the first run created and made a fresh
  duplicate category instead. The fetch now falls back to any available
  per-store row when the default one is missing, keeping the real url_key
  and with it the whole point of matching on it

### 1.9.4 (2026-09-01)

- **Fix: an out-of-range listing page stranded the visitor.** `hasPagination()`
  is driven by the total post count, not by whether the current page has any
  items, so `?p=` past the last page returned zero posts while pagination was
  still true. Both list templates nested the pager inside the "posts is not
  empty" branch, so that combination hid the pager along with the grid,
  leaving a dead end with no link back to page 1. The pager now renders
  whenever `hasPagination()` is true, independent of whether this page's own
  item list is empty
- **Fix: the External Blog API still emitted a dead URL for a post with no
  `url_key`.** 1.9.3's fix moved every URL-emitting call site onto the shared
  `Block\PostUrl` helper, but `Model\ExternalBlog::formatPostResponse()` was
  missed — it kept hand-concatenating `'blog/' . $post->getUrlKey()`, which for
  an empty `url_key` produced a bare `blog/` link instead of the id-form
  fallback every other call site now falls back to

### 1.9.3 (2026-09-01)

- **New: Short Description.** A second WYSIWYG on the post form, above Content,
  feeding the listing cards. Nullable on purpose: null means "never written",
  which is what lets the migration backfill tell a genuine blank from a value it
  has already filled. Cards fall back to the automatic excerpt when it is empty.
  The card emits the authored value as markup rather than escaped text: hiding an
  element in the editor is a styling instruction, not a deletion, so `strip_tags()`
  dropped the marking and kept the words — content the author had hidden came back
  on the card. Rendering the real markup lets the browser apply the author's intent
  without this code needing to know how a given editor spells "hidden". The card is
  a `div`, not a `p`, since the field can hold block elements
- **The Amasty migration backfills Short Description onto posts already
  migrated.** The old unconditional skip-if-exists meant no re-run could ever add
  a newly introduced field. An existing post is now examined, and an empty
  column filled from the source with a direct `UPDATE` — not a repository save,
  which would rewrite every column and reset the RequestDesk sync fields
- **Fix: saving a post fataled with a `TypeError`.** `setCommentsEnabled(bool)`
  is strictly typed and the admin controller passed `(int)`, under
  `declare(strict_types=1)` where no coercion happens. Both ternary branches were
  wrong — the `: 0` fallback would have thrown the same way. The neighbouring
  `setIsActive()` has no parameter type at all, which is why it never showed the
  same symptom
- **Fix: Allow Comment was ignored on the frontend.** The guard existed in both
  module post templates, but the active theme's override of
  `hyva/post/view.phtml` had no guard, and a theme template wins over a module
  one. Comments rendered on every post regardless of the toggle
- **Fix: listing links used the id form.** `Controller\Router` had resolved
  `/blog/<url-key>` for some time, but nothing generated those URLs — five blocks
  each carried their own copy of `getPostUrl()` returning `blog/post/view`. They
  now share `Block\PostUrl`, which falls back to the id form for a post with no
  `url_key`. `BlogPosting` JSON-LD and the post-comment redirect were emitting the
  id form too, and `Model\ExternalBlog` was emitting `blog/post/<slug>` — three
  segments, which the router rejects, so **every URL in that API payload 404'd**
- **Fix: pretty URLs reached the wrong module.** `Controller\Router` and
  Amasty_Blog's router both registered at `sortOrder 60`, and Amasty won the tie,
  so `/blog/<url-key>` rendered the legacy Amasty post page. Moved to `50`
- **Fix: the listing never paginated.** The theme's list template already drew a
  pager, but `PostList` had none of the methods it called. That failed silently
  rather than fatally: `DataObject::__call()` answers an undefined `hasPagination()`
  by looking up `$_data['pagination']`, so it returned false forever and the pager
  simply never drew. `?p=` and `?limit=` now work, page size comes from
  `Model\Config` instead of a second config read in the block, and both module
  list templates carry a pager of their own
- Documentation: `short_description` and `comments_enabled` added to the schema
  table; the `author_id` row corrected — it is a FK onto
  `requestdesk_blog_author.author_id`, not `admin_user.user_id`, which is the
  confusion 1.6.4's `repair-authors` exists to undo

### 1.6.4 (2026-08-10)
- **Fix: three of the four admin grids were never registered.** `etc/di.xml`
  carried four separate `<type>` nodes for
  `UiComponent\DataProvider\CollectionFactory`. The mapper that reads that file
  assigns by type name, so the nodes replaced one another instead of merging and
  only the last survived. The post, comment and tag grids failed with
  "Not registered handle". Now one node with all four collections
- **Fix: the post form failed XML validation.**
  `requestdesk_blog_post_form.xml` declared `<wysiwyg>true</wysiwyg>` inside
  `<settings>`, which is not in `ui_definition.xsd`
  (*"Element 'wysiwyg': This element is not expected"*). `formElement="wysiwyg"`
  already binds the field
- **Fix: the Amasty migration created no authors.** It resolved the byline to an
  `admin_user.user_id` and wrote that into `requestdesk_blog_post.author_id`, a
  foreign key onto `requestdesk_blog_author.author_id` — so it either broke the
  constraint or pointed at an unrelated author, and the Author grid stayed empty.
  `AuthorResolver::getOrCreateByName()` now creates or reuses a real author,
  carrying the bio and avatar over and linking the admin account through
  `admin_user_id`, the column that actually means that
- **Fix: `setup:upgrade` aborted with a duplicate foreign key.**
  `db_schema_whitelist.json` still listed only the legacy
  `..._AUTHOR_ID_ADMIN_USER_USER_ID` from when `author_id` pointed at
  `admin_user`, so declarative schema did not know the current FK already existed
  and re-emitted it inside the same `ALTER`
- **Comments on Hyvä.** The Hyvä post template had no comment markup at all —
  the list and form existed only in the Luma template. Ported against the same
  block API and POST contract, so the controller is unchanged
- **New: `bin/magento requestdesk:blog:repair-authors`** rebuilds post-to-author
  links for posts migrated by a pre-1.6.4 build. The 1.4.2 backfill patch runs
  only once, so it cannot reach anything migrated after it. `setup:upgrade` does
  not catch this either: declarative schema disables `foreign_key_checks`, so it
  adds the author foreign key over the top of violating rows and the breakage
  stays silent
- Documented the real version support matrix (Magento 2.4.7 – 2.4.9, PHP
  8.1 – 8.5), marking which rows are runtime-tested and which are static only

### 1.6.3 (2026-08-04)
- **Unit test suite** covering `Model/PostContent`, running standalone with no
  Magento install or database, plus GitHub Actions CI on PHP 8.1 and 8.3
- **Mage-OS mirror declared** as a composer repository, so the module can be
  installed and tested standalone. Magento packages are not on packagist.org,
  and without this the package could not resolve its own requirements outside a
  store
- **Tests and internal notes no longer ship.** `.gitattributes` keeps `Test/`,
  `phpunit.xml.dist`, the CI workflow and planning docs out of `vendor/`
- Documentation: the previous "Running Tests" section described a suite that did
  not exist, and the changelog stopped at 1.2.0 while five releases had shipped

### 1.6.2 (2026-07-30)
- **Fix:** product sync authenticated with Magento ciphertext. The `api_key`
  field is `obscure` in `system.xml`, so `core_config_data` stores an encrypted
  value; `ProductExportService` posted it raw and RequestDesk answered 401 while
  the admin looked correctly configured

### 1.6.1 (2026-07-30)
- **Fix:** a post saved with an empty `url_key` now generates one from its
  title, with a `-2`, `-3` suffix until it is unique. Imported posts always
  arrived with a key, so nothing had ever generated one

### 1.6.0 (2026-07-30)
- Post import points at RequestDesk's current API

### 1.5.2 (2026-07-30)
- **Fix:** admin AJAX endpoints fail with a usable message instead of silently

### 1.5.1 (2026-07-30)
- **Fix:** the media gallery plugin no longer fires on avatars; stored post
  content repaired

### 1.5.0 (2026-07-30)
- Pretty post URLs; the deprecated author profile table is retired

### 1.4.3 (2026-07-30)
- **Fix:** stale grid bookmarks cleared, `updated_at` stamped, upgrade path
  documented

### 1.4.2 (2026-07-30)
- **Fix:** three author bugs found by an admin click-through

### 1.4.1 (2026-07-30)
- **Fix:** fatal in the post form from a nonexistent Cms Wysiwyg element class

### 1.4.0 (2026-07-29)
- Fixes for all nine issues on the blog module issue sheet

### 1.3.0 (2026-07-24)
- **Standalone free tier:** the blog runs without `RequestDesk_Qa`, which
  becomes an optional companion rather than a hard dependency
- Amasty Blog migration console command, reading the Amasty tables directly

### 1.2.0 (2026-07-17)
- **Own frontend route** (`/blog`) with Luma templates: list, post, author, tag,
  and category-filtered views
- **Reuse-first taxonomy**: categories now reuse native Magento categories;
  authors reuse native admin users with a public Author Profile extension
- **Tags**: blog-owned entity with admin grid, archive pages, and schema keywords
- **Comments**: guest form (form-key + honeypot), moderation grid, schema
- **AEO by default**: `BlogPosting` on every post, `FAQPage` + visible FAQ for
  posts with Q&A, powered by the shared `RequestDesk_Qa` library (new required
  dependency)
- **Widget**: recent / by-category / related-to-current-product cross-link
- **Open Graph** head block (`og:title/description/image`)
- **Import**: matches incoming author to a native admin user (else free-text);
  auto-creates and links tags
- **Fixes**: Active toggle now saves to and displays from the `status` column
  correctly in both directions; deleting a post cleans up its Q&A links
- **Optional companion**: `requestdesk/magento-aeo` declared via composer
  `suggest` (recommended, not required)

### 1.1.0 (2025-12-29)
- **Package renamed** from `requestdesk/module-blog` to `requestdesk/magento-blog`
- Establishes multi-platform naming convention (`magento-*`, `wordpress-*`, etc.)

### 1.0.0 (2025-12-29)
- Initial release
- Full blog system with posts and categories
- Product-to-post linking
- RequestDesk API integration
- Product export to RequestDesk knowledge base
- Post import from RequestDesk
- Automated hourly imports via cron
- REST API for headless implementations
- Hyvä theme support
- Multi-store support
