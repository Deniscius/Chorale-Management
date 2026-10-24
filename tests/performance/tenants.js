import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';

const base = (__ENV.BASE_URL || 'http://127.0.0.1:8002').replace(/\/$/, '');
if (!/^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(base)) throw Error('Localhost HTTP required');
if (!__ENV.K6_PASSWORD || !__ENV.K6_FIXTURES) throw Error('K6_PASSWORD and K6_FIXTURES required');
const accounts = JSON.parse(open(__ENV.K6_FIXTURES)).accounts;
if (accounts.length !== 2) throw Error('Two distinct test tenants required');
const profile = __ENV.PROFILE || 'smoke';
if (!['smoke','load'].includes(profile)) throw Error('Unknown PROFILE');
const smoke = profile === 'smoke';
const pages = new Trend('tenant_page_duration', true);
const pageCount = new Counter('tenant_pages');
const isolation = new Rate('tenant_isolation');
const routes = ['/dashboard','/membres','/oeuvres','/repetitions','/celebrations','/temps-liturgiques','/rappels','/instruments','/instruments/mouvements','/instruments/maintenance','/instruments/ensembles','/courriers','/activites','/transactions','/budgets','/cotisations','/dons','/documents?commission=commission_liturgique','/utilisateurs','/administration/journal'];
const thresholds = {http_req_failed:['rate<0.01',{threshold:'rate<0.05',abortOnFail:true,delayAbortEval:'30s'}], http_req_duration:['p(95)<2000'],checks:['rate==1'],tenant_isolation:['rate==1'],tenant_page_duration:['p(95)<1500']};
for (const tenant of ['alpha','beta']) {
    thresholds['tenant_page_duration{tenant:'+tenant+'}']=['p(95)<1500'];
    thresholds['tenant_pages{tenant:'+tenant+'}']=['count>0'];
}
export const options = {noCookiesReset:true,thresholds,summaryTrendStats:['avg','med','p(95)','max'],scenarios:smoke?{verify:{executor:'shared-iterations',vus:1,iterations:1,maxDuration:'120s'}}:{browse:{executor:'ramping-vus',startVUs:0,stages:[{duration:'15s',target:10},{duration:'15s',target:10},{duration:'20s',target:30},{duration:'30s',target:30},{duration:'20s',target:50},{duration:'45s',target:50},{duration:'15s',target:5},{duration:'20s',target:5},{duration:'10s',target:0}],gracefulRampDown:'15s',gracefulStop:'15s'}}};
function params(name, jar, tenant, status=200) {return {jar,redirects:0,timeout:'15s',tags:{name,tenant},responseCallback:http.expectedStatuses(status)};}
function prereq(ok, message) {if(!check(ok,{'scenario prerequisites':v=>v}))fail(message);}
function login(account, jar, tenant) {
    const form=http.get(base+'/login',params('auth.form',jar,tenant));
    prereq(form.status===200 && form.body,'Login form unavailable');
    const token=form.html().find('input[name="_token"]').attr('value');
    prereq(token,'CSRF missing');
    const response=http.post(base+'/login',{_token:token,login:account.username,password:__ENV.K6_PASSWORD},params('auth.login',jar,tenant,302));
    prereq(response.status===302 && !String(response.headers.Location).includes('/login'),'Login failed');
    for(const [id,status] of [[account.own_document,200],[account.foreign_document,404]]) {
        const r=http.get(base+'/documents/'+id+'/telecharger',params('isolation.download',jar,tenant,status));
        isolation.add(check(r,{'download respects tenant':r=>r.status===status}));
    }
}
function page(route,account,jar,tenant) {
    const r=http.get(base+route,params('page '+route,jar,tenant));
    pages.add(r.timings.duration,{tenant,route});
    pageCount.add(1,{tenant});
    check(r,{'page and tenant branding correct':r=>r.status===200 && r.body && r.body.includes(account.brand)});
    return r;
}
function reset(r, jar, tenant) {
    if(r.status!==200 || !r.body)return;
    const snapshot=r.html().find('[wire\\:snapshot]').first().attr('wire:snapshot');
    const uri=r.html().find('script[data-update-uri]').attr('data-update-uri');
    const token=r.html().find('meta[name="csrf-token"]').attr('content');
    prereq(snapshot && uri && token,'Livewire context missing');
    const url=uri.startsWith('/')?base+uri:uri;
    prereq(url.startsWith(base+'/'),'Unexpected update endpoint');
    const p=params('livewire.resetFilters',jar,tenant);p.headers={'Content-Type':'application/json','X-CSRF-TOKEN':token,'X-Livewire':'true'};
    const result=http.post(url,JSON.stringify({_token:token,components:[{snapshot,updates:{},calls:[{path:'',method:'resetFilters',params:[]}]}]}),p);
    check(result,{'tenant Livewire action succeeds':r=>r.status===200});
}
let authenticated=false;
export default function () {
    if(smoke) {
        for(let i=0;i<accounts.length;i++) {
            const jar=new http.CookieJar(),tenant=i===0?'alpha':'beta',account=accounts[i];
            login(account,jar,tenant);
            for(const route of routes){const r=page(route,account,jar,tenant);if(!['/dashboard','/administration/journal'].includes(route))reset(r,jar,tenant);}
        }
        return;
    }
    const i=(__VU-1)%2,account=accounts[i],tenant=i===0?'alpha':'beta',jar=http.cookieJar();
    if(!authenticated){login(account,jar,tenant);authenticated=true;}
    const route=routes[(__ITER+__VU-1)%routes.length],r=page(route,account,jar,tenant);
    if(__ITER%5===0 && !['/dashboard','/administration/journal'].includes(route))reset(r,jar,tenant);
    sleep(0.4+(__VU%3)*0.1);
}
export function handleSummary(data) {
    const out={stdout:JSON.stringify({checks:data.metrics.checks,errors:data.metrics.http_req_failed,isolation:data.metrics.tenant_isolation},null,2)+'\n'};
    if(__ENV.SUMMARY_PATH)out[__ENV.SUMMARY_PATH]=JSON.stringify(data,null,2);
    return out;
}
