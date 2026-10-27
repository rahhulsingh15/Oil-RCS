<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  <title>Sign in · RSC Compliance Portal</title>

  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            oilSidebar: '#0b1626',
            oilSidebarDark: '#070e19',
            oilSidebarActive: '#1a2e4c',
          },
          fontFamily: { sans: ['Inter', 'system-ui', 'Segoe UI', 'Roboto', 'sans-serif'] }
        }
      }
    }
  </script>

  <style>
    body { font-family: 'Inter', system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
    .grid-bg {
      background-image:
        linear-gradient(rgba(148,163,184,.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148,163,184,.07) 1px, transparent 1px);
      background-size: 36px 36px;
    }
    .glow {
      background: radial-gradient(600px circle at 15% 20%, rgba(14,165,233,.18), transparent 60%),
                  radial-gradient(500px circle at 85% 90%, rgba(220,38,38,.14), transparent 60%);
    }
    @keyframes rise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
    .rise { animation: rise .5s ease-out both; }
    .rise-2 { animation-delay: .08s; }
    .rise-3 { animation-delay: .16s; }
    @media (prefers-reduced-motion: reduce) { .rise { animation: none; } }
  </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">

<div class="min-h-screen flex">

  {{-- LEFT: brand panel (hidden on small screens) --}}
  <aside class="hidden lg:flex lg:w-[52%] xl:w-[55%] relative overflow-hidden bg-oilSidebar text-slate-300">
    <div class="absolute inset-0 grid-bg"></div>
    <div class="absolute inset-0 glow"></div>

    <div class="relative z-10 flex flex-col justify-between w-full p-10 xl:p-14">
      {{-- Logo --}}
      <div class="flex items-center gap-3 rise">
        <div class="w-11 h-11 rounded-full bg-white flex items-center justify-center p-0.5 shadow">
          <div class="w-full h-full rounded-full border-2 border-red-600 flex items-center justify-center">
            <span class="text-[12px] font-black tracking-tighter text-red-600 leading-none">OIL</span>
          </div>
        </div>
        <div class="leading-tight">
          <p class="text-white font-bold text-[15px]">RSC Compliance Portal</p>
          <p class="text-sky-400 text-[12px] font-medium">Aarohan 2.0</p>
        </div>
      </div>

      {{-- Headline --}}
      <div class="max-w-lg rise rise-2">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-amber-400 mb-4">Oil India Limited</p>
        <h1 class="text-white text-4xl xl:text-[44px] font-extrabold leading-[1.1] tracking-tight">
          Every block, every clearance,<br>every deadline in one place.
        </h1>
        <p class="mt-5 text-[15px] leading-relaxed text-slate-400">
          Track contractual, statutory and regulatory obligations across all RSC blocks, and get alerted before anything slips.
        </p>

        <ul class="mt-9 space-y-4">
          <li class="flex items-start gap-3.5">
            <span class="mt-0.5 w-9 h-9 rounded-lg bg-sky-500/10 border border-sky-500/20 flex items-center justify-center flex-shrink-0">
              <i class="fa-solid fa-file-contract text-sky-400 text-sm"></i>
            </span>
            <div>
              <p class="text-white font-semibold text-[14px]">Contracts &amp; milestones</p>
              <p class="text-slate-400 text-[13px]">EC, CTE, CTO validity and RSC obligations per block</p>
            </div>
          </li>
          <li class="flex items-start gap-3.5">
            <span class="mt-0.5 w-9 h-9 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center flex-shrink-0">
              <i class="fa-solid fa-bell text-amber-400 text-sm"></i>
            </span>
            <div>
              <p class="text-white font-semibold text-[14px]">Automated alerts</p>
              <p class="text-slate-400 text-[13px]">Email reminders before due dates and on overdue items</p>
            </div>
          </li>
          <li class="flex items-start gap-3.5">
            <span class="mt-0.5 w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center flex-shrink-0">
              <i class="fa-solid fa-clock-rotate-left text-emerald-400 text-sm"></i>
            </span>
            <div>
              <p class="text-white font-semibold text-[14px]">Full audit trail</p>
              <p class="text-slate-400 text-[13px]">Who changed what, and when</p>
            </div>
          </li>
        </ul>
      </div>

      <p class="text-[12px] text-slate-500 rise rise-3">&copy; {{ date('Y') }} Oil India Limited &middot; For authorised users only</p>
    </div>
  </aside>

  {{-- RIGHT: sign-in form --}}
  <main class="flex-1 flex items-center justify-center px-4 py-10 sm:px-8">
    <div class="w-full max-w-[400px]">

      {{-- Compact logo for mobile --}}
      <div class="lg:hidden flex items-center gap-3 mb-8">
        <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center p-0.5 shadow-sm border border-slate-200">
          <div class="w-full h-full rounded-full border-2 border-red-600 flex items-center justify-center">
            <span class="text-[11px] font-black tracking-tighter text-red-600 leading-none">OIL</span>
          </div>
        </div>
        <div class="leading-tight">
          <p class="font-bold text-[15px]">RSC Compliance Portal</p>
          <p class="text-sky-600 text-[12px] font-medium">Aarohan 2.0</p>
        </div>
      </div>

      <div class="rise">
        <h2 class="text-2xl font-bold tracking-tight">Sign in</h2>
        <p class="mt-1.5 text-[14px] text-slate-500">Use your portal account to continue.</p>
      </div>

      @if (session('status'))
        <div class="mt-6 flex items-center gap-2.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-[13px] text-emerald-800">
          <i class="fa-solid fa-circle-check"></i>
          <span>{{ session('status') }}</span>
        </div>
      @endif

      @if ($errors->any())
        <div role="alert" class="mt-6 flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-[13px] text-red-700">
          <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
          <span>{{ $errors->first() }}</span>
        </div>
      @endif

      <form method="POST" action="{{ route('login.attempt') }}" class="mt-7 space-y-5 rise rise-2" id="login-form">
        @csrf

        <div>
          <label for="email" class="block text-[13px] font-semibold text-slate-700 mb-1.5">Email address</label>
          <div class="relative">
            <i class="fa-regular fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[14px]"></i>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   placeholder="name@oilindia.in"
                   class="w-full rounded-lg border bg-white pl-10 pr-3.5 py-2.5 text-[14px] placeholder:text-slate-400 shadow-sm transition
                          focus:outline-none focus:ring-4 focus:ring-blue-500/15 focus:border-blue-600
                          {{ $errors->has('email') ? 'border-red-400' : 'border-slate-300' }}">
          </div>
        </div>

        <div>
          <label for="password" class="block text-[13px] font-semibold text-slate-700 mb-1.5">Password</label>
          <div class="relative">
            <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[13px]"></i>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   placeholder="Enter your password"
                   class="w-full rounded-lg border border-slate-300 bg-white pl-10 pr-11 py-2.5 text-[14px] placeholder:text-slate-400 shadow-sm transition
                          focus:outline-none focus:ring-4 focus:ring-blue-500/15 focus:border-blue-600">
            <button type="button" id="toggle-password" aria-label="Show password"
                    class="absolute right-1.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition">
              <i class="fa-regular fa-eye text-[14px]"></i>
            </button>
          </div>
          <p id="caps-warning" class="hidden mt-1.5 text-[12px] text-amber-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Caps Lock is on</p>
        </div>

        <label class="flex items-center gap-2.5 text-[13px] text-slate-600 select-none cursor-pointer">
          <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('remember') ? 'checked' : '' }}>
          Keep me signed in
        </label>

        <button type="submit" id="submit-btn"
                class="w-full flex items-center justify-center gap-2 rounded-lg bg-blue-700 hover:bg-blue-800 active:bg-blue-900 text-white font-semibold text-[14px] py-2.5 shadow-sm transition
                       focus:outline-none focus:ring-4 focus:ring-blue-500/30 disabled:opacity-70 disabled:cursor-wait">
          <span id="submit-label">Sign in</span>
          <i id="submit-icon" class="fa-solid fa-arrow-right text-[13px]"></i>
        </button>
      </form>

      <div class="mt-8 pt-6 border-t border-slate-200 flex items-start gap-2.5 text-[12px] text-slate-500 rise rise-3">
        <i class="fa-solid fa-shield-halved mt-0.5 text-slate-400"></i>
        <p>Access is limited to Admins and authorised Company Representatives. Contact your portal administrator if you need an account.</p>
      </div>
    </div>
  </main>
</div>

<script>
  (function () {
    var pwd = document.getElementById('password');
    var toggle = document.getElementById('toggle-password');
    var caps = document.getElementById('caps-warning');

    toggle.addEventListener('click', function () {
      var show = pwd.type === 'password';
      pwd.type = show ? 'text' : 'password';
      toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      toggle.innerHTML = '<i class="fa-regular ' + (show ? 'fa-eye-slash' : 'fa-eye') + ' text-[14px]"></i>';
      pwd.focus();
    });

    ['keyup', 'keydown'].forEach(function (evt) {
      pwd.addEventListener(evt, function (e) {
        if (e.getModifierState) caps.classList.toggle('hidden', !e.getModifierState('CapsLock'));
      });
    });

    document.getElementById('login-form').addEventListener('submit', function () {
      var btn = document.getElementById('submit-btn');
      btn.disabled = true;
      document.getElementById('submit-label').textContent = 'Signing in…';
      document.getElementById('submit-icon').className = 'fa-solid fa-circle-notch fa-spin text-[13px]';
    });
  })();
</script>
</body>
</html>
