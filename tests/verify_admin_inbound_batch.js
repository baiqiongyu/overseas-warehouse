// 验证后台入库列表：行复选框 + 批量审核
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

async function states(token) {
  const res = await fetch(API + '/inbound/list?limit=50', { headers: { token } });
  const j = await res.json();
  const m = {};
  (j.data || []).forEach(r => { m[r.order_no] = r.state; });
  return m;
}

(async () => {
  const token = await login('admin@hw.com', 'admin123456');
  console.log('[1] 超管登录成功');

  const browser = await chromium.launch({ executablePath: EXE, headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 860 } });
  await page.goto(BASE + '/login.html');
  await page.evaluate(t => localStorage.setItem('hw_admin_token', t), token);
  await page.goto(BASE + '/inbound.html');
  await page.waitForSelector('.inb-table .el-table__row', { timeout: 10000 });
  await page.waitForTimeout(800);

  // [2] 复选框列存在
  const hasSel = await page.$('.inb-table .el-table__header .el-checkbox') !== null;
  console.log(hasSel ? '[PASS] 表头有全选复选框' : '[FAIL] 表头没有复选框');
  const boxPerRow = await page.$eval('.inb-table .el-table__row:not([style*="display: none"])', r => !!r.querySelector('.el-checkbox'));
  console.log(boxPerRow ? '[PASS] 每行有复选框' : '[FAIL] 行内没有复选框');

  // [3] 勾选两张待审核单 → 批量条出现
  const pick = ['IN202610220021', 'IN202610220018'];
  // 元素复选框是左侧非固定列，直接点行内 .el-checkbox__original 的父 label
  for (const no of pick) {
    const row = page.locator('.inb-table .el-table__row', { hasText: no }).first();
    await row.locator('.el-checkbox').first().click();
    await page.waitForTimeout(150);
  }
  await page.waitForSelector('.dock', { timeout: 5000 });
  const barText = await page.$eval('.dock', e => e.textContent.replace(/\s+/g, ' ').trim());
  console.log('[3] 批量条:', barText);
  await page.screenshot({ path: SHOT + '_admin_inbound_select.png' });

  // 勾选一个草稿，验证跳过提示
  const draftRow = page.locator('.inb-table .el-table__row', { hasText: 'IN202610220017' }).first();
  await draftRow.locator('.el-checkbox').first().click();
  await page.waitForTimeout(200);
  const barText2 = await page.$eval('.dock', e => e.textContent.replace(/\s+/g, ' ').trim());
  console.log('[4] 混选后批量条:', barText2);

  // [5] 点批量审核 → 确认 → 检查状态流转
  await page.click('.dock .el-button:has-text("批量审核通过")');
  await page.waitForSelector('.el-message-box__wrapper:visible', { timeout: 5000 });
  const confirmText = await page.$eval('.el-message-box__message', e => e.textContent.trim());
  console.log('[5] 确认框文案:', confirmText);
  await page.click('.el-message-box__btns button.el-button--primary');
  await page.waitForSelector('.el-message--success', { timeout: 15000 });
  const toast = await page.$eval('.el-message--success', e => e.textContent.trim());
  console.log('[6] 结果提示:', toast);

  await page.waitForTimeout(1200);
  const st = await states(token);
  console.log('[7] 审核后状态:', pick.map(n => n + '=' + st[n]).join(', '), '| 草稿 017=' + st['IN202610220017']);
  const ok = st[pick[0]] === 30 && st[pick[1]] === 30 && st['IN202610220017'] === 10;
  console.log(ok ? '[PASS] 两张待审核 → 30，草稿未受影响' : '[FAIL] 状态流转异常');

  const barGone = await page.$('.dock') === null;
  console.log(barGone ? '[PASS] 批量条已收起（选中已清空）' : '[FAIL] 批量条未收起');

  await browser.close();
  console.log('DONE');
})().catch(e => { console.error('FAIL:', e.message); process.exit(1); });
