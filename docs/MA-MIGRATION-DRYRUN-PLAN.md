# Magento Association: Amasty Blog to RequestDesk Blog, dry-run plan

Written 2026-10-09 by Claude-Picard. Goal set by Brent: rehearse the Amasty to RequestDesk migration in the Evrig environment, prove it worked with an automated test plus a manual audit of a few posts, and scan every URL before and after so no SEO is lost.

## Facts established (checked 2026-10-09)
| Fact | Value | Source |
|---|---|---|
| Production state | Still on Amasty. The dump has zero requestdesk_blog tables. | dump |
| Amasty posts | 225 total: 218 published (status 2), 2 at status 1, 5 at status 0 | scratch db ma_20261006 |
| Other Amasty content | 12 categories, 113 tags, 105 authors, 0 comments | scratch db |
| Live blog URLs | https://www.magentoassociation.org/commerce-co-op.html (index), /commerce-co-op/<url_key>.html (posts), /commerce-co-op/author/<Name>.html (authors) | live GET |
| Suffix handling | Amasty setting url_postfix is .html. The live site also serves posts without .html and redirects to the .html form. | live GET, dump |
| Live sitemap | 15 URLs, none of them blog posts | live GET |
| RD module URLs | Prefix is configurable (default /blog). A search of Router.php and config.xml finds no .html handling. Whether RD returns 404 for a .html URL is NOT verified yet; step 9 tests it. | code search |
| Odd keys | Some url_keys are long and malformed, for example commerce-co-opfull-article2025introducing-... and mixed-case author keys | live GET |
| Websites in dump | 4 (hub, magento-opensource, magentoassociation, meet-magento). The blog sits on the magentoassociation website (store 3). | dump |
| Source media | amasty/blog (301 files), .thumbsamasty/blog (149) in media-2026-10-06_12-57-27.tgz | archive listing |

## Environment decision (assumption, change if wrong)
Use the Evrig Docker project. Do not touch Evrig's `magento` database. Point the Evrig PHP container at the scratch database `ma_20261006` by editing the db name in env.php, after copying env.php aside. Override base URLs in the env.php `system` block so the site answers on https://evrig.test:8443 and not on magentoassociation.org. Restoring the saved env.php returns Evrig to normal. Evrig's own data snapshot is also saved.

## Pass criteria (all must hold)
1. Every published Amasty post exists in RD with the same title, url_key, publish date, author, tags, category, and body text.
2. Every URL that returned 200 before returns 200 after, at the same path or through one 301 to the matching path. Zero new 404s.
3. Title tag, meta description, canonical, H1, og:image, and JSON-LD types match, or each difference is listed and accepted by Brent.
4. A second run of every migrate command changes nothing.
5. Blog images load. No image path points at amasty/blog after migration.

## Phases
### Phase 0. Setup (done except the env.php step)
Evrig snapshot saved, dump imported to ma_20261006, media unpacked in scratch.

### Phase 1. Before inventory (read-only)
- Source of truth for expected posts: the 225 url_keys in ma_20261006.
- Crawl the live site section by section, one request at a time with a delay, user agent CB-migration-audit: blog index and every pagination page, 225 post URLs both with and without .html, every author, tag, and category page found in links, plus the 15 sitemap URLs.
- Record per URL: status, redirect chain, final URL, canonical, title, meta description, H1, og:title, og:image, JSON-LD types, word count, internal link count, image count.
- Save as before.csv. Keep it outside git.

### Phase 2. Prepare Evrig on the MA data
- Copy env.php aside, set the db name to ma_20261006, add base URL and blog prefix overrides (/commerce-co-op), run `bin/magento app:config:import`.
- Run `bin/magento setup:upgrade` so the requestdesk_blog tables are created in the scratch database. Record what it prints.
- Confirm the storefront answers and the blog module is enabled.

### Phase 3. Migrate (each command first with --dry-run, numbers recorded, then for real)
1. requestdesk:blog:migrate-amasty (also creates the categories and links posts)
2. requestdesk:blog:migrate-amasty-categories (run AFTER posts exist; it re-links by url_key)
3. copy source media into the Evrig media volume, then requestdesk:blog:migrate-media
4. requestdesk:blog:repair-authors, repair-content, repair-schema
5. The 7 non-published posts (status 0 and 1) are skipped by the command. They already return 404 on the live site, so skipping loses nothing.

### Phase 4. Automated verification (one script, re-runnable)
Reads ma_20261006 Amasty tables and the RD tables and fails loudly on any mismatch:
- Counts: posts, categories, tags, authors, links between posts and each of them.
- Per post: title, url_key, publish date, status, author, tag set, category set, body text hash after allowed normalization, featured image file present on disk.
- Image check: no amasty/blog path left in any post body or field.
- Idempotency: run all migrate commands again and assert zero changes.
- Also run the module's existing unit suite (Test/Unit) and report the result.
Output: a pass/fail table plus a list of every failing post by url_key.

### Phase 5. After inventory and URL comparison
- Crawl evrig.test:8443 over the same URL list as before.csv, mapped to the local host.
- Compare by path: status, final URL, canonical, title, meta description, H1, og:image, JSON-LD, word count within a tolerance, image count.
- Output after.csv and diff.csv with one row per URL and a verdict: same, redirected correctly, changed (list the fields), lost (404 or 5xx).
- Anything lost or changed needs a fix, a redirect rule, or Brent's explicit acceptance.

