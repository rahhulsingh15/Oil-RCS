@extends('admin.layouts.master')

@section('title', 'Short Forms')
@section('heading', 'Short Forms & Full Forms')
@section('subheading', 'Terms used in the portal. Tip: hover over a short form anywhere in the portal to see its full form.')
@section('filters')
  <div class="relative w-52 no-print">
    <input type="text" oninput="filterGlossary(this.value)" placeholder="Search short or full form..." class="w-full text-[11px] pl-6 pr-2 py-1 border border-slate-300 rounded bg-white focus:outline-none focus:border-blue-500">
    <i class="fa-solid fa-magnifying-glass absolute left-2 top-2 text-slate-400 text-[10px]"></i>
  </div>
@endsection

@section('content')
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-3.5">
    @foreach (config('glossary') as $group => $terms)
      <div class="glossary-group bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
        <h4 class="px-3.5 py-2 border-b border-slate-100 font-bold text-[11px] uppercase tracking-wider text-slate-800">{{ $group }}</h4>
        <table class="w-full table-fixed text-left text-[11px]">
          <colgroup><col class="w-[18%]"><col class="w-[40%]"><col></colgroup>
          <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
            @foreach ($terms as $short => [$full, $meaning])
              <tr class="glossary-row" data-search="{{ strtolower($short.' '.$full.' '.$meaning) }}">
                {{-- Wrapped in <abbr> already, so the page script leaves these alone --}}
                <td class="py-2 px-3.5 font-black text-slate-900"><abbr class="no-underline" title="">{{ $short }}</abbr></td>
                <td class="py-2 px-3.5 font-semibold text-blue-900"><abbr class="no-underline" title="">{{ $full }}</abbr></td>
                <td class="py-2 px-3.5 text-slate-500"><abbr class="no-underline" title="">{{ $meaning ?: '-' }}</abbr></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endforeach
  </div>
  <p class="text-[10px] text-slate-400">Full forms of some industry terms (e.g. EDPMB, PSTM) are the commonly used ones; confirm with OIL if a different expansion is used internally.</p>
@endsection

@push('scripts')
<script>
  function filterGlossary(q) {
    q = q.toLowerCase().trim();
    document.querySelectorAll('.glossary-group').forEach(function (group) {
      var visible = 0;
      group.querySelectorAll('.glossary-row').forEach(function (row) {
        var show = q === '' || row.dataset.search.indexOf(q) !== -1;
        row.classList.toggle('hidden', !show);
        visible += show ? 1 : 0;
      });
      group.classList.toggle('hidden', visible === 0);
    });
  }
</script>
@endpush
