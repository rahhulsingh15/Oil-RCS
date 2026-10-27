@if ($paginator->hasPages())
  <div class="flex items-center gap-1 text-[11px]">
    @if ($paginator->onFirstPage())
      <span class="px-2 py-0.5 rounded border border-slate-200 text-slate-300">&lsaquo;</span>
    @else
      <a href="{{ $paginator->previousPageUrl() }}" class="px-2 py-0.5 rounded border border-slate-300 bg-white text-slate-600 hover:bg-slate-50">&lsaquo;</a>
    @endif

    @foreach ($elements as $element)
      @if (is_string($element))<span class="px-1 text-slate-400">…</span>@endif
      @if (is_array($element))
        @foreach ($element as $page => $url)
          @if ($page == $paginator->currentPage())
            <span class="px-2 py-0.5 rounded bg-slate-900 text-white font-semibold">{{ $page }}</span>
          @else
            <a href="{{ $url }}" class="px-2 py-0.5 rounded border border-slate-300 bg-white text-slate-600 hover:bg-slate-50">{{ $page }}</a>
          @endif
        @endforeach
      @endif
    @endforeach

    @if ($paginator->hasMorePages())
      <a href="{{ $paginator->nextPageUrl() }}" class="px-2 py-0.5 rounded border border-slate-300 bg-white text-slate-600 hover:bg-slate-50">&rsaquo;</a>
    @else
      <span class="px-2 py-0.5 rounded border border-slate-200 text-slate-300">&rsaquo;</span>
    @endif
  </div>
@endif
