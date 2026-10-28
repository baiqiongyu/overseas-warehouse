// 验证后台入库列表：托盘显示托数 + 详情弹窗显示客户单号（适配台账新样式）
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
  console.log('[1] 超管登录成功');

  const browser = await chromium.launch({ executablePath: EXE, headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 860 } });
  await page.goto(BASE + '/login.html');
  await page.evaluate(t => localStorage.setItem('hw_admin_token', t), token);

  await page.goto(BASE + '/inbound.html');
  await page.waitForSelector('.ledger .el-table__row', { timeout: 10000 });
  await page.waitForTimeout(900);

  // [2] 列表：托盘行应显示「N 托」
  const rows = await page.$$eval('.ledger .el-table__body tr', trs =>
    trs.filter(r => r.offsetParent !== null).map(r => ({
      no: r.querySelector('.ono .no') ? r.querySelector('.ono .no').textContent.trim() : '',
      ht: r.querySelector('.ht') ? r.querySelector('.ht').textContent.replace(/\s+/g, '').trim() : ''
    }))
  );
  const palletRows = rows.filter(r => r.ht.indexOf('托盘') === 0);
  console.log('[2] 托盘行:', JSON.stringify(palletRows));
  const withCount = palletRows.filter(r => /\d+托$/.test(r.ht));
  const withoutCount = palletRows.filter(r => !/\d+托$/.test(r.ht));
  console.log(withCount.length > 0 && withoutCount.length === 0
    ? '[PASS] 所有托盘行都显示了托数'
    : '[FAIL] 有托盘行未显示托数: ' + JSON.stringify(withoutCount));

  // [3] 详情弹窗：打开 IN202610220016（托盘 12 托 + 客户单号 TF-8899）
  const target = rows.find(r => r.no === 'IN202610220016') || rows[0];
  await page.click('.ledger .el-table__fixed-right .el-table__row:has-text("' + target.no + '") button:has-text("详情")');
  await page.waitForSelector('.el-dialog:visible .info-grid', { timeout: 8000 });
  await page.waitForTimeout(700);

  const headNo = await page.$eval('.sheet-head .no', e => e.textContent.trim());
  const headRef = await page.$eval('.sheet-head .ref', e => e.textContent.replace('客户单号', '').trim()).catch(() => '');
  const gridTexts = await page.$$eval('.el-dialog:visible .info-grid .ig', els =>
    els.map(e => e.querySelector('.k').textContent.trim() + '=' + e.querySelector('.v').textContent.trim()));
  console.log('[3] 详情头:', headNo, '| 客户单号:', headRef);
  console.log('[4] 信息区:', JSON.stringify(gridTexts));
  const ok = headNo === target.no && headRef === 'TF-8899'
    && gridTexts.some(t => t.replace(/\s+/g, '') === '到仓形态=托盘·12托');
  console.log(ok ? '[PASS] 详情含 单号 + 客户单号 + 托数' : '[FAIL] 详情字段异常');
  await page.screenshot({ path: SHOT + '_admin_inbound_ref_pallet.png' });

  await browser.close();
  console.log('DONE');
})().catch(e => { console.error('FAIL:', e.message); process.exit(1); });
