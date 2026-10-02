import { useId } from 'react';

interface TrendChartProps {
  data: number[];
  labels?: string[];
  height?: number;
  color?: string;
  showAxis?: boolean;
  className?: string;
}

/**
 * Lightweight SVG trend chart — no external chart library needed.
 * All colors are driven by CSS variables so it adapts to dark/light mode.
 */
export default function TrendChart({
  data,
  labels,
  height = 120,
  showAxis = true,
  className = '',
}: TrendChartProps) {
  const id = useId().replace(/:/g, '');
  if (data.length === 0) return null;

  const width = 100;
  const padding = showAxis ? 8 : 2;
  const max = Math.max(...data, 1);
  const min = Math.min(...data, 0);
  const range = max - min || 1;

  const points = data.map((v, i) => {
    const x = padding + (i / (data.length - 1 || 1)) * (width - padding * 2);
    const y = padding + (1 - (v - min) / range) * (height / 2 - padding);
    return [x, y] as [number, number];
  });

  const pathD = points.reduce((acc, [x, y], i) => {
    return acc + (i === 0 ? `M ${x} ${y}` : ` L ${x} ${y}`);
  }, '');

  const areaD = pathD + ` L ${points[points.length - 1][0]} ${height / 2} L ${points[0][0]} ${height / 2} Z`;

  const labelStep = Math.max(1, Math.floor(labels ? labels.length : 0 / 6));

  return (
    <div className={className}>
      <svg
        viewBox={`0 0 ${width} ${height / 2}`}
        preserveAspectRatio="none"
        className="w-full"
        style={{ height: height / 2 }}
        role="img"
        aria-label="Trend chart"
      >
        <defs>
          <linearGradient id={`trend-fill-${id}`} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor="currentColor" stopOpacity="0.35" className="text-green-500" />
            <stop offset="100%" stopColor="currentColor" stopOpacity="0" className="text-green-500" />
          </linearGradient>
        </defs>

        {/* Grid lines */}
        {showAxis && [0.25, 0.5, 0.75].map((p) => (
          <line
            key={p}
            x1={padding}
            y1={padding + (height / 2 - padding * 2) * p}
            x2={width - padding}
            y2={padding + (height / 2 - padding * 2) * p}
            stroke="currentColor"
            className="text-gray-200 dark:text-ink-200"
            strokeWidth="0.2"
            strokeDasharray="0.6 0.6"
          />
        ))}

        {/* Area fill */}
        <path d={areaD} fill={`url(#trend-fill-${id})`} />

        {/* Line */}
        <path
          d={pathD}
          fill="none"
          stroke="currentColor"
          className="text-green-500"
          strokeWidth="0.6"
          strokeLinecap="round"
          strokeLinejoin="round"
        />

        {/* Data points */}
        {points.map(([x, y], i) => (
          <circle
            key={i}
            cx={x}
            cy={y}
            r="0.6"
            fill="currentColor"
            className="text-green-500"
          />
        ))}
      </svg>

      {showAxis && labels && labels.length > 0 && (
        <div className="flex justify-between mt-1 px-1 text-[10px] text-gray-500 dark:text-ink-500">
          {labels.map((l, i) =>
            i % labelStep === 0 || i === labels.length - 1 ? (
              <span key={i}>{l}</span>
            ) : null
          )}
        </div>
      )}
    </div>
  );
}
