interface LogoProps { size?: 'sm' | 'md' | 'lg'; showText?: boolean }

export default function Logo({ size = 'md', showText = true }: LogoProps) {
  const height = size === 'sm' ? 40 : size === 'lg' ? 92 : 56;
  return (
    <span className="inline-flex items-center" aria-label="Marketly">
      <img src="/marketly-logo-light.png" alt="Marketly" width={height * (showText ? 1.4 : 1.15)} height={height}
        className="block dark:hidden object-contain" style={{ height, width: 'auto', maxWidth: showText ? height * 1.5 : height * 1.1, objectPosition: 'center' }} />
      <img src="/marketly-logo-dark.png" alt="Marketly" width={height * (showText ? 1.4 : 1.15)} height={height}
        className="hidden dark:block object-contain" style={{ height, width: 'auto', maxWidth: showText ? height * 1.5 : height * 1.1, objectPosition: 'center' }} />
    </span>
  );
}
