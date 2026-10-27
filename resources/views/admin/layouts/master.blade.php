<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  <title>@yield('title', 'Dashboard') · OIL RSC Compliance Portal</title>

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <!-- FontAwesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            oilSidebar: '#0b1626',
            oilSidebarDark: '#070e19',
            oilSidebarHover: '#14233a',
            oilSidebarActive: '#1d4ed8',
            oilBg: '#f1f5f9',
            oilCardBorder: '#e2e8f0',
            oilNavyText: '#0f172a',
          },
          fontFamily: {
            sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
          }
        }
      }
    }
  </script>

  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background-color: #f1f5f9;
      color: #0f172a;
      -webkit-font-smoothing: antialiased;
    }

    /* Status indicator dots */
    .status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 6px; flex-shrink: 0; }
    .status-dot-green { background-color: #16a34a; }
    .status-dot-orange { background-color: #ea580c; }
    .status-dot-amber { background-color: #d97706; }
    .status-dot-red { background-color: #dc2626; }
    .status-dot-gray { background-color: #94a3b8; }

    /* Custom scrollbars */
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: #e2e8f0; }
    ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 4px; }

    /* Short forms with a hover full form (see config/glossary.php) */
    abbr.short-form { text-decoration: none; border-bottom: 1px dotted currentColor; cursor: help; }

    /* Map vector land path */
    .india-map-land { fill: #e8f0fe; stroke: #93c5fd; stroke-width: 1.5; }

    @media print {
      aside, header, .no-print { display: none !important; }
      body, main, .print-scroll { height: auto !important; overflow: visible !important; }
    }
  </style>
  @stack('styles')
</head>
<body class="flex h-screen overflow-hidden text-[12px] bg-oilBg">

  @include('admin.layouts.sidebar')

  <main class="flex-1 flex flex-col h-full overflow-hidden bg-oilBg min-w-0">
    @include('admin.layouts.header')

    <div class="flex-1 overflow-y-auto p-4 space-y-3.5 print-scroll">
      <!-- Page heading + filters -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
        <div>
          <h3 class="text-sm font-bold text-slate-900">@yield('heading')</h3>
          <p class="text-[11px] text-slate-500">@yield('subheading')</p>
        </div>
        @yield('filters')
      </div>

      @if (! auth()->user()->canSeeAllBlocks() && auth()->user()->visibleBlockIds() === [])
        <div class="flex items-center gap-2 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
          <i class="fa-solid fa-circle-info"></i>
          <span><b>No blocks are assigned to your account yet,</b> so there is no data to show. Please ask the portal Admin to assign your blocks.</span>
        </div>
      @endif

      @if (session('success'))
        <div class="flex items-center gap-2 rounded border border-emerald-200 bg-emerald-50 px-3 py-2 text-[11px] text-emerald-800 no-print">
          <i class="fa-solid fa-circle-check"></i><span>{{ session('success') }}</span>
        </div>
      @endif
      @if (session('error'))
        <div class="flex items-center gap-2 rounded border border-red-200 bg-red-50 px-3 py-2 text-[11px] text-red-700 no-print">
          <i class="fa-solid fa-circle-exclamation"></i><span>{{ session('error') }}</span>
        </div>
      @endif
      @if ($errors->any())
        <div class="rounded border border-red-200 bg-red-50 px-3 py-2 text-[11px] text-red-700 no-print">
          <ul class="list-disc pl-4 space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
      @endif

      @yield('content')
    </div>
  </main>

  @include('admin.layouts.script')
  @stack('scripts')
</body>
</html>
