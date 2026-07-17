# RequestDesk Blog Extension for Magento 2

[![Magento 2.4.7+](https://img.shields.io/badge/Magento-2.4.7+-orange.svg)](https://magento.com)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net)
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

- Magento Open Source or Adobe Commerce 2.4.7+
- PHP 8.1 or later
- **[`requestdesk/magento-qa`](https://github.com/brentwpeterson/requestdesk-magento-qa)** — required. The shared Q&A library that powers on-post FAQ + FAQPage schema. Composer pulls it automatically.
- RequestDesk account with API key (only needed for the RequestDesk sync/import features)

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

```bash
# Unit tests
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist \
  app/code/RequestDesk/Blog/Test/Unit

# Integration tests
vendor/bin/phpunit -c dev/tests/integration/phpunit.xml.dist \
  app/code/RequestDesk/Blog/Test/Integration
```

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
