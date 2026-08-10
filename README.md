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
- Featured images, plus a proper Open Graph head block (`og:title/description/image`)
- Draft/Published status workflow via an Active toggle
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
- **[`requestdesk/magento-qa`](https://github.com/brentwpeterson/requestdesk-magento-qa)** — required. The shared Q&A library that powers on-post FAQ + FAQPage schema. Composer pulls it automatically.
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

Composer resolves the required `requestdesk/magento-qa` dependency for you.

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

## Configuration

Navigate to **Stores > Configuration > RequestDesk > Blog**

### General Settings

| Setting | Description |
|---------|-------------|
| Enable Blog | Enable/disable blog functionality on frontend |
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

### SEO Settings

| Setting | Description |
|---------|-------------|
| Blog URL Prefix | URL prefix for blog pages (default: `blog`) |
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
| `url_key` | varchar(255) | SEO-friendly URL slug |
| `meta_title` | varchar(255) | SEO meta title |
| `meta_description` | text | SEO meta description |
| `featured_image` | varchar(255) | Featured image path |
| `status` | smallint | 0=Draft, 1=Published |
| `author` | varchar(255) | Author name (free-text fallback byline) |
| `author_id` | int | Native `admin_user.user_id` (nullable, `SET NULL`) |
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
| `/blog/post/view/id/:postId` | Single post view |
| `/blog/category/:urlKey` | Category listing |

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
