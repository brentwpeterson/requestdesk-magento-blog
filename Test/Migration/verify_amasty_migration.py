#!/usr/bin/env python3
"""Verify an Amasty -> RequestDesk_Blog migration, post by post.

Reads both sides from one MySQL database (the Amasty tables and the
requestdesk_blog_* tables sit side by side after a migration) through
`docker exec`, compares every published Amasty post to its migrated twin and
exits 1 on any mismatch. Read-only unless --rerun is given.

Usage (from cb-magento-integration/):
  python3 -I requestdesk-blog/Test/Migration/verify_amasty_migration.py \
      --container base-magento-db-1 --phpfpm base-magento-phpfpm-1 \
      --db ma_20261006 --env base-magento/env/db.env [--rerun] [--json out.json]
"""
import argparse, json, subprocess, sys, hashlib

def parse():
    a = argparse.ArgumentParser()
    a.add_argument('--container', required=True)
    a.add_argument('--phpfpm', required=True)
    a.add_argument('--db', required=True)
    a.add_argument('--env', required=True, help='db.env file holding MYSQL_ROOT_PASSWORD')
    a.add_argument('--rerun', action='store_true', help='re-run the migrate commands and prove nothing changes')
    a.add_argument('--json', help='write results to this file')
    return a.parse_args()

args = parse()
PW = next(l.split('=', 1)[1].strip() for l in open(args.env) if l.startswith('MYSQL_ROOT_PASSWORD'))

def sql(q):
    r = subprocess.run(['docker', 'exec', args.container, 'mysql', '-uroot', f'-p{PW}', '-B', '-N', args.db, '-e', q],
                       capture_output=True)
    if r.returncode:
        sys.exit('SQL failed: ' + r.stderr.decode('utf-8', 'replace').replace(PW, '***')[:300])
    # Raw bytes on purpose: text mode would split a row at a bare carriage return inside a field.
    return [l.split('\t') for l in r.stdout.decode('utf-8', 'replace').split('\n') if l != '']

def one(q):
    return sql(q)[0][0]

failures, checks, warnings = [], [], []
def warn(name, items):
    if items:
        warnings.append((name, len(items), items[:5]))

def check(name, ok, detail=''):
    checks.append((name, ok, detail))
    if not ok:
        failures.append((name, detail))

# ---- 1. counts -------------------------------------------------------------
am_pub = int(one("SELECT COUNT(*) FROM amasty_blog_posts WHERE status=2"))
rd_all = int(one("SELECT COUNT(*) FROM requestdesk_blog_post"))
check('post count: RD equals published Amasty', am_pub == rd_all, f'amasty published={am_pub} rd={rd_all}')

am_keys = {r[0] for r in sql("SELECT url_key FROM amasty_blog_posts WHERE status=2")}
rd_keys = {r[0] for r in sql("SELECT url_key FROM requestdesk_blog_post")}
check('url_key set identical', am_keys == rd_keys,
      f'missing in RD={sorted(am_keys-rd_keys)[:5]} extra in RD={sorted(rd_keys-am_keys)[:5]}')

# ---- 2. per post fields ----------------------------------------------------
AUTH = "COALESCE((SELECT name FROM amasty_blog_author_store s WHERE s.author_id=a.author_id AND s.store_id=0),'')"
am = {r[0]: r for r in sql(f"""SELECT a.url_key, a.post_id, a.title, a.published_at, MD5(a.full_content),
  MD5(REPLACE(a.full_content,'amasty/blog','blog')), COALESCE(a.meta_title,''), COALESCE(a.meta_description,''),
  {AUTH}, COALESCE(a.post_thumbnail,''), a.author_id
  FROM amasty_blog_posts a WHERE a.status=2""")}
rd = {r[0]: r for r in sql("""SELECT p.url_key, p.post_id, p.title, p.created_at, MD5(p.content), COALESCE(p.meta_title,''),
  COALESCE(p.meta_description,''), COALESCE(au.name, p.author, ''), COALESCE(p.featured_image,''), p.status, p.author_id
  FROM requestdesk_blog_post p LEFT JOIN requestdesk_blog_author au ON au.author_id=p.author_id""")}

