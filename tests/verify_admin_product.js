// 验证后台产品模块 UI（顶部模块出现 + 所属公司列 + 公司筛选 + 建档弹窗）
const { chromium } = require('playwright');

const EXE = 'C:/Users/lijiwei/AppData/Local/ms-playwright/chromium-1217/chrome-win64/chrome.exe';
const API = 'http://127.0.0.1:8088/admin';
const BASE = 'http://127.0.0.1:8088/pages/admin';

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
  page.on('pageerror', e => console.log('[PAGE ERROR]', e.message));

  await page.goto(BASE + '/login.html');
  await page.evaluate(t => localStorage.setItem('hw_admin_token', t), token);
  await page.goto(BASE + '/index.html');
  await page.waitForSelector('.mnav a', { timeout: 8000 });
  await page.waitForTimeout(600);

  const tabs = await page.$$eval('.mnav a', as => as.map(a => a.textContent.trim()));
  console.log('[1] 顶部模块:', JSON.stringify(tabs));

  // 点产品管理
  await page.click('.mnav a:has-text("产品管理")');
  await page.waitForSelector('.main iframe[src="product.html"]', { timeout: 8000 });
  const frame = page.frames().find(f => f.url().includes('product.html'));
  await frame.waitForSelector('.sku-table', { timeout: 8000 });
  await page.waitForTimeout(800);

  const headers = await frame.$$eval('.sku-table th', ths => ths.map(t => t.textContent.trim()).filter(Boolean));
  console.log('[2] 表头:', JSON.stringify(headers));

  const coCell = await frame.$eval('.co-tag', el => el.textContent.trim()).catch(() => '(无)');
  const coCode = await frame.$eval('.co-code', el => el.textContent.trim()).catch(() => '');
  console.log('[3] 首行所属公司:', coCell, '|', coCode);

  // 公司筛选
  const coCount = await frame.$$eval('.co-tag', els => els.length);
  console.log('[4] 当前页行数(公司标签):', coCount);

  // 建档弹窗：所属公司必选
  await frame.click('button.btn-primary');
  await frame.waitForSelector('.el-dialog', { timeout: 5000 });
  const dlgTitle = await frame.$eval('.el-dialog__title', el => el.textContent.trim());
  console.log('[5] 弹窗:', dlgTitle);
  await page.screenshot({ path: 'C:/Users/lijiwei/Desktop/海外仓系统/warehouse/tests/_product_create.png' });
  await frame.click('.el-dialog__footer .el-button');
  await page.waitForTimeout(300);

  // 收货员：顶栏不应出现产品管理
  const token2 = await login('receiver@hw.com', 'receiver123456');
  const p2 = await browser.newPage({ viewport: { width: 1440, height: 860 } });
  await p2.goto(BASE + '/login.html');
  await p2.evaluate(t => localStorage.setItem('hw_admin_token', t), token2);
  await p2.goto(BASE + '/index.html');
  await p2.waitForSelector('.mnav a', { timeout: 8000 });
  await p2.waitForTimeout(600);
  const tabs2 = await p2.$$eval('.mnav a', as => as.map(a => a.textContent.trim()));
  console.log('[6] 收货员顶部模块:', JSON.stringify(tabs2), '| 含产品管理:', tabs2.some(t => t.includes('产品')));

  await browser.close();
  console.log('DONE');
})().catch(e => { console.error('FAIL:', e.message); process.exit(1); });
