// Picks a category-appropriate emoji from a category or product name.
// Order matters: first match wins. Add new keywords at the top of each group.

const RULES: Array<{ emoji: string; patterns: RegExp }> = [
  // Streaming & video
  { emoji: '🎬', patterns: /netflix|شاهد|shahid|osn|prime\s*video|disney|hulu|ستارزبلاي|starzplay|فيلم|movie|streaming/i },
  { emoji: '📺', patterns: /live|لايف|بث\s*مباشر|بيغو|bigo|poppo|mico|yalla\s*live|streamkar|tango|nonolive/i },
  { emoji: '🎵', patterns: /spotify|anghami|أنغامي|music|موسيقى|deezer|soundcloud|apple\s*music/i },
  { emoji: '🎥', patterns: /capcut|canva|ادوبي|adobe|premiere|after\s*effect|filmora|video\s*edit/i },

  // Games
  { emoji: '💎', patterns: /جواهر|jewel|diamond|diamonds|gem|شدات|شدة|شحن\s*جوا|free\s*fire|فري\s*فاير|pubg|ببجي|بابجي|mobile\s*legend|موبايل\s*ليجند|fortnite|فورتنايت|roblox|روبلوكس|genshin|جينشين|valorant|فالورانت|call\s*of\s*duty|كول\s*اوف\s*ديوتي|clash|كلاش|8ball|8\s*ball|بلياردو|ludo|لودو|jawaker|جواكر|yalla\s*ludo/i },
  { emoji: '🎮', patterns: /game|العاب|لعبة|gaming|playstation|بلايستيشن|\bPS[345]\b|xbox|اكس\s*بوكس|nintendo|steam|ستيم|epic\s*games|riot|riot\s*games|blizzard|ubisoft|ea\s*play|battle\.net|قيمينق/i },

  // Messaging & chat
  { emoji: '💬', patterns: /chat|شات|محادثة|whatsapp|واتس|telegram|تليجرام|signal|wechat|و\s*تشات|messenger|ماسنجر|discord|ديسكورد|line|viber/i },
  { emoji: '📷', patterns: /instagram|انستقرام|انستغرام|snapchat|سناب|snap|tiktok|تيك\s*توك|tik\s*tok|twitter|تويتر|threads|ثريدز|facebook|فيسبوك/i },

  // Social boost / growth
  { emoji: '👍', patterns: /like|لايك|likes|boost|متابع|followers|following|مشاهد|views|subscriber|مشترك|رشق|تعليق|comment/i },

  // Telecom / SIM top-up
  { emoji: '📱', patterns: /zain|زين|mobily|موبايلي|stc|إس\s*تي\s*سي|etisalat|اتصالات|vodafone|فودافون|orange|أورنج|syriatel|سيريتل|mtn|truke?ll|شحن\s*رصيد|topup|top\s*up/i },

  // Wallets / currency / codes
  { emoji: '💰', patterns: /رصيد|balance|credit|wallet|محفظة|paypal|باي\s*بال|binance|باينانس|usdt|تحويل|currency|عملة|dollar|دولار|ليرة|ريال|درهم|دينار/i },
  { emoji: '💳', patterns: /visa|فيزا|master|ماستر|credit\s*card|بطاقة|card\s*code|كود\s*شحن|gift\s*card|قيفت\s*كارد/i },

  // Streaming entertainment cards
  { emoji: '🎁', patterns: /gift|هدية|قيفت|بطاقة|reward|مكافأة/i },

  // VPN / security
  { emoji: '🔒', patterns: /vpn|في\s*بي\s*ان|حماية|security|أمان|antivirus|نورتون|norton|kaspersky|كاسبر/i },

  // Software & apps
  { emoji: '💻', patterns: /windows|ويندوز|office|أوفيس|microsoft|مايكروسوفت|google\s*play|جوجل\s*بلاي|itunes|ايتونز|app\s*store|appstore|software|برنامج|canva|figma|figm|chatgpt|openai|midjourney|cursor|copilot|gemini/i },

  // Education / courses
  { emoji: '📚', patterns: /course|كورس|دورة|lesson|تعلم|learning|udemy|edx|coursera|كتاب|book/i },

  // Food / delivery
  { emoji: '🍔', patterns: /talabat|طلبات|food|طعام|restaurant|مطعم|delivery|توصيل|هنقر|hunger/i },

  // Default
  { emoji: '🛍️', patterns: /.*/ },
];

export function categoryEmoji(name?: string | null): string {
  const text = (name ?? '').trim();
  if (!text) return '🛍️';
  for (const { emoji, patterns } of RULES) {
    if (patterns.test(text)) return emoji;
  }
  return '🛍️';
}
