require('dotenv').config();
const { Telegraf, Markup } = require('telegraf');
const mysql = require('mysql2/promise');
const OpenAI = require('openai');

const openai = new OpenAI({ 
  apiKey: process.env.GROQ_API_KEY,
  baseURL: 'https://api.groq.com/openai/v1'
});

const bot = new Telegraf(process.env.BOT_TOKEN);
const webAppUrl = process.env.WEB_APP_URL;
const adminId = process.env.ADMIN_CHAT_ID || '7716104719';

const dbConfig = {
  host: process.env.DB_HOST, user: process.env.DB_USER, password: process.env.DB_PASS, database: process.env.DB_NAME
};

let isBroadcastMode = false;

// دالة لإنشاء الأزرار المدمجة (Inline) للعميل
const getUserMenu = () => {
  return Markup.inlineKeyboard([
    [Markup.button.webApp('🛍️ تسوق الآن', webAppUrl)],
    [Markup.button.webApp('📦 طلباتي', `${webAppUrl}/dashboard/orders`), Markup.button.callback('💰 سجل إيداعاتي', 'my_deposits')],
    [Markup.button.callback('💬 الدعم الفني', 'support'), Markup.button.callback('ℹ️ المساعدة', 'help')]
  ]);
};

// دالة لإنشاء الأزرار المدمجة للأدمن
const getAdminMenu = () => {
  return Markup.inlineKeyboard([
    [Markup.button.webApp('🛍️ تسوق الآن', webAppUrl)],
    [Markup.button.callback('💰 الإيداعات المعلقة', 'admin_deposits'), Markup.button.callback('📦 آخر الطلبات', 'admin_orders')],
    [Markup.button.callback('📊 إحصائيات المتجر', 'admin_stats'), Markup.button.callback('📢 إرسال إذاعة', 'admin_broadcast')],
    [Markup.button.webApp('📦 طلباتي', `${webAppUrl}/dashboard/orders`)]
  ]);
};

bot.start((ctx) => {
  const isAdmin = ctx.from.id.toString() === adminId.toString();
  const text = `أهلاً بك في متجر Marketlly! 🛒\n\nاستخدم الأزرار أدناه للتنقل، أو اكتب سؤالك وسأجيبك فوراً.`;
  ctx.reply(text, isAdmin ? getAdminMenu() : getUserMenu());
});

// --- معالجة أزرار العميل ---
bot.action('my_deposits', async (ctx) => {
  await ctx.answerCbQuery();
  const tgId = ctx.from.id;
  const tgEmail = `tg${tgId}@marketlly.store`;
  try {
    const connection = await mysql.createConnection(dbConfig);
    const [rows] = await connection.execute(`
      SELECT t.id, t.amount, t.method, t.status, t.created_at 
      FROM transactions t JOIN users u ON t.user_id = u.id 
      WHERE u.email = ? AND t.type = 'deposit' ORDER BY t.id DESC LIMIT 5
    `, [tgEmail]);
    await connection.end();

    if (rows.length === 0) return ctx.reply('📭 ليس لديك أي سجل إيداعات حتى الآن.');

    let text = '💰 *سجل إيداعاتك الأخيرة:*\n\n';
    const statusMap = { 'pending': '⏳ قيد المراجعة', 'approved': '✅ مقبول', 'rejected': '❌ مرفوض', 'completed': '✅ مكتمل' };
    rows.forEach(dep => {
      const date = new Date(dep.created_at).toLocaleDateString('ar-SY');
      text += `#${dep.id} - $${Number(dep.amount).toFixed(2)} - ${statusMap[dep.status] || dep.status} - ${date}\n`;
    });
    ctx.reply(text, { parse_mode: 'Markdown' });
  } catch (err) { ctx.reply('❌ حدث خطأ.'); }
});

bot.action('support', (ctx) => {
  ctx.answerCbQuery();
  ctx.reply('💬 اكتب رسالتك الآن، وسيقوم الذكاء الاصطناعي أو فريق الدعم بالرد عليك.');
});

bot.action('help', (ctx) => {
  ctx.answerCbQuery();
  ctx.reply('مرحباً بك. يمكنك الشراء من زر (تسوق الآن)، أو مراسلة الدعم.');
});

