// 验证后台入库列表 / 详情：创建时间正常显示
const { chromium } = require('playwright');

const EXE = 'C:/Users/lijiwei/AppData/Local/ms-playwright/chromium-1217/chrome-win64/chrome.exe';
const API = 'http://127.0.0.1:8088/admin';
const BASE = 'http://127.0.0.1:8088/pages/admin';
const SHOT = 'C:/Users/lijiwei/Desktop/海外仓系统/warehouse/tests/';

async function login(email, password) {
  const res = await fetch(API + '/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'email=' + encodeURIComponent(email) + '&password=' + encodeURIComponent(password),
  });
  const j = await res.json();
  if (j.code !== 0) { throw new Error('登录失败: ' + j.msg); }
  return j.data.token;
}

(async () => {
  const token = await login('admin@hw.com', 'admin123456');
  const browser = await chromium.launch({ executablePath: EXE, headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 860 } });
  await page.goto(BASE + '/login.html');
  await page.evaluate(t => localStorage.setItem('hw_admin_token', t), token);
  await page.goto(BASE + '/inbound.html');
  await page.waitForSelector('.inb-table .el-table__row', { timeout: 10000 });
  await page.waitForTimeout(800);

  // 列索引取「创建时间」表头
  const colIdx = await page.$$eval('.inb-table .el-table__header th', ths =>
    ths.findIndex(th => th.textContent.trim() === '创建时间'));
  console.log('[1] 创建时间列索引:', colIdx);

  const times = await page.$$eval('.inb-table .el-table__body tr', (trs, i) => {
    return trs.filter(tr => tr.offsetParent !== null)
      .map(tr => tr.children[i] ? tr.children[i].textContent.trim() : '')
      .slice(0, 6);
  }, colIdx);
  console.log('[2] 前 6 行创建时间:', JSON.stringify(times));
  const bad = times.filter(t => !/^\d{2}-\d{2}\s*\d{2}:\d{2}$/.test(t));
  console.log(bad.length === 0 ? '[PASS] 列表创建时间全部正常' : '[FAIL] 异常值: ' + JSON.stringify(bad));

  // 详情弹窗创建时间
  await page.click('.inb-table .el-table__fixed-right .el-table__row:nth-child(1) button:has-text("详情")');
  await page.waitForSelector('.el-dialog:visible .info-grid', { timeout: 8000 });
  await page.waitForTimeout(600);
  const grid = await page.$$eval('.el-dialog:visible .info-grid .ig', els =>
    els.map(e => e.querySelector('.k').textContent.trim() + '=' + e.querySelector('.v').textContent.trim()));
  console.log('[3] 详情:', JSON.stringify(grid));
  const createItem = grid.find(t => t.indexOf('创建时间=') === 0) || '';
  console.log(/创建时间=\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(createItem.replace(/\s+/g, ' '))
    ? '[PASS] 详情创建时间正常: ' + createItem : '[FAIL] 详情创建时间仍异常: ' + createItem);

  await page.screenshot({ path: SHOT + '_admin_inbound_time.png' });
  await browser.close();
  console.log('DONE');
})().catch(e => { console.error('FAIL:', e.message); process.exit(1); });