### Phase 6. Manual audit and smoke test
Brent or Claude opens each in a browser, desktop and phone width, and compares live and local side by side:
- the newest post: metal-mafia-30-percent-more-daily-orders
- a podcast episode: episode-10-arron-moss (embed, long body)
- a post with a malformed key: one of the commerce-co-opfull-article2025 posts
- a post by an author with a mixed-case key (Noah-Oken-Berg)
Checklist per post: headline, byline and date, hero image, body images, embeds, links inside the body, tags, category, related posts, breadcrumbs, view-source for title, description, canonical, og tags, JSON-LD. Also check the blog index, page 2, one author page, one tag page, and search.

### Phase 7. Production runbook
Write the exact production sequence from what the rehearsal showed: fresh backup, maintenance mode, module deploy, config for prefix and URL suffix, commands in order, redirect rules, cache and reindex, the smoke list, rollback. Record timings.

## Known SEO risks to resolve before cutover
- .html suffix on every live blog URL. If RD does not serve or redirect the .html form, all 225 posts lose their indexed URLs.
- Live blog posts are absent from the sitemap. RD adds posts to the XML sitemap (module 1.10.2), which is an improvement; confirm after migration.
- Malformed and mixed-case url_keys must survive unchanged or get 301s.
- Author, tag, and category archive URLs differ in shape between Amasty and RD. Needs an explicit map.
- Rich data: compare JSON-LD types before and after so structured data is not lost.

## Rollback
Restore the saved env.php to return Evrig to its own database. Drop ma_20261006 when finished. Production is never touched during the dry run.

## Open decisions for Brent
1. Keep /commerce-co-op/<key>.html as the final URL form, or move to extensionless with 301s (see Fix list item 1).
2. Whether the production cutover keeps /commerce-co-op as the prefix.
3. Accept or fix the content differences listed under Results.

## RESULTS (2026-10-09, Evrig pointed at ma_20261006, scratch database rebuilt from the dump for run 2)

Evidence: docs/dryrun-2026-10-09/ (before.csv from the live site, after-raw.csv from Evrig, diff.csv, crawler and compare scripts).
Automated tests: Test/Unit (218 tests, 355 assertions) and Test/Migration/verify_amasty_migration.py (20 checks).

### Run 1, before any module change: FAILED
| Result | Value |
|---|---|
| Live URLs answering 200 before | 635 of 690 |
| Lost after migration | 416: every .html address (218 posts, 99 authors, 86 tags, 11 categories, 2 index pages) returned 404 |
| Canonical link | missing on all 218 posts |
| BreadcrumbList structured data | missing on all 218 posts |

### Fixes made in RequestDesk_Blog 1.14.0
1. Blog URL Suffix setting. `.html` makes the suffixed address answer, and the address without the suffix redirects to it with a 301. Applies to the listing, posts, and author, tag and category pages. Links, canonical, structured data and the XML sitemap use the suffixed form.
2. Canonical link tag on posts, listing, category, tag and author pages.
3. BreadcrumbList structured data on posts (Home, Blog, the post).
4. Storefront gate ignored the suffix and refused the suffixed index; fixed.
5. Migration defect found by the URL scan, not by the data test: author and tag url_keys were rebuilt from names, which dropped accents and dots (andrea-sacca became andrea-sacc, magento-2.4 became magento-2-4) and lowercased IsaacMorey. The importer now keeps the Amasty key. 12 authors and 2 tags had been lost.
6. Author and tag pages: title and heading use Amasty's wording (Articles by Name, Posts tagged 'Name'); author meta description is taken from the bio.
7. The data test now also checks author and tag url_keys, and was shown to fail when a key is broken.

### Run 2, after the fixes: PASSED
| Check | Result |
|---|---|
| Unit suite | 218 tests, 0 failures (169 before) |
| Data test | 20 passed, 0 failed, including a second migration run that changed nothing |
| Posts migrated | 218 of 218, 0 failed |
| Live URLs answering 200 before | 635 |
| Lost after migration | 0 |
| Extensionless post, author, tag and index addresses | all 301 to the .html address, same final address as live |
| Pages identical on title, canonical, meta description, H1, og:image, robots, JSON-LD types | 506 |
| Post pages: canonical and JSON-LD types identical | all 436 post address forms |

### Differences that remain (129 pages), none lost
| Difference | Pages | What it is |
|---|---|---|
| og:image path | 58 posts | live points at an Amasty resized cache file, RD at the same image under media/blog. All 29 distinct RD image files return 200 |
| Author meta description | 69 author pages | live text came from a separate Amasty meta field with no RD column; RD now uses the bio. Wording differs, none is empty |
| Page title | 3 | 2 are a crawler artifact on the live side (an icon title read as part of the title); 1 is a tag whose name differs only in case (eCommerce, ecommerce) |
| H1 | 2 | the site home page (theme) and the same tag |
| Meta title | 14 posts | Amasty had none; RD uses the post title (data test warning) |
| Empty body | 2 posts | empty in Amasty too; check the live page |

### Still to do
1. Manual audit and smoke test in a browser on https://evrig.test:8443 (post list in the Phase 6 section above). Automated checks now pass, so this is the last gate.
2. Production runbook (Phase 7) with the new setting: set Blog URL Suffix to .html and Blog URL Prefix to commerce-co-op before opening the site, run the migration commands in order, resubmit the sitemap.
3. Decide whether to keep the case-merged tag (eCommerce, ecommerce) as two tags with redirects.

### State left behind
Evrig's PHP container still points at ma_20261006 so the manual audit can run. To restore Evrig: copy scratchpad/evrig-snapshot/env.php.orig back to app/etc/env.php in the phpfpm container, run bin/magento app:config:import and cache:flush. Evrig's own data snapshot is evrig-magento-pre-dryrun.sql.gz in the same folder. Drop ma_20261006 when finished. The module changes are uncommitted in cb-magento-integration/requestdesk-blog.
