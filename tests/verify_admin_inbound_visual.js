// 视觉验收：入库台账新样式（布局、列宽、状态胶囊、详情弹窗）
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
  const page = await browser.newPage({ viewport: { width: 1440, height: 860 }, deviceScaleFactor: 1.4 });
  await page.goto(BASE + '/login.html');
  await page.evaluate(t => localStorage.setItem('hw_admin_token', t), token);
  await page.goto(BASE + '/inbound.html');
  await page.waitForSelector('.ledger .el-table__row', { timeout: 12000 });
  await page.waitForTimeout(900);
  await page.screenshot({ path: SHOT + '_ledger_list.png' });

  // 结构自检
  const info = await page.evaluate(() => {
    const q = s => document.querySelector(s);
    const rows = document.querySelectorAll('.ledger .el-table__body tr');
    const first = rows[0];
    return {
      tabs: document.querySelectorAll('.tab').length,
      tabActive: q('.tab.on') ? q('.tab.on').textContent.replace(/\s+/g, '').trim() : null,
      rows: rows.length,
      rowH: first ? Math.round(first.getBoundingClientRect().height) : 0,
      metrics: document.querySelectorAll('.metrics .m').length,
      stPills: document.querySelectorAll('.st').length,
      mono: getComputedStyle(q('.ono .no')).fontFamily,
      tableW: q('.ledger .el-table') ? Math.round(q('.ledger .el-table').getBoundingClientRect().width) : 0,
      bodyW: q('.ledger-body') ? Math.round(q('.ledger-body').getBoundingClientRect().width) : 0,
      hasHScroll: q('.ledger .el-table__body-wrapper') ? q('.ledger .el-table__body-wrapper').scrollWidth > q('.ledger .el-table__body-wrapper').clientWidth + 2 : null,
      emptyVisible: !!q('.empty'),
      dockVisible: !!q('.dock')
    };
  });
  console.log('[1] 结构:', JSON.stringify(info));

  // 列宽体检：各列实际渲染宽度 + 横向滚动
  const cols = await page.evaluate(() => {
    const ths = [...document.querySelectorAll('.ledger .el-table__header th')].filter(th => th.offsetParent !== null);
    const wrap = document.querySelector('.ledger .el-table__body-wrapper');
    return {
      widths: ths.map(th => {
        const lb = th.querySelector('.cell');
        return (lb ? lb.textContent.trim() : '').slice(0, 10) + ':' + Math.round(th.getBoundingClientRect().width);
      }),
      sum: Math.round(ths.reduce((a, th) => a + th.getBoundingClientRect().width, 0)),
      bodyW: wrap ? Math.round(wrap.clientWidth) : 0,
      hScroll: wrap ? wrap.scrollWidth - wrap.clientWidth : 0
    };
  });
  console.log('[1b] 列宽:', JSON.stringify(cols));

  // 状态页签切换
  await page.click('.tab:has-text("待审核")');
  await page.waitForTimeout(900);
  const afterTab = await page.evaluate(() => ({
    active: document.querySelector('.tab.on').textContent.replace(/\s+/g, '').trim(),
    rows: document.querySelectorAll('.ledger .el-table__body tr').length,
    hint: document.querySelector('.filters .tip') ? document.querySelector('.filters .tip').textContent.trim() : ''
  }));
  console.log('[2] 切到待审核:', JSON.stringify(afterTab));
  await page.screenshot({ path: SHOT + '_ledger_pending.png' });

  // 勾选 → 批量坞
  const boxes = await page.$$('.ledger .el-table__body .el-checkbox');
  if (boxes.length) {
    await boxes[0].click();
    await page.waitForTimeout(350);
    const dock = await page.$eval('.dock', e => e.textContent.replace(/\s+/g, ' ').trim()).catch(() => null);
    console.log('[3] 批量坞:', dock);
    await page.screenshot({ path: SHOT + '_ledger_dock.png' });
    await page.click('.dock .el-button:has-text("取消选择")');
  }

  // 回到全部 → 详情
  await page.click('.tab:has-text("全部")');
  await page.waitForTimeout(900);
  await page.click('.ledger .el-table__fixed-right .el-table__row:nth-child(1) button:has-text("详情")');
  await page.waitForSelector('.el-dialog:visible .info-grid', { timeout: 8000 });
  await page.waitForTimeout(700);
  await page.screenshot({ path: SHOT + '_ledger_detail.png' });
  const dlg = await page.evaluate(() => ({
    headNo: document.querySelector('.sheet-head .no').textContent.trim(),
    cells: document.querySelectorAll('.info-grid .ig').length,
    boxes: document.querySelectorAll('.dlg-scroll .box').length,
    secs: [...document.querySelectorAll('.sec-title .t')].map(e => e.textContent.trim())
  }));
  console.log('[4] 详情:', JSON.stringify(dlg));

  await browser.close();
  console.log('DONE');
})().catch(e => { console.error('FAIL:', e.message); process.exit(1); });
