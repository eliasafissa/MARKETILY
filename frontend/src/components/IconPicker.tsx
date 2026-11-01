import { useState, useMemo } from 'react';

const ICON_NAMES = [
  'gamepad-2','zap','shield','globe','server','monitor','credit-card','wallet',
  'message-circle','messages-square','phone','mail','user','users','star','heart',
  'shopping-cart','bag','package','box','layers','grid','layout','layout-grid',
  'image','camera','film','video','music','headphones','mic','bell','bell-ring',
  'lock','unlock','key','shield-check','shield-alert','eye','eye-off',
  'search','filter','sliders','settings','tool','wrench','code','terminal',
  'database','cloud','cloud-upload','download','upload','share','link','link-2',
  'copy','clipboard','bookmark','tag','tags','flag','book','book-open',
  'map','navigation','map-pin','compass','globe-2','wifi','wifi-off','rss',
  'send','inbox','inbox-full','at-sign','hash','type','bold','italic','underline',
  'check-circle','check-square','x-circle','alert-circle','alert-triangle','info',
  'plus','minus','x','chevron-down','chevron-right','arrow-down','arrow-up','arrow-right','arrow-left',
  'external-link','maximize','minimize','refresh','rotate-cw','loader','clock','calendar',
  'dollar-sign','trending-up','trending-down','pie-chart','bar-chart','activity',
  'facebook','twitter','instagram','youtube','twitch','discord','telegram','whatsapp',
  'apple','windows','android','globe-lock','bot','cpu','hard-drive','smartphone',
  'gem','crown','award','gift','sparkles','rocket','target','crosshair',
];

const EMOJI_MAP: Record<string, string> = {
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

function IconDisplay({ name }: { name: string }) {
  return <span className="text-lg leading-none">{EMOJI_MAP[name] ?? '📦'}</span>;
}

interface IconPickerProps {
  value?: string;
  onChange: (iconName: string) => void;
}

export default function IconPicker({ value, onChange }: IconPickerProps) {
  const [search, setSearch] = useState('');

  const filtered = useMemo(() => {
    if (!search) return ICON_NAMES;
    return ICON_NAMES.filter((n) => n.includes(search.toLowerCase()));
  }, [search]);

  return (
    <div>
      <input
        type="search"
        placeholder="Search icons…"
        className="input mb-2"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
      />
      <div className="grid grid-cols-8 gap-1 max-h-48 overflow-y-auto p-1 border border-gray-200 dark:border-ink-200 rounded-lg">
        {filtered.map((name) => (
          <button
            key={name}
            type="button"
            onClick={() => onChange(name)}
            title={name}
            className={`w-9 h-9 rounded-lg flex items-center justify-center text-base transition-all ${
              value === name
                ? 'bg-green-500 text-gray-900 dark:text-ink text-lg'
                : 'bg-gray-100 dark:bg-ink-100 text-gray-700 dark:text-ink-700 hover:bg-gray-200 dark:hover:bg-ink-200'
            }`}
          >
            <IconDisplay name={name} />
          </button>
        ))}
      </div>
      {filtered.length === 0 && (
        <p className="text-small text-gray-600 dark:text-ink-500 text-center py-4">No icons found.</p>
      )}
    </div>
  );
}
