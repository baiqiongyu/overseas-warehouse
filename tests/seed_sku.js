// 造 20 条仿真 SKU（走 /admin/product/create）
const API = 'http://127.0.0.1:8088/admin';

const IMGS = [
  '/storage/sku/20261021/47e5dba7f1d089e5fad950042d9aca66.png',
  '/storage/sku/20261021/4931dcb3bad1bd6bb8f74aff0aaf018e.jpg',
  '/storage/sku/20261021/327c9de7fd8769650b1c7178b35697d2.jpg',
];

// [所属公司id, sku_code, 中文名, 英文名, 颜色, 重量kg, 长, 宽, 高, 申报价值$, 海关编码, attrs]
const DATA = [
  [1, 'SK-500', '不锈钢保温壶', 'Vacuum Flask 1.5L', '银色', 0.850, 28.00, 12.00, 12.00, 8.50, '9616.60', { liquid: true }],
  [1, 'SK-501', '陶瓷马克杯', 'Ceramic Mug 350ml', '白色', 0.320, 11.00, 9.00, 10.00, 3.20, '6912.00', { fragile: true }],
  [1, 'SK-502', '无线充电板', 'Wireless Charger Pad', '黑色', 0.180, 15.00, 10.00, 1.20, 9.90, '8504.40', { battery: true }],
  [1, 'SK-503', '帆布收纳篮', 'Canvas Storage Basket', '米色', 0.260, 25.00, 20.00, 18.00, 4.60, '6307.90', {}],
  [1, 'SK-504', '硅胶餐垫', 'Silicone Placemat', '灰色', 0.150, 40.00, 30.00, 0.50, 2.80, '3919.10', {}],

  [3, 'AD-100', '蓝牙音箱', 'Bluetooth Speaker', '深灰', 0.540, 18.00, 8.00, 8.00, 21.00, '8518.21', { battery: true }],
  [3, 'AD-101', '电动牙刷', 'Electric Toothbrush', '白色', 0.130, 22.00, 3.00, 3.00, 15.50, '8509.80', { battery: true, liquid: true }],
  [3, 'AD-102', '保温饭盒', 'Thermal Lunch Box', '墨绿', 0.620, 21.00, 14.00, 14.00, 12.80, '9617.00', { liquid: true }],
  [3, 'AD-103', '手机支架', 'Phone Stand', '铝合金', 0.090, 12.00, 8.00, 9.00, 3.90, '7615.10', {}],
  [3, 'AD-104', '化妆刷套装', 'Makeup Brush Set', '粉色', 0.170, 19.00, 9.00, 3.50, 7.20, '9603.30', {}],
  [3, 'AD-105', '迷你加湿器', 'Mini Humidifier', '白色', 0.280, 12.00, 12.00, 16.00, 10.50, '8479.89', { liquid: true, battery: true }],
  [3, 'AD-106', '瑜伽弹力带', 'Resistance Band Set', '多色', 0.340, 20.00, 15.00, 4.00, 6.60, '9506.91', {}],

  [4, 'IB-200', 'LED 台灯', 'LED Desk Lamp', '黑色', 0.710, 35.00, 14.00, 40.00, 18.00, '9405.20', { battery: true }],
  [4, 'IB-201', '厨房电子秤', 'Kitchen Scale', '不锈钢色', 0.410, 20.00, 15.00, 4.00, 8.80, '8423.90', { battery: true }],
  [4, 'IB-202', '儿童水杯', 'Kids Water Bottle', '蓝色', 0.230, 18.00, 7.00, 7.00, 5.40, '3924.10', { liquid: true }],
  [4, 'IB-203', '宠物食盆', 'Pet Feeding Bowl', '不锈钢', 0.380, 22.00, 15.00, 6.00, 6.90, '7323.93', {}],
  [4, 'IB-204', '香薰蜡烛', 'Scented Candle', '琥珀色', 0.460, 10.00, 10.00, 12.00, 11.20, '3406.00', { liquid: true, fragile: true }],
  [4, 'IB-205', '笔记本电脑包', 'Laptop Sleeve 14"', '深蓝', 0.330, 38.00, 27.00, 3.00, 9.60, '4202.12', {}],
  [4, 'IB-206', '桌面收纳盒', 'Desk Organizer', '原木色', 0.520, 22.00, 14.00, 11.00, 7.70, '4419.19', {}],
  [4, 'IB-207', '颈枕', 'Memory Foam Neck Pillow', '浅灰', 0.290, 28.00, 22.00, 12.00, 8.10, '9404.90', {}],
];

(async () => {
  const res = await fetch(API + '/auth/login', {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'email=admin%40hw.com&password=admin123456' });
  const token = (await res.json()).data.token;

  let ok = 0, fail = 0;
  for (const d of DATA) {
    const body = {
      customer_id: d[0], sku_code: d[1], name_cn: d[2], name_en: d[3], color: d[4],
      weight: d[5], length: d[6], width: d[7], height: d[8], declare_value: d[9],
      hs_code: d[10], image: IMGS[ok % IMGS.length], attrs: d[11],
      desc_cn: d[2] + '，出口常规款。', desc_en: d[3] + ' for export.',
    };
    const j = await fetch(API + '/product/create', {
      method: 'POST', headers: { 'Content-Type': 'application/json', token: token },
      body: JSON.stringify(body)
    }).then(r => r.json());
    if (j.code === 0) { ok++; console.log('OK  ', d[1], j.data.barcode); }
    else { fail++; console.log('FAIL', d[1], j.msg); }
  }
  console.log('完成：成功 ' + ok + '，失败 ' + fail);
})().catch(e => { console.error(e); process.exit(1); });
