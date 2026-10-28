// 验证后台「顶部模块导航 + 左侧栏目」布局
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
  console.log('[1] 超管登录成功');

  const browser = await chromium.launch({ executablePath: EXE, headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 860 } });

  // 先注入 token 再进首页
  await page.goto(BASE + '/login.html');
  await page.evaluate(t => localStorage.setItem('hw_admin_token', t), token);
  await page.goto(BASE + '/index.html');
  await page.waitForSelector('.mnav a', { timeout: 8000 });
  await page.waitForTimeout(600);

  // 顶部模块清单
  const tabs = await page.$$eval('.mnav a', as => as.map(a => a.textContent.trim()));
  console.log('[2] 顶部模块:', JSON.stringify(tabs));

  // 默认应落在第一个模块（工作台→首页概览）
  const activeTab1 = await page.$eval('.mnav a.active', a => a.textContent.trim());
  const side1 = await page.$$eval('.sidebar a', as => as.map(a => a.textContent.trim()));
  const src1 = await page.$eval('.main iframe', f => f.getAttribute('src'));
  console.log('[3] 默认高亮模块:', activeTab1, '| 左侧栏目:', JSON.stringify(side1), '| iframe:', src1);
  await page.screenshot({ path: 'C:/Users/lijiwei/Desktop/海外仓系统/warehouse/tests/_layout_worktop.png' });

  // 点击「入库管理」
  await page.click('.mnav a:has-text("入库管理")');
  await page.waitForTimeout(300);
  const side2 = await page.$$eval('.sidebar a', as => as.map(a => a.textContent.trim()));
  const src2 = await page.$eval('.main iframe', f => f.getAttribute('src'));
  console.log('[4] 切到入库管理 | 左侧栏目:', JSON.stringify(side2), '| iframe:', src2);

  // 点击「系统设置」→ 左侧应出现 员工/角色/菜单管理；再点左侧「角色管理」
  await page.click('.mnav a:has-text("系统设置")');
  await page.waitForTimeout(300);
  const side3 = await page.$$eval('.sidebar a', as => as.map(a => a.textContent.trim()));
  const src3 = await page.$eval('.main iframe', f => f.getAttribute('src'));
  console.log('[5] 切到系统设置 | 左侧栏目:', JSON.stringify(side3), '| iframe:', src3);

  await page.click('.sidebar a:has-text("角色管理")');
  await page.waitForTimeout(400);
  const src4 = await page.$eval('.main iframe', f => f.getAttribute('src'));
  const activeSide = await page.$eval('.sidebar a.active', a => a.textContent.trim());
  console.log('[6] 点左侧角色管理 | 高亮:', activeSide, '| iframe:', src4);
  await page.screenshot({ path: 'C:/Users/lijiwei/Desktop/海外仓系统/warehouse/tests/_layout_settings.png' });

  // 刷新后恢复上次页面 + 模块高亮
  await page.reload();
  await page.waitForSelector('.mnav a.active', { timeout: 8000 });
  await page.waitForTimeout(600);
  const activeTab2 = await page.$eval('.mnav a.active', a => a.textContent.trim());
  const activeSide2 = await page.$eval('.sidebar a.active', a => a.textContent.trim());
  const src5 = await page.$eval('.main iframe', f => f.getAttribute('src'));
  console.log('[7] 刷新恢复 | 模块高亮:', activeTab2, '| 栏目高亮:', activeSide2, '| iframe:', src5);

  // 收货员角色：顶部应只有 工作台 + 入库管理
  const token2 = await login('receiver@hw.com', 'receiver123456').catch(() => null);
  if (token2) {
    const p2 = await browser.newPage({ viewport: { width: 1440, height: 860 } });
    await p2.goto(BASE + '/login.html');
    await p2.evaluate(t => localStorage.setItem('hw_admin_token', t), token2);
    await p2.goto(BASE + '/index.html');
    await p2.waitForSelector('.mnav a', { timeout: 8000 });
    await p2.waitForTimeout(600);
    const tabs2 = await p2.$$eval('.mnav a', as => as.map(a => a.textContent.trim()));
    const sideR = await p2.$$eval('.sidebar a', as => as.map(a => a.textContent.trim()));
    console.log('[8] 收货员视角 | 顶部模块:', JSON.stringify(tabs2), '| 左侧栏目:', JSON.stringify(sideR));
    await p2.screenshot({ path: 'C:/Users/lijiwei/Desktop/海外仓系统/warehouse/tests/_layout_receiver.png' });
    await p2.close();
  } else {
    console.log('[8] 收货员账号不可用，跳过');
  }

  await browser.close();
  console.log('DONE');
})().catch(e => { console.error('FAIL:', e.message); process.exit(1); });
