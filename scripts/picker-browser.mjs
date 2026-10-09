// picker-browser.mjs — the headless-browser pass for the record picker in Projects (adapted from the design-system's
// examples/php/record-picker/picker-browser.mjs). Run after scripts/prove-picker.sh has seeded the scratch database (projects PKA, PKP, PK01..PK28,
// members 9001, 9002, 9101..9130) and with the scratch php -S up:
//   php -S 127.0.0.1:8493 -t html scripts/picker-router.php &
//   mkdir -p /tmp/projects-picker && cd /tmp/projects-picker && ln -s /var/www/web/node_modules node_modules
//   S=$PWD BASE=http://127.0.0.1:8493 SSO="$(php sso-query.php)" node /srv/apps/projects-picker/scripts/picker-browser.mjs
// SSO is the query string of a hand-off token for member 9001 signed with the scratch ACTION_TOKEN_KEY (the proof's sso() shows how).
import { chromium } from 'playwright';
const S = process.env.S || '.', B = process.env.BASE || 'http://127.0.0.1:8493', SSO = process.env.SSO;
const ok = (n, c) => console.log((c ? 'ok   ' : 'FAIL ') + n);
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await ctx.newPage();
const errors = []; page.on('pageerror', e => errors.push(e.message)); page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
await page.goto(B + '/sso?' + SSO);
const F = '#issue-form-field-assignee';
await page.goto(B + '/issues/new?project=PKA'); await page.waitForSelector(F + '-open');
await page.screenshot({ path: S + '/1-form-desktop.png' });
ok('the opener shows the placeholder Unassigned', (await page.textContent(F + '-open')).trim() === 'Unassigned');
await page.click(F + '-open');
await page.waitForSelector('#record-picker.show'); await page.waitForSelector('#record-picker-list .record-picker-row');
ok('the modal opens with the first page (25 rows)', (await page.$$('#record-picker .record-picker-row')).length === 25);
ok('the project\'s team is first, badged', await page.evaluate(() => [...document.querySelectorAll('#record-picker .record-picker-row')].slice(0, 3).every(r => r.textContent.includes('Team'))));
await page.waitForFunction(() => document.activeElement.id === 'record-picker-search', null, { timeout: 3000 }).catch(() => {});
ok('the search box has focus', await page.evaluate(() => document.activeElement.id === 'record-picker-search'));
ok('the title is the field\'s', (await page.textContent('#record-picker-title')).trim() === 'Assignee');
ok('no New button for a field without create', await page.evaluate(() => document.getElementById('record-picker-footer').classList.contains('d-none')));
await page.type('#record-picker-search', 'person 0'); await page.waitForTimeout(700);
const n = (await page.$$('#record-picker .record-picker-row')).length; ok('typing narrows the list (' + n + ' rows)', n === 9);
await page.screenshot({ path: S + '/2-picker-desktop.png' });
await page.keyboard.press('ArrowDown'); ok('ArrowDown moves to the first row', await page.evaluate(() => document.activeElement.classList.contains('record-picker-row')));
await page.keyboard.press('ArrowDown'); const label = await page.evaluate(() => document.activeElement.dataset.label);
await page.keyboard.press('Enter'); await page.waitForSelector('#record-picker', { state: 'hidden' });
ok('Enter picks the focused row (' + label + ')', (await page.textContent(F + '-open')).trim() === label && (await page.inputValue(F)) !== '');
await page.waitForFunction(id => document.activeElement.id === id, F.slice(1) + '-open', { timeout: 3000 }).catch(() => {});
ok('focus returns to the opener', await page.evaluate(id => document.activeElement.id === id, F.slice(1) + '-open'));
ok('the Clear button appears', await page.evaluate(id => !document.getElementById(id).classList.contains('d-none'), F.slice(1) + '-clear'));
await page.click(F + '-clear'); ok('Clear empties the field and restores the placeholder', (await page.inputValue(F)) === '' && (await page.textContent(F + '-open')).trim() === 'Unassigned');
await page.click(F + '-open'); await page.waitForSelector('#record-picker .record-picker-more');
await page.click('#record-picker-more'); await page.waitForFunction(() => document.querySelectorAll('#record-picker .record-picker-row').length > 25);
ok('Show more appends page 2 in place (32 rows, button gone)', (await page.$$('#record-picker .record-picker-row')).length === 32 && !(await page.$('#record-picker-more')));
await page.keyboard.press('Escape'); await page.waitForSelector('#record-picker', { state: 'hidden' });
ok('Escape closes the picker without choosing', (await page.inputValue(F)) === '');
// the form saves with the picked assignee
await page.fill('#issue-form-field-summary', 'Browser pass issue');
await page.click(F + '-open'); await page.waitForSelector('#record-picker .record-picker-row'); await page.type('#record-picker-search', 'person 05'); await page.waitForTimeout(600); await page.keyboard.press('Enter'); await page.waitForSelector('#record-picker', { state: 'hidden' });
await Promise.all([page.waitForURL(/\/issues\/PKA-\d+/), page.click('#issue-form-save-btn')]);
ok('the issue saved and shows its assignee in the details column', (await page.textContent('#issue-field-assignee-open')).trim() === 'Picker Person 05');
// change the assignee from the details column: the picker posts on change
await page.click('#issue-field-assignee-open'); await page.waitForSelector('#record-picker .record-picker-row'); await page.type('#record-picker-search', 'person 06'); await page.waitForTimeout(600); await page.keyboard.press('Enter'); await page.waitForSelector('#record-picker', { state: 'hidden' });
await page.waitForTimeout(800); await page.reload(); await page.waitForSelector('#issue-field-assignee-open');
ok('the details column assigns on pick (kept after a reload)', (await page.textContent('#issue-field-assignee-open')).trim() === 'Picker Person 06');
await page.screenshot({ path: S + '/3-issue-details.png' });
// the team settings: a required picker, posted by the Add button
await page.goto(B + '/projects/PKA/settings?tab=team'); await page.waitForSelector('#team-form-field-member-open');
await page.click('#team-form-field-member-open'); await page.waitForSelector('#record-picker .record-picker-row');
const more = (await page.textContent('.record-picker-more')).trim();
ok('the team picker lists only candidates (' + more + ')', /25 of 2\d shown/.test(more) && !(await page.$('#record-picker .record-picker-row[data-value="9001"]')));
await page.type('#record-picker-search', 'person 2'); await page.waitForTimeout(600);
const cand = await page.getAttribute('#record-picker .record-picker-row', 'data-label'); await page.click('#record-picker .record-picker-row'); await page.waitForSelector('#record-picker', { state: 'hidden' });
await page.click('#team-form-save-btn'); await page.waitForTimeout(1000); await page.goto(B + '/projects/PKA/settings?tab=team');
ok('Add posts the picked member (' + cand + ') to the team', (await page.textContent('#project-settings-team')).includes(cand));
await page.screenshot({ path: S + '/4-team-desktop.png' });
// phone
const phone = await browser.newContext({ viewport: { width: 375, height: 740 }, storageState: await ctx.storageState() });
const p2 = await phone.newPage(); p2.on('pageerror', e => errors.push(e.message));
await p2.goto(B + '/issues/new?project=PKA'); await p2.waitForSelector(F + '-open');
ok('no horizontal scroll at 375px', await p2.evaluate(() => document.documentElement.scrollWidth <= 375));
await p2.screenshot({ path: S + '/5-form-phone.png' });
await p2.click(F + '-open'); await p2.waitForSelector('#record-picker.show'); await p2.waitForSelector('#record-picker .record-picker-row');
await p2.type('#record-picker-search', 'pick'); await p2.waitForTimeout(600);
const box = await p2.evaluate(() => { const r = document.querySelector('#record-picker .modal-content').getBoundingClientRect(); return [r.width, r.height]; });
ok('the modal fills the phone screen (' + box.join('x') + ')', box[0] >= 374 && box[1] >= 700);
const rowH = await p2.evaluate(() => document.querySelector('#record-picker .record-picker-row').getBoundingClientRect().height);
ok('rows are touch targets (' + rowH + 'px)', rowH >= 44);
await p2.screenshot({ path: S + '/6-picker-phone.png' });
// the filters screen at 375: the project filter in the page header
await p2.keyboard.press('Escape'); await p2.waitForSelector('#record-picker', { state: 'hidden' });
await p2.goto(B + '/filters'); await p2.waitForSelector('.page-header-right-open-toggle'); await p2.click('.page-header-right-open-toggle'); await p2.waitForSelector('#filters-list-project-open', { state: 'visible' });
ok('no horizontal scroll on the saved filters at 375px', await p2.evaluate(() => document.documentElement.scrollWidth <= 375));
await p2.screenshot({ path: S + '/7-filters-phone.png' });
ok('no page errors', errors.length === 0);
if (errors.length) console.log('errors: ' + errors.join(' | '));
await browser.close();
