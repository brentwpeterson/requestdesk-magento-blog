import csv, sys, re
b = {r['url_path']: r for r in csv.DictReader(open(sys.argv[1]))}
a = {r['url_path']: r for r in csv.DictReader(open(sys.argv[2]))}
def norm(f, row):
    if f == 'canonical_path': return row.get('canonical', '')
    if f == 'robots': return row.get('robots', '').lower().replace(' ', '')
    return row.get(f, '')
def strip(p): return re.sub(r'\.html$', '', p.split('?')[0]) + ('?' + p.split('?')[1] if '?' in p else '')
out, tally = [], {}
for path, r in b.items():
    x = a.get(path)
    if r['status'] != '200':      # dead before too (404, redirect loop): not an SEO loss
        verdict = 'dead-before'; diffs = ''
    elif not x:
        verdict = 'not-scanned-after'; diffs = ''
    elif x['status'] == '200' or (x['chain'] and x['status'] == '200'):
        diffs = [f for f in ('canonical_path', 'title', 'meta_description', 'h1', 'og_image', 'jsonld_types', 'robots') if
                 norm(f, r) != norm(f, x)]
        verdict = 'same' if not diffs else 'changed'
        diffs = ','.join(diffs)
    elif x['status'] in ('404', '410', '500', '502', '503') or str(x['status']).startswith('ERR'):
        verdict = 'LOST'; diffs = f"after status {x['status']}"
    else:
        verdict = 'check'; diffs = f"after status {x['status']}"
    tally[verdict] = tally.get(verdict, 0) + 1
    out.append({'url_path': path, 'before_status': r['status'], 'before_final': r['final_path'], 'after_status': x['status'] if x else '', 'after_final': x['final_path'] if x else '', 'verdict': verdict, 'fields_changed': diffs})
with open(sys.argv[3], 'w', newline='') as f:
    w = csv.DictWriter(f, fieldnames=list(out[0].keys())); w.writeheader(); w.writerows(out)
print(tally)
