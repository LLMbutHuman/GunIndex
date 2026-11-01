#!/usr/bin/env python3
"""Small bounded local HTTP measurement, not a production capacity promise."""
import argparse,concurrent.futures,json,statistics,time,urllib.error,urllib.parse,urllib.request
def run(url,requests,concurrency):
 parsed=urllib.parse.urlsplit(url)
 if parsed.scheme!='http' or parsed.hostname not in ('127.0.0.1','localhost','::1') or parsed.username or parsed.password or parsed.fragment:raise ValueError('Only an explicitly local HTTP development target is accepted.')
 if not 1<=requests<=200 or not 1<=concurrency<=8:raise ValueError('Use1..200 requests and1..8 workers.')
 def one(_):
  opener=urllib.request.build_opener(urllib.request.ProxyHandler({}));start=time.perf_counter()
  try:
   with opener.open(urllib.request.Request(url,headers={'User-Agent':'GunIndex-local-load-smoke'}),timeout=15) as response:response.read();status=response.status
  except urllib.error.HTTPError as error:status=error.code
  except OSError:status=0
  return status,(time.perf_counter()-start)*1000
 start=time.perf_counter()
 with concurrent.futures.ThreadPoolExecutor(max_workers=concurrency) as pool:rows=list(pool.map(one,range(requests)))
 times=sorted(row[1] for row in rows);statuses={str(code):sum(1 for row in rows if row[0]==code) for code in sorted(set(row[0] for row in rows))}
 return {'target_path':parsed.path or '/','requests':requests,'concurrency':concurrency,'elapsed_seconds':round(time.perf_counter()-start,3),'status_counts':statuses,'latency_ms':{'median':round(statistics.median(times),2),'p95':round(times[max(0,int(len(times)*.95)-1)],2),'maximum':round(max(times),2)},'all_200':all(row[0]==200 for row in rows),'scope':'local development sample; not Core Web Vitals or production load certification'}
if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--url',default='http://127.0.0.1:8081/');parser.add_argument('--requests',type=int,default=40);parser.add_argument('--concurrency',type=int,default=4);args=parser.parse_args()
 try:result=run(args.url,args.requests,args.concurrency)
 except ValueError as error:parser.error(str(error))
 print(json.dumps(result,indent=2));raise SystemExit(0 if result['all_200'] else 1)
