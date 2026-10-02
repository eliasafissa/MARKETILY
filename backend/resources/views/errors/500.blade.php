<!doctype html>
<html lang="en" class="dark" style="color-scheme: dark;">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 — Server Error · Marketly</title>
  <style>
    /* ── Reset ─────────────────────────────────────── */
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html{height:100%;-webkit-text-size-adjust:100%;color-scheme:dark}
    body{height:100%;min-height:100vh;display:flex;flex-direction:column;font-family:"Instrument Sans",ui-sans-serif,system-ui,sans-serif;background:#0A0A0A;color:#E2E8F0;line-height:1.6;-webkit-font-smoothing:antialiased}
    /* ── Accent palette ───────────────────────────── */
    .accent{color:#10B981}
    .red{color:#F87171}
    /* ── Layout ───────────────────────────────────── */
    .page{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:2rem}
    /* ── Typography ────────────────────────────────── */
    .code{font-size:clamp(5rem,20vw,9rem);font-weight:800;letter-spacing:-.05em;line-height:1;background:linear-gradient(135deg,#F87171 0%,#DC2626 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
    .heading{font-size:clamp(1.5rem,4vw,2.25rem);font-weight:700;color:#F8FAFC;margin-top:.75rem;letter-spacing:-.02em}
    .body{font-size:1rem;color:#94A3B8;max-width:520px;margin:1rem auto 0;line-height:1.75}
    /* ── Actions ──────────────────────────────────── */
    .actions{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;margin-top:2rem}
    .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.625rem 1.375rem;border-radius:.5rem;font-size:.875rem;font-weight:600;cursor:pointer;text-decoration:none;transition:all .15s ease;border:1px solid transparent}
    .btn-primary{background:#10B981;color:#051C16;border-color:#10B981}
    .btn-primary:hover{background:#059669;border-color:#059669}
    .btn-ghost{background:transparent;color:#94A3B8;border-color:#1E293B}
    .btn-ghost:hover{background:#1E293B;color:#E2E8F0}
    /* ── Orb decoration ───────────────────────────── */
    .orb{position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none;z-index:0}
    .orb-1{width:400px;height:400px;background:rgba(248,113,113,.08);top:-100px;left:-100px}
    .orb-2{width:300px;height:300px;background:rgba(248,113,113,.05);bottom:-80px;right:-80px}
    /* ── Divider ──────────────────────────────────── */
    .divider{width:60px;height:2px;background:linear-gradient(90deg,#F87171,#DC2626);border-radius:1px;margin:1.5rem auto 0}
    /* ── Logo ─────────────────────────────────────── */
    .logo{display:flex;align-items:center;gap:.5rem;font-weight:700;font-size:1.125rem;color:#F8FAFC;margin-bottom:2rem}
  </style>
</head>
<body>
  <!-- Ambient orbs -->
  <div class="orb orb-1" aria-hidden="true"></div>
  <div class="orb orb-2" aria-hidden="true"></div>

  <div class="page">
    <!-- Logo -->
    <div class="logo">
      <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect width="28" height="28" rx="8" fill="#10B981"/>
        <path d="M8 14.5L12 18.5L20 10" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Marketly
    </div>

    <p class="red text-sm font-semibold tracking-widest uppercase mb-2">Error</p>
    <div class="code">500</div>
    <div class="divider"></div>
    <h1 class="heading">Something went wrong</h1>
    <p class="body">
      We ran into an unexpected issue. Our team has been notified and is looking into it.
      Try refreshing the page, or come back shortly.
    </p>

    <div class="actions">
      <a href="/" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        Go home
      </a>
      <a href="javascript:location.reload()" class="btn btn-ghost">
        ↻ Try again
      </a>
    </div>
  </div>
</body>
</html>
