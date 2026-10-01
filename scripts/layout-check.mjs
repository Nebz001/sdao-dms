/**
 * Layout check for the authenticated app: for each role, theme and viewport it
 * visits every sidebar page and reports horizontal page overflow, elements
 * poking past the viewport, clipped text and ellipsis truncation.
 *
 *   node scripts/layout-check.mjs [port]      (default port 8001)
 *
 * The script starts its OWN throwaway Laravel server and never takes a URL, so
 * it cannot be pointed at your dev app. Everything below is set through
 * environment variables for the child processes only (this shell and your
 * dev app are untouched):
 *
 *   LARAVEL_STORAGE_PATH  a temp storage dir (cache, sessions, views, logs),
 *                         so nothing is written to storage/ in the project
 *   DB_CONNECTION=sqlite, DB_DATABASE=<temp file>, DB_URL=   throwaway database
 *   CACHE_STORE=array     no file cache at all (the current period is cached
 *                         "forever", so a shared file cache would leak into
 *                         the dev app)
 *   SESSION_DRIVER=database   sessions live in the throwaway database
 *   QUEUE_CONNECTION=sync, MAIL_MAILER=array, LOG_CHANNEL=single
 *
 * Before it runs a single check it asks the app itself (artisan tinker) which
 * database, cache store and storage path it resolved, and aborts unless they
 * are the temp ones. It migrates, seeds the full demo data (the same wipe and
 * DemoDataSeeder that demo:reset uses, minus its Postgres-only statement),
 * switches the throwaway database to 3rd term so the renewal form is open, and
 * deletes the temp dir and stops the server when it finishes. public/hot is
 * only read, never changed. Accounts are the seeded ones (password ict@1234).
 * Screenshots of the main pages go to the OS temp folder under shots/b.
 */
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { chromium } from 'playwright';
const PASSWORD = 'ict@1234';
const roles = {
  sdao: ['sdao-a@nu-lipa.edu.ph', ['/review/registrations/1', '/admin/approvers/create', '/notifications']],
  adviser: ['adviser-one@nu-lipa.edu.ph', ['/notifications']],
  dean: ['dean-ccit@nu-lipa.edu.ph', ['/notifications']],
  officer: ['student-alpha@students.nu-lipa.edu.ph', ['/registrations/create', '/organizations/join', '/notifications']],
  // Needs the full demo data (php artisan demo:reset on a throwaway database).
  president: ['torresm@students.nu-lipa.edu.ph', ['/activity-proposals/31', '/activity-calendars/create', '/renewals/create', '/organizations/officer-change', '/notifications']],
};
const viewports = { desktop: [1366, 860], tablet: [1024, 768], phone: [390, 844] };
const out = join(tmpdir(), 'shots', 'b'); mkdirSync(out, { recursive: true });

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

async function runChecks(base) {
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
}

const projectRoot = resolve(import.meta.dirname, '..');
const port = process.argv[2] || '8001';
const root = mkdtempSync(join(tmpdir(), 'layout-check-'));
const dbFile = join(root, 'db.sqlite');
const storage = join(root, 'storage');
for (const dir of ['framework/cache/data', 'framework/sessions', 'framework/views', 'framework/testing', 'logs']) {
  mkdirSync(join(storage, dir), { recursive: true });
}
writeFileSync(dbFile, '');

const env = {
  ...process.env,
  LARAVEL_STORAGE_PATH: storage,
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: dbFile,
  DB_URL: '',
  CACHE_STORE: 'array',
  SESSION_DRIVER: 'database',
  QUEUE_CONNECTION: 'sync',
  MAIL_MAILER: 'array',
  LOG_CHANNEL: 'single',
};

function artisan(args, { quiet = true } = {}) {
  const result = spawnSync('php', ['artisan', ...args], { cwd: projectRoot, env, encoding: 'utf8' });
  if (result.status !== 0) {
    throw new Error(`php artisan ${args[0]} failed:
${result.stdout}
${result.stderr}`);
  }
  if (!quiet) console.log(result.stdout);
  return result.stdout;
}

const SEED_AND_PERIOD = `
$c = app(App\\Console\\Commands\\ResetDemoData::class);
$m = new ReflectionMethod($c, 'wipe');
DB::transaction(fn () => $m->invoke($c));
DB::transaction(fn () => app(Database\\Seeders\\DemoDataSeeder::class)->run());
DB::table('settings')->where('key', 'current_period')->update(['value' => '2026-2027:third_term']);
`;

const WHERE_AM_I = `
echo json_encode([
  'db' => DB::connection()->getDatabaseName(),
  'cache' => config('cache.default'),
  'session' => config('session.driver'),
  'storage' => storage_path(),
]);
`;

function assertIsolated() {
  const out = artisan(['tinker', '--execute', WHERE_AM_I]);
  const info = JSON.parse(out.slice(out.indexOf('{')));
  const norm = (p) => resolve(p).toLowerCase();
  const ok =
    norm(info.db) === norm(dbFile) &&
    info.cache === 'array' &&
    info.session === 'database' &&
    norm(info.storage).startsWith(norm(storage));
  if (!ok) {
    throw new Error(`Refusing to run: the app is not isolated. Resolved ${JSON.stringify(info)}`);
  }
  console.log('Isolated:', JSON.stringify(info));
}

let server = null;

function stopServer() {
  if (server?.pid) {
    if (process.platform === 'win32') {
      spawnSync('taskkill', ['/pid', String(server.pid), '/T', '/F']);
    } else {
      server.kill();
    }
  }
}

async function waitForServer(base) {
  for (let i = 0; i < 60; i++) {
    try {
      const res = await fetch(`${base}/login`);
      if (res.ok) return;
    } catch {
      // not up yet
    }
    await new Promise((r) => setTimeout(r, 500));
  }
  throw new Error('The throwaway server did not start.');
}

async function main() {
  assertIsolated();
  artisan(['migrate:fresh', '--force', '--no-interaction']);
  artisan(['db:seed', '--force']);
  artisan(['db:seed', '--class=Database\\Seeders\\IdentitySeeder', '--force']);
  artisan(['tinker', '--execute', SEED_AND_PERIOD]);
  assertIsolated();

  server = spawn('php', ['artisan', 'serve', `--port=${port}`], { cwd: projectRoot, env, stdio: 'ignore' });
  const base = `http://127.0.0.1:${port}`;
  await waitForServer(base);
  await runChecks(base);
}

main()
  .catch((error) => {
    console.error(error.message);
    process.exitCode = 1;
  })
  .finally(() => {
    stopServer();
    rmSync(root, { recursive: true, force: true });
  });