am_tags, rd_tags, am_cats, rd_cats = {}, {}, {}, {}
for k, n in sql("""SELECT a.url_key, COALESCE(t.name,'') FROM amasty_blog_posts a JOIN amasty_blog_posts_tag pt ON pt.post_id=a.post_id
   JOIN amasty_blog_tags_store t ON t.tag_id=pt.tag_id AND t.store_id=0 WHERE a.status=2"""):
    am_tags.setdefault(k, set()).add(n)
for k, n in sql("""SELECT p.url_key, t.name FROM requestdesk_blog_post p JOIN requestdesk_blog_post_tag pt ON pt.post_id=p.post_id
   JOIN requestdesk_blog_tag t ON t.tag_id=pt.tag_id"""):
    rd_tags.setdefault(k, set()).add(n)
for k, n in sql("""SELECT a.url_key, c.amasty_category_id FROM amasty_blog_posts a JOIN amasty_blog_posts_category pc ON pc.post_id=a.post_id
   JOIN requestdesk_blog_category c ON c.amasty_category_id=pc.category_id WHERE a.status=2"""):
    am_cats.setdefault(k, set()).add(n)
for k, n in sql("""SELECT p.url_key, c.amasty_category_id FROM requestdesk_blog_post p JOIN requestdesk_blog_category_post pc ON pc.post_id=p.post_id
   JOIN requestdesk_blog_category c ON c.category_id=pc.category_id"""):
    rd_cats.setdefault(k, set()).add(n)

bad = {f: [] for f in ['title', 'publish_date', 'body', 'meta_title', 'meta_description', 'author', 'tags', 'categories', 'image', 'status']}
w_empty, w_metatitle, w_tagcase = [], [], []
EMPTY_MD5 = hashlib.md5(b'').hexdigest()
for k in sorted(am_keys & rd_keys):
    a, r = am[k], rd[k]
    if a[2] != r[2]: bad['title'].append(k)
    if a[3] != r[3]: bad['publish_date'].append(k)
    src_empty = a[4] == EMPTY_MD5
    rd_empty = r[4] in ('NULL', EMPTY_MD5)
    if src_empty and rd_empty: w_empty.append(k)          # both empty: same, but worth a human look
    elif r[4] not in (a[4], a[5]): bad['body'].append(k)
    a_mt = '' if a[6] == 'NULL' else a[6]
    r_mt = '' if r[5] == 'NULL' else r[5]
    if a_mt != r_mt:
        if a_mt == '' and r_mt == a[2]: w_metatitle.append(k)   # RD fills the post title when Amasty had none
        else: bad['meta_title'].append(k)
    if a[7] != r[6]: bad['meta_description'].append(k)
    if a[8].strip() != r[7].strip(): bad['author'].append(k)
    at, rt = am_tags.get(k, set()), rd_tags.get(k, set())
    if {x.lower() for x in at} != {x.lower() for x in rt}: bad['tags'].append(k)
    elif at != rt: w_tagcase.append(k)
    if am_cats.get(k, set()) != rd_cats.get(k, set()): bad['categories'].append(k)
    if a[9] and not r[8]: bad['image'].append(k)
    if r[9] != '1': bad['status'].append(k)
warn('post has no body in Amasty (empty on both sides, check the live page)', w_empty)
warn('meta_title was empty in Amasty, RD fills the post title', w_metatitle)
warn('tag name changed case (RD reuses an existing tag with different case)', w_tagcase)
for f, ks in bad.items():
    check(f'every post keeps its {f}', not ks, f'{len(ks)} differ, first: {ks[:5]}')

# ---- 2b. archive page addresses --------------------------------------------
# A post is only half of what search engines indexed: every author and tag with
# a published post has a page of its own, at /<prefix>/author/<key> and
# /<prefix>/tag/<key>. The key must survive exactly, or those pages 404.
am_author_keys = {r[0]: r[1] for r in sql("""SELECT TRIM(s.name), s.url_key FROM amasty_blog_author_store s
   WHERE s.store_id=0 AND s.author_id IN (SELECT author_id FROM amasty_blog_posts WHERE status=2)""")}
