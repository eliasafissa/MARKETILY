// Maps admin-picked icon names (from IconPicker) to emoji.
// Kept in sync with the EMOJI_MAP inside IconPicker.tsx.

export const ICON_TO_EMOJI: Record<string, string> = {
  'gamepad-2':'🎮','zap':'⚡','shield':'🛡️','globe':'🌐','server':'🖥️','monitor':'🖥️',
  'credit-card':'💳','wallet':'💰','message-circle':'💬','messages-square':'💬','phone':'📞',
  'mail':'📧','user':'👤','users':'👥','star':'⭐','heart':'❤️','shopping-cart':'🛒',
  'bag':'👜','package':'📦','box':'📦','layers':'📚','grid':'⊞','layout':'⊟',
  'layout-grid':'⊞','image':'🖼️','camera':'📷','film':'🎬','video':'🎥','music':'🎵',
  'headphones':'🎧','mic':'🎤','bell':'🔔','bell-ring':'🔔','lock':'🔒','unlock':'🔓',
  'key':'🔑','shield-check':'✅','shield-alert':'⚠️','eye':'👁️','eye-off':'🙈',
  'search':'🔍','filter':'🔽','sliders':'⚙️','settings':'⚙️','tool':'🔧','wrench':'🔧',
  'code':'💻','terminal':'⬛','database':'🗄️','cloud':'☁️','cloud-upload':'☁️','download':'⬇️',
  'upload':'⬆️','share':'🔗','link':'🔗','link-2':'🔗','copy':'📋','clipboard':'📋',
  'bookmark':'🔖','tag':'🏷️','tags':'🏷️','flag':'🚩','book':'📖','book-open':'📖',
  'map':'🗺️','navigation':'🧭','map-pin':'📍','compass':'🧭','globe-2':'🌍',
  'wifi':'📶','wifi-off':'📵','rss':'📡','send':'📤','inbox':'📥','at-sign':'📧',
  'hash':'#','type':'Aa','bold':'𝐁','italic':'𝐼','underline':'U̲',
  'check-circle':'✅','check-square':'☑️','x-circle':'❌','alert-circle':'⚠️',
  'alert-triangle':'⚠️','info':'ℹ️','plus':'➕','minus':'➖','x':'✖️',
  'chevron-down':'⌄','chevron-right':'▶','arrow-down':'⬇️','arrow-up':'⬆️',
  'arrow-right':'➡️','arrow-left':'⬅️','external-link':'↗️','maximize':'⛶',
  'refresh':'🔄','rotate-cw':'🔄','loader':'⏳','clock':'🕐','calendar':'📅',
  'dollar-sign':'$','trending-up':'📈','trending-down':'📉','pie-chart':'📊',
  'bar-chart':'📊','activity':'📊','facebook':'📘','twitter':'🐦','instagram':'📷',
  'youtube':'▶️','twitch':'🎮','discord':'💬','telegram':'✈️','whatsapp':'💬',
  'apple':'🍎','windows':'🪟','android':'📱','globe-lock':'🔒','bot':'🤖',
  'cpu':'🖥️','hard-drive':'💾','smartphone':'📱','gem':'💎','crown':'👑',
  'award':'🏆','gift':'🎁','sparkles':'✨','rocket':'🚀','target':'🎯',
  'crosshair':'⊕',
};

export function iconToEmoji(icon?: string | null): string | null {
  if (!icon) return null;
  // If it's already an emoji (1-4 UTF-16 code units beyond ASCII), use as-is
  if (/[^\x00-\x7F]/.test(icon) && icon.length <= 4) return icon;
  return ICON_TO_EMOJI[icon] ?? null;
}
