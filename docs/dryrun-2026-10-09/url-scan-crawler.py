import sys, csv, time, re, json, urllib.request, urllib.error, urllib.parse
from html.parser import HTMLParser
base, outcsv, keysfile = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
prefix, delay = '/commerce-co-op', 0.5
UA = 'CB-migration-audit/1.0'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
opener = urllib.request.build_opener(NoRedirect)
import ssl
ctx = ssl.create_default_context()
if 'evrig.test' in base or 'ma.test' in base:
    ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
opener.add_handler(urllib.request.HTTPSHandler(context=ctx))
class P(HTMLParser):
    def __init__(s): super().__init__(); s.title=''; s.title_done=False; s.h1=''; s.in_t=s.in_h1=False; s.meta={}; s.canon=''; s.ld=[]; s.in_ld=False; s.links=[]; s.imgs=0; s.words=0; s.skip=0
    def handle_starttag(s,t,a):
        a=dict(a)
        if t=='title' and not s.title_done: s.in_t=True
        elif t=='h1' and not s.h1: s.in_h1=True
        elif t=='meta':
            k=a.get('name') or a.get('property')
            if k: s.meta[k]=a.get('content','')
        elif t=='link' and a.get('rel')=='canonical': s.canon=a.get('href','')
        elif t=='script' and a.get('type')=='application/ld+json': s.in_ld=True; s.ld.append('')
        elif t in('script','style'): s.skip+=1
        elif t=='a' and a.get('href'): s.links.append(a['href'])
        elif t=='img': s.imgs+=1
    def handle_endtag(s,t):
        if t=='title' and s.in_t: s.in_t=False; s.title_done=True
        elif t=='h1': s.in_h1=False
        elif t=='script': s.in_ld=False; s.skip=max(0,s.skip-1) if not s.ld or True else s.skip
        elif t=='style': s.skip=max(0,s.skip-1)
    def handle_data(s,d):
        if s.in_t: s.title+=d
        if s.in_h1: s.h1+=d
        if s.in_ld and s.ld: s.ld[-1]+=d
        if not s.in_ld and not s.skip: s.words+=len(d.split())
def fetch(url):
    chain=[]; cur=url
    for _ in range(6):
        req=urllib.request.Request(cur,headers={'User-Agent':UA})
        try:
            r=opener.open(req,timeout=30); body=r.read().decode('utf-8','replace'); return chain,r.status,cur,body
        except urllib.error.HTTPError as e:
            if e.code in(301,302,307,308):
                loc=urllib.parse.urljoin(cur,e.headers.get('Location','')); chain.append(f'{e.code}>{loc}'); cur=loc; continue
            return chain,e.code,cur,e.read().decode('utf-8','replace')
        except Exception as e: return chain,'ERR:'+type(e).__name__,cur,''
    return chain,'LOOP',cur,''
keys=[l.split('\t')[0] for l in open(keysfile).read().split('\n') if l.strip()]
seed=[base+'/', base+prefix+'.html']+[f'{base}{prefix}.html?p={i}' for i in range(2,40)]
for k in keys: seed+= [f'{base}{prefix}/{k}.html', f'{base}{prefix}/{k}']
if len(sys.argv) > 4:   # seed from the before-scan so the comparison is one to one
    import csv as _csv
    seed = [base + r['url_path'] for r in _csv.DictReader(open(sys.argv[4]))]
seen=set(); rows=[]; queue=list(seed)
host=urllib.parse.urlparse(base).netloc
while queue:
    u=queue.pop(0)
    if u in seen: continue
    seen.add(u)
    chain,status,final,body=fetch(u)
    p=P()
    if isinstance(status,int) and status==200 and body: p.feed(body)
    def norm(x): return urllib.parse.urlparse(x).path+('?'+urllib.parse.urlparse(x).query if urllib.parse.urlparse(x).query else '')
    types=[]
    for blk in p.ld:
        try:
            j=json.loads(blk); items=j if isinstance(j,list) else j.get('@graph',[j])
            for it in items: types.append(str(it.get('@type')))
        except Exception: types.append('PARSEERR')
    rows.append({'url_path':norm(u),'status':status,'chain':' | '.join(chain),'final_path':norm(final),'canonical':norm(p.canon) if p.canon else '','title':' '.join(p.title.split()),'meta_description':p.meta.get('description',''),'h1':' '.join(p.h1.split()),'og_image':norm(p.meta.get('og:image','')) if p.meta.get('og:image') else '','robots':p.meta.get('robots',''),'jsonld_types':';'.join(sorted(types)),'words':p.words,'images':p.imgs,'links':len(p.links)})
    if status==200:
        for h in p.links:
            h2=urllib.parse.urljoin(final,h).split('#')[0]
            pr=urllib.parse.urlparse(h2)
            if pr.netloc==host and pr.path.startswith(prefix+'/') and any(x in pr.path for x in ('/author/','/tag/','/category/','/search')) and h2 not in seen: queue.append(h2)
    time.sleep(delay)
with open(outcsv,'w',newline='') as f:
    w=csv.DictWriter(f,fieldnames=list(rows[0].keys())); w.writeheader(); w.writerows(rows)
print('done',len(rows),'urls', sum(1 for r in rows if r['status']==200),'x200')
