/**
 * Layout check for the authenticated app: for each role, theme and viewport it
 * visits every sidebar page and reports horizontal page overflow, elements
 * poking past the viewport, clipped text and ellipsis truncation.
 *
 * Run against a throwaway database, never the dev one:
 *   node scripts/layout-check.mjs http://127.0.0.1:8001
 * Accounts are the IdentitySeeder ones (password ict@1234). Screenshots of the
 * main pages go to the OS temp folder under shots/b.
 */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
const base = process.argv[2] || 'http://127.0.0.1:8001';
const PASSWORD = 'ict@1234';
const roles = {
  sdao: ['sdao-a@nu-lipa.edu.ph', ['/review/registrations/1', '/admin/approvers/create', '/notifications']],
  adviser: ['adviser-one@nu-lipa.edu.ph', ['/notifications']],
  dean: ['dean-ccit@nu-lipa.edu.ph', ['/notifications']],
  officer: ['student-alpha@students.nu-lipa.edu.ph', ['/registrations/create', '/notifications']],
};
const viewports = { desktop: [1366, 860], tablet: [1024, 768], phone: [390, 844] };
const out = tmpdir() + '/shots/b'; mkdirSync(out, { recursive: true });

async function audit(page) {
  return page.evaluate(() => {
    const vw = document.documentElement.clientWidth;
    const issues = [];
    const inScroller = (el) => { for (let p = el.parentElement; p; p = p.parentElement) { const s = getComputedStyle(p); if (/(auto|scroll)/.test(s.overflowX) && p.scrollWidth > p.clientWidth + 1) return true; if (p.hasAttribute('data-radix-scroll-area-viewport')) return true; } return false; };
    if (document.documentElement.scrollWidth > vw + 1) issues.push(`PAGE overflow-x: scrollWidth ${document.documentElement.scrollWidth} > ${vw}`);
    for (const el of document.body.querySelectorAll('*')) {
      const r = el.getBoundingClientRect(); if (r.width === 0 || r.height === 0 || el.closest('.sr-only')) continue;
      const s = getComputedStyle(el); if (s.visibility === 'hidden' || s.display === 'none' || s.position === 'fixed') continue;
      const label = `${el.tagName.toLowerCase()}${el.className && typeof el.className === 'string' ? '.' + el.className.split(/\s+/).slice(0, 3).join('.') : ''}`;
      if (r.right > vw + 1 && !inScroller(el) && !el.closest('[data-slot=sidebar-container],[data-slot=sidebar-gap]')) issues.push(`PAST-VIEWPORT ${label} right=${Math.round(r.right)} vw=${vw} "${(el.textContent || '').trim().slice(0, 30)}"`);
      const txt = (el.childNodes.length && [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim()));
      if (txt && s.textOverflow === 'ellipsis' && el.scrollWidth > el.clientWidth + 1) issues.push(`ELLIPSIS ${label} "${el.textContent.trim().slice(0, 40)}"`);
      else if (txt && /(hidden|clip)/.test(s.overflowX) && s.textOverflow !== 'ellipsis' && el.scrollWidth > el.clientWidth + 1 && !el.closest('[data-slot=sidebar-menu-button]')) issues.push(`CLIPPED-X ${label} "${el.textContent.trim().slice(0, 40)}"`);
      if (txt && /(hidden|clip)/.test(s.overflowY) && el.scrollHeight > el.clientHeight + 2 && s.display !== 'inline') issues.push(`CLIPPED-Y ${label} "${el.textContent.trim().slice(0, 40)}"`);
    }
    return [...new Set(issues)];
  });
}

(async () => {
  const b = await chromium.launch(); let total = 0;
  for (const [role, [email, extra]] of Object.entries(roles)) {
    const lctx = await b.newContext(); const lp = await lctx.newPage();
    await lp.goto(base + '/login', { waitUntil: 'networkidle' });
    await lp.fill('input[name=email]', email); await lp.fill('input[name=password]', PASSWORD); await lp.click('button[type=submit]');
    await lp.waitForURL((u) => !u.pathname.startsWith('/login')); await lp.waitForLoadState('networkidle');
    const links = await lp.$$eval('[data-sidebar=content] a', (as) => [...new Set(as.map((a) => new URL(a.href).pathname))]);
    const state = await lctx.storageState(); await lctx.close();
    for (const scheme of ['light', 'dark']) {
      for (const [vp, [w, h]] of Object.entries(viewports)) {
        const ctx = await b.newContext({ viewport: { width: w, height: h }, colorScheme: scheme, storageState: state });
        const page = await ctx.newPage(); const errors = [];
        page.on('pageerror', (e) => errors.push(e.message.slice(0, 120)));
        const pages = ['/dashboard', ...links, ...extra]; const seen = new Set();
        for (const p of pages) {
          if (seen.has(p)) continue; seen.add(p);
          await page.goto(base + p, { waitUntil: 'load' }); await page.waitForTimeout(500);
          const issues = await audit(page);
          const tag = `${role}/${scheme}/${vp} ${p}`;
          if (issues.length) { total += issues.length; console.log('!!', tag); issues.forEach((i) => console.log('   ', i)); }
          if (['/dashboard', '/admin/dashboard'].includes(p) || p === '/review/registrations' || p === '/registrations/create') await page.screenshot({ path: `${out}/${role}-${scheme}-${vp}-${p.replace(/\//g, '_')}.png` });
        }
        if (errors.length) console.log('JS ERRORS', role, scheme, vp, errors);
        await ctx.close();
      }
    }
  }
  console.log('TOTAL ISSUES', total); await b.close();
})();