// --- معالجة أزرار الأدمن ---
bot.action('admin_deposits', async (ctx) => {
  if (ctx.from.id.toString() !== adminId.toString()) return ctx.answerCbQuery('❌ غير مصرح لك.');
  await ctx.answerCbQuery();
  ctx.reply('⏳ جاري جلب الإيداعات...');
  try {
    const connection = await mysql.createConnection(dbConfig);
    const [rows] = await connection.execute(`
      SELECT t.id, t.amount, t.method, u.name as user_name 
      FROM transactions t JOIN users u ON t.user_id = u.id 
      WHERE t.type = 'deposit' AND t.status = 'pending' ORDER BY t.id DESC LIMIT 10
    `);
    await connection.end();
    if (rows.length === 0) return ctx.reply('✅ لا توجد إيداعات معلقة حالياً.');

    for (const row of rows) {
      const text = `💰 *إيداع جديد #${row.id}*\n\n👤 العميل: ${row.user_name}\n💵 المبلغ: $${Number(row.amount).toFixed(2)}\n💳 الطريقة: ${row.method}`;
      const buttons = Markup.inlineKeyboard([
        Markup.button.callback('✅ موافقة', `approve_dep_${row.id}`),
        Markup.button.callback('❌ رفض', `reject_dep_${row.id}`)
      ]);
      ctx.reply(text, { parse_mode: 'Markdown', ...buttons });
    }
  } catch (err) { ctx.reply('❌ حدث خطأ.'); }
});

bot.action('admin_orders', async (ctx) => {
  if (ctx.from.id.toString() !== adminId.toString()) return ctx.answerCbQuery('❌ غير مصرح لك.');
  await ctx.answerCbQuery();
  ctx.reply('⏳ جاري جلب الطلبات...');
  try {
    const connection = await mysql.createConnection(dbConfig);
    const [rows] = await connection.execute(`
      SELECT o.id, o.total, o.status, o.created_at, u.name as user_name 
      FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 10
    `);
    await connection.end();
    if (rows.length === 0) return ctx.reply('لا توجد طلبات بعد.');
    let text = '📦 *آخر 10 طلبات:*\n\n';
    rows.forEach(order => {
      const date = new Date(order.created_at).toLocaleDateString('ar-SY');
      text += `#${order.id} - ${order.user_name} | $${Number(order.total).toFixed(2)} | ${order.status} | ${date}\n`;
    });
    ctx.reply(text, { parse_mode: 'Markdown' });
  } catch (err) { ctx.reply('❌ حدث خطأ.'); }
});

bot.action('admin_stats', async (ctx) => {
  if (ctx.from.id.toString() !== adminId.toString()) return ctx.answerCbQuery('❌ غير مصرح لك.');
  await ctx.answerCbQuery();
  ctx.reply('⏳ جاري جلب الإحصائيات...');
  try {
    const connection = await mysql.createConnection(dbConfig);
    const [ordersToday] = await connection.execute('SELECT COUNT(*) as count, SUM(total) as sum FROM orders WHERE DATE(created_at) = CURDATE()');
    const [totalUsers] = await connection.execute('SELECT COUNT(*) as count FROM users');
    const [totalOrders] = await connection.execute('SELECT COUNT(*) as count FROM orders');
    await connection.end();
    const text = `📊 *إحصائيات اليوم:*\n\n🛒 طلبات اليوم: ${ordersToday[0].count || 0}\n💰 مبيعات اليوم: $${(Number(ordersToday[0].sum) || 0).toFixed(2)}\n\n👥 المستخدمين: ${totalUsers[0].count || 0}\n📦 إجمالي الطلبات: ${totalOrders[0].count || 0}`;
    ctx.reply(text, { parse_mode: 'Markdown' });
  } catch (err) { ctx.reply('❌ حدث خطأ.'); }
});

bot.action('admin_broadcast', (ctx) => {
  if (ctx.from.id.toString() !== adminId.toString()) return ctx.answerCbQuery('❌ غير مصرح لك.');
  ctx.answerCbQuery();
  isBroadcastMode = true;
  ctx.reply('📣 *وضع الإذاعة مفعل.*\n\nأرسل الآن الرسالة التي تريد إرسالها لجميع العملاء.\n\nلإلغاء العملية، اكتب كلمة "إلغاء".', { parse_mode: 'Markdown' });
});

bot.action(/^approve_dep_(\d+)$/, async (ctx) => {
  if (ctx.from.id.toString() !== adminId.toString()) return ctx.answerCbQuery('❌ غير مصرح لك.');
  const txId = ctx.match[1];
  try {
    const connection = await mysql.createConnection(dbConfig);
    await connection.beginTransaction();
    const [tx] = await connection.execute('SELECT amount, user_id, status FROM transactions WHERE id = ? FOR UPDATE', [txId]);
    if (tx.length === 0 || tx[0].status !== 'pending') {
      await connection.rollback(); await connection.end();
      return ctx.editMessageText('⚠️ هذا الإيداع تمت معالجته مسبقاً.');
    }
    const { amount, user_id } = tx[0];
    await connection.execute('UPDATE transactions SET status = ? WHERE id = ?', ['approved', txId]);
    await connection.execute('UPDATE users SET balance = balance + ? WHERE id = ?', [amount, user_id]);
    await connection.commit(); await connection.end();
    await ctx.editMessageText(`✅ تمت الموافقة على الإيداع #${txId} وإضافة $${Number(amount).toFixed(2)} لرصيد العميل.`);
  } catch (err) { ctx.answerCbQuery('❌ حدث خطأ.'); }
});

