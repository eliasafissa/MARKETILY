interface LogoProps { size?: 'sm' | 'md' | 'lg'; showText?: boolean }

export default function Logo({ size = 'md', showText = true }: LogoProps) {
  const height = size === 'sm' ? 40 : size === 'lg' ? 92 : 56;
  const darkSrc = showText ? '/marketlly-logo-dark.png' : '/marketlly-icon.png';
  const lightSrc = showText ? '/marketlly-logo-light.png' : '/marketlly-icon.png';
  return (
    <span className="inline-flex items-center" aria-label="Marketlly">
      <img src={lightSrc} alt="Marketlly" className="block dark:hidden object-contain" style={{ height, width: 'auto' }} />
      <img src={darkSrc} alt="Marketlly" className="hidden dark:block object-contain" style={{ height, width: 'auto' }} />
    </span>
  );
}