rd_author_keys = {r[0]: r[1] for r in sql("SELECT TRIM(name), url_key FROM requestdesk_blog_author")}
bad_authors = sorted(n for n, k in am_author_keys.items() if rd_author_keys.get(n) != k)
check('every author keeps its url_key', not bad_authors,
      f"{len(bad_authors)} differ, first: {[(n, am_author_keys[n], rd_author_keys.get(n)) for n in bad_authors[:3]]}")

am_tag_keys = {r[0]: r[1] for r in sql("""SELECT t.name, t.url_key FROM amasty_blog_tags_store t
   WHERE t.store_id=0 AND t.tag_id IN (SELECT pt.tag_id FROM amasty_blog_posts_tag pt
      JOIN amasty_blog_posts a ON a.post_id=pt.post_id WHERE a.status=2)""")}
rd_tag_keys = {r[0].lower(): r[1] for r in sql("SELECT name, url_key FROM requestdesk_blog_tag")}
rd_key_set = set(rd_tag_keys.values())
bad_tags = sorted(n for n, k in am_tag_keys.items() if k not in rd_key_set and rd_tag_keys.get(n.lower()) != k)
merged = sorted(n for n, k in am_tag_keys.items() if k not in rd_key_set and rd_tag_keys.get(n.lower()) not in (None, k))
check('every tag keeps its url_key unless two tags merged', not [n for n in bad_tags if n not in merged],
      f"{len(bad_tags)} differ, first: {[(n, am_tag_keys[n], rd_tag_keys.get(n.lower())) for n in bad_tags[:3]]}")
warn('tag url_key lost because RD merged two tags that differ only in case (the old address 404s)', merged)

# ---- 3. images on disk and no stale paths -----------------------------------
stale = int(one("SELECT COUNT(*) FROM requestdesk_blog_post WHERE content LIKE '%amasty/blog%' OR featured_image LIKE '%amasty/blog%'"))
check('no amasty/blog path left in RD posts', stale == 0, f'{stale} posts still point at amasty/blog')
imgs = sorted({r[0] for r in sql("SELECT DISTINCT featured_image FROM requestdesk_blog_post WHERE featured_image<>''")})
missing = []
for i in imgs:
    p = subprocess.run(['docker', 'exec', args.phpfpm, 'test', '-f', f'/var/www/html/pub/media/{i}'])
    if p.returncode: missing.append(i)
check('every featured image file exists on disk', not missing, f'{len(missing)} missing, first: {missing[:5]}')

# ---- 4. idempotency ---------------------------------------------------------
def fingerprint():
    return hashlib.md5(json.dumps([sql(f"CHECKSUM TABLE {t}") for t in
        ('requestdesk_blog_post', 'requestdesk_blog_post_tag', 'requestdesk_blog_tag', 'requestdesk_blog_author',
         'requestdesk_blog_category', 'requestdesk_blog_category_post')]).encode()).hexdigest()
if args.rerun:
    before = fingerprint()
    for c in ('migrate-amasty', 'migrate-amasty-categories', 'migrate-media'):
        r = subprocess.run(['docker', 'exec', args.phpfpm, 'bash', '-c', f'cd /var/www/html && bin/magento requestdesk:blog:{c}'],
                           capture_output=True, text=True)
        check(f'rerun {c} exits 0', r.returncode == 0, r.stdout[-200:])
    check('rerun changed nothing', before == fingerprint(), 'table checksums differ after second run')

# ---- report -----------------------------------------------------------------
w = max(len(n) for n, _, _ in checks)
for n, ok, d in checks:
    print(f"{'PASS' if ok else 'FAIL'}  {n.ljust(w)}  {'' if ok else d}")
for n,c,ex in warnings:
    print(f"WARN  {n}: {c} posts, first: {ex}")
print(f"\n{len(checks)-len(failures)} passed, {len(failures)} failed; posts compared: {len(am_keys & rd_keys)}")
if args.json:
    json.dump({'checks': checks, 'differences': bad, 'warnings': warnings}, open(args.json, 'w'), indent=1)
sys.exit(1 if failures else 0)
