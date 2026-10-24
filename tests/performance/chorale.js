import http from 'k6/http';
import { check, sleep, fail } from 'k6';
import { Trend, Rate } from 'k6/metrics';

const base = (__ENV.BASE_URL || 'http://127.0.0.1:8002').replace(/\/$/, '');
if (!/^http:\/\/(127\.0\.0\.1|localhost)(:\d+)?$/.test(base)) throw new Error('Localhost HTTP target required.');
const profile = __ENV.PROFILE || 'smoke';
if (!['smoke', 'load', 'privacy'].includes(profile)) throw new Error('Unknown PROFILE');
const password = __ENV.K6_PASSWORD;
if (!password) throw new Error('K6_PASSWORD is required.');
const routes = ['/dashboard','/membres','/oeuvres','/repetitions','/celebrations','/temps-liturgiques','/rappels','/instruments','/instruments/mouvements','/instruments/maintenance','/instruments/ensembles','/courriers','/activites','/transactions','/budgets','/cotisations','/dons','/documents?commission=commission_liturgique','/utilisateurs','/administration/journal'];
const pages = new Trend('page_duration', true);
const security = new Rate('privacy_checks');
let authenticated = false;
function abort(message) { check(false, {'scenario prerequisites available':()=>false}); fail(message); }
function params(name, jar, expected=200) { return {jar, redirects:0, timeout:'15s', tags:{name}, responseCallback:http.expectedStatuses(expected)}; }
function login(username, jar=http.cookieJar()) {
    const page=http.get(base+'/login', params('auth.form',jar));
    const token=page.html().find('input[name="_token"]').attr('value');
    if (!token) abort('Login form missing CSRF token');
    const response=http.post(base+'/login',{_token:token,login:username,password},params('auth.login',jar,302));
    if (!check(response, {'login accepted':r=>r.status===302 && !String(r.headers.Location).includes('/login')})) abort('Authentication failed');
    const dashboard=http.get(base+'/dashboard',params('auth.confirm',jar));
    if (!check(dashboard,{'authenticated dashboard':r=>r.status===200 && r.body.includes('Aller au contenu principal')})) abort('Authenticated session missing');
}
function page(path, jar=http.cookieJar()) {
    const response=http.get(base+path,params('page '+path,jar));
    if(response.status!==200) console.warn('page failure '+response.status+' '+path);
    pages.add(response.timings.duration,{route:path});
    check(response,{'page accessible':r=>r.status===200 && r.body.includes('Aller au contenu principal')},{route:path});
    return response;
}
function livewire(response, method, jar=http.cookieJar()) {
    const snapshot=response.html().find('[wire\\:snapshot]').first().attr('wire:snapshot');
    const update=response.html().find('script[data-update-uri]').attr('data-update-uri');
    const token=response.html().find('meta[name="csrf-token"]').attr('content');
    if (!snapshot || !update || !token) abort('Livewire context absent on '+response.url);
    const url=update.startsWith('/')?base+update:update;
    if (!url.startsWith(base+'/')) abort('Unexpected Livewire endpoint');
    const p=params('livewire '+method,jar);p.headers={'Content-Type':'application/json','X-CSRF-TOKEN':token,'X-Livewire':'true'};
    const result=http.post(url,JSON.stringify({_token:token,components:[{snapshot,updates:{},calls:[{path:'',method,params:[]}]}]}),p);
    if(result.status!==200) console.warn('Livewire failure '+result.status+' '+response.url);
    check(result,{'Livewire action succeeds':r=>r.status===200 && Array.isArray(r.json('components'))});
    return result;
}
const thresholds={'http_req_failed':['rate<0.01'],checks:['rate==1']};
if(profile!=='privacy') {thresholds.page_duration=['p(95)<1500'];thresholds.http_req_duration=['p(95)<2000']; for(const route of routes) thresholds['page_duration{route:'+route+'}']=['p(95)<1500'];}
if(profile==='privacy') thresholds.privacy_checks=['rate==1'];
export const options={noCookiesReset:true,thresholds,scenarios:profile==='load'?{browse:{executor:'ramping-vus',startVUs:1,stages:[{duration:'15s',target:1},{duration:'20s',target:5},{duration:'25s',target:10},{duration:'10s',target:0}],gracefulRampDown:'10s',gracefulStop:'10s'}}:{verify:{executor:'shared-iterations',vus:1,iterations:1,maxDuration:'120s'}},summaryTrendStats:['avg','med','p(90)','p(95)','max']};
export default function () {
    if(profile==='privacy') {
        const privateL=__ENV.LIT_PRIVATE_ID, privateS=__ENV.MAIL_PRIVATE_ID, shared=__ENV.SHARED_ID;
        if(![privateL,privateS,shared].every(x=>/^\d+$/.test(x||''))) abort('Privacy fixture IDs required');
        for(const [user,canL,canS] of [['demo.admin',false,false],['demo.liturgie',true,false],['demo.associe',true,false],['demo.secretariat',false,true],['demo.associe.courriers',false,true],['demo.choriste',false,false]]) {
            const jar=new http.CookieJar();login(user,jar);
            for(const [id,allowed] of [[privateL,canL],[privateS,canS],[shared,true]]) {
                const response=http.get(base+'/documents/'+id+'/telecharger',params('privacy download',jar,allowed?200:403));
                const ok=check(response,{'archive permission correct':r=>r.status===(allowed?200:403)},{role:user});security.add(ok);
            }
        }
        const guest=http.get(base+'/documents/'+shared+'/telecharger',params('privacy guest',new http.CookieJar(),302));
        security.add(check(guest,{'guest redirected to login':r=>r.status===302 && String(r.headers.Location).includes('/login')}));
        return;
    }
    if(!authenticated){login(__ENV.K6_USERNAME || 'demo.admin');authenticated=true;}
    if(profile==='smoke') {
        for(const route of routes) {const r=page(route);if(!['/dashboard','/administration/journal'].includes(route)) livewire(r,'resetFilters');}
        const members=page('/membres?statut=actif');
        livewire(members,'resetFilters');
        const opened=livewire(members,'create');
        check(opened,{'modal is rendered':r=>r.status===200 && r.body.includes('Nouveau choriste')});
        return;
    }
    const route=routes[(__ITER+__VU-1)%routes.length];
    const response=page(route);
    if(__ITER%5===0 && route!=='/dashboard' && route!=='/administration/journal') livewire(response,'resetFilters');
    sleep(0.4+(__VU%3)*0.1);
}
export function handleSummary(data) {
    const result={stdout:JSON.stringify({profile,metrics:data.metrics},null,2)+'\n'};
    if(__ENV.SUMMARY_PATH) result[__ENV.SUMMARY_PATH]=JSON.stringify(data,null,2);
    return result;
}