bot.action(/^reject_dep_(\d+)$/, async (ctx) => {
  if (ctx.from.id.toString() !== adminId.toString()) return ctx.answerCbQuery('❌ غير مصرح لك.');
  const txId = ctx.match[1];
  try {
    const connection = await mysql.createConnection(dbConfig);
    await connection.execute('UPDATE transactions SET status = ?, rejection_reason = ? WHERE id = ?', ['rejected', 'مرفوض من قبل الإدارة', txId]);
    await connection.end();
    await ctx.editMessageText(`❌ تم رفض الإيداع #${txId}.`);
  } catch (err) { ctx.answerCbQuery('❌ حدث خطأ.'); }
});

// --- الدردشة الحية والذكاء الاصطناعي ---
bot.on('message', async (ctx) => {
  const message = ctx.message;
  const fromId = ctx.from.id;
  const text = message.text;

  // وضع الإذاعة
  if (isBroadcastMode && fromId.toString() === adminId.toString()) {
    if (text === 'إلغاء') {
      isBroadcastMode = false;
      return ctx.reply('❌ تم إلغاء الإذاعة.');
    }
    isBroadcastMode = false;
    ctx.reply('⏳ جاري بدء الإرسال...');
    try {
      const connection = await mysql.createConnection(dbConfig);
      const [rows] = await connection.execute("SELECT email FROM users WHERE email LIKE 'tg%@marketlly.store'");
      await connection.end();
      const tgIds = rows.map(r => r.email.replace('tg', '').replace('@marketlly.store', ''));
      let success = 0, fail = 0;
      for (const id of tgIds) {
        try {
          await bot.telegram.copyMessage(id, ctx.chat.id, message.message_id);
          success++;
          await new Promise(resolve => setTimeout(resolve, 50));
        } catch (e) { fail++; }
      }
      ctx.reply(`✅ *تمت الإذاعة بنجاح!*\n\n👥 تم الإرسال إلى: ${success} عميل.\n❌ فشل الإرسال إلى: ${fail} عميل.`, { parse_mode: 'Markdown' });
    } catch (err) { ctx.reply('❌ حدث خطأ أثناء الإذاعة.'); }
    return;
  }

  if (text && text.startsWith('/')) return;

  // رد الأدمن على العملاء (Live Chat)
  if (fromId.toString() === adminId.toString() && message.reply_to_message) {
    const repliedMsg = message.reply_to_message;
    if (repliedMsg.text && repliedMsg.text.includes('[ID:')) {
      const match = repliedMsg.text.match(/\[ID:\s*(\d+)\]/);
      if (match) {
        try {
          await bot.telegram.copyMessage(match[1], ctx.chat.id, message.message_id);
          ctx.reply('✅ تم إرسال ردك للعميل.');
        } catch (err) { ctx.reply('❌ فشل إرسال الرد.'); }
      }
    }
    return;
  }

  // الذكاء الاصطناعي للعملاء
  if (fromId.toString() !== adminId.toString() && text) {
    try {
      await ctx.sendChatAction('typing');
      const response = await openai.chat.completions.create({
        model: "openai/gpt-oss-20b",
        messages: [
          { role: "system", content: "أنت مساعد الدعم الفني لمتجر Marketlly (متجر رقمي لبيع شدات الألعاب والخدمات الرقمية). أجب باللغة العربية بأسلوب ودود ومختصر جداً. طرق الدفع: شام كاش، سيرياتيل، باينانس، و USDT. الموقع: www.marketlly.store. إذا سأل العميل عن طلبه، اعتذر بلطف وقل له أن الإدارة سترد عليه قريباً." },
          { role: "user", content: text }
        ],
        max_tokens: 150,
      });
      const aiReply = response.choices[0].message.content;
      await ctx.reply(aiReply, Markup.inlineKeyboard([
        Markup.button.callback('👨‍💻 تحدث مع الإدارة', 'human_support')
      ]));
    } catch (err) {
      console.error("AI Error:", err);
      ctx.reply('عذراً، حدث خطأ. هل تريد التواصل مع الدعم الفني؟', Markup.inlineKeyboard([
        Markup.button.callback('👨‍💻 تحدث مع الإدارة', 'human_support')
      ]));
    }
  }
});

bot.action('human_support', async (ctx) => {
  await ctx.answerCbQuery();
  ctx.reply('جاري تحويلك إلى الدعم البشري، يرجى كتابة رسالتك الآن وسنرد عليك في أقرب وقت.');
  const fromId = ctx.from.id;
  await bot.telegram.sendMessage(adminId, 
    `🚨 *طلب دعم بشري جديد!*\n\n[ID: ${fromId}]\nالاسم: ${ctx.from.first_name}\nيرجى الرد على رسالته القادمة.`, 
    { parse_mode: 'Markdown', reply_markup: { force_reply: true } }
  );
});

bot.launch().then(() => console.log('🤖 Bot with Inline UI is running!'));
process.once('SIGINT', () => bot.stop('SIGINT'));
process.once('SIGTERM', () => bot.stop('SIGTERM'));