@extends('admin.layouts.master')

@section('title', 'Document Repository')
@section('heading', 'Document Repository')
@section('subheading', 'WCR, RSC, study reports, letters, MC minutes and other documents of each block')
@section('filters')
  <div class="flex flex-wrap items-center gap-2.5">
    @include('admin.layouts.filters', ['third' => ['name' => 'type', 'label' => 'All Document Types', 'options' => config('documents.types')]])
    @can('documents.create')
      <button type="button" onclick="openDocumentModal()" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px] no-print"><i class="fa-solid fa-upload mr-1"></i> Upload Document</button>
    @endcan
  </div>
@endsection

@section('content')
  <!-- Summary cards -->
  <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3">
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">Total Documents</p>
      <p class="text-2xl font-black text-slate-900 mt-0.5">{{ $totalCount }}</p>
    </div>
    <div class="bg-white rounded p-3 border border-oilCardBorder shadow-xs">
      <p class="text-[11px] font-medium text-slate-500">Total Size</p>
      <p class="text-2xl font-black text-slate-900 mt-0.5">{{ $totalSize >= 1048576 ? round($totalSize / 1048576, 1).' MB' : round($totalSize / 1024).' KB' }}</p>
    </div>
    @foreach (config('documents.types') as $key => $label)
      <a href="{{ route('documents.index', ['type' => $key]) }}" class="bg-white rounded p-3 border shadow-xs hover:border-slate-300 {{ request('type') === $key ? 'border-blue-500' : 'border-oilCardBorder' }}">
        <p class="text-[11px] font-medium text-slate-500 truncate" title="{{ $label }}">{{ $key === 'Letter' ? 'Letters' : $key }}</p>
        <p class="text-2xl font-black text-blue-700 mt-0.5">{{ $typeCounts[$key] ?? 0 }}</p>
      </a>
    @endforeach
  </div>

  <!-- Documents table -->
  <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-[11px]">
        <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500 border-b border-slate-200">
          <tr>
            <th class="py-2.5 px-3">Document</th>
            <th class="py-2.5 px-3">Block</th>
            <th class="py-2.5 px-3">Type</th>
            <th class="py-2.5 px-3">Document Date</th>
            <th class="py-2.5 px-3">Size</th>
            <th class="py-2.5 px-3">Uploaded By</th>
            <th class="py-2.5 px-3 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
          @forelse ($documents as $doc)
            @php $viewable = in_array(strtolower(pathinfo($doc->original_name, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png']); @endphp
            <tr class="hover:bg-slate-50 align-top">
              <td class="py-2.5 px-3">
                <div class="flex items-start gap-2">
                  <i class="fa-solid {{ $doc->icon() }} text-base mt-0.5"></i>
                  <div class="min-w-0">
                    <p class="font-bold text-slate-900">{{ $doc->title }}</p>
                    <p class="text-[10px] text-slate-400 truncate max-w-[260px]" title="{{ $doc->original_name }}">{{ $doc->original_name }}</p>
                    @if ($doc->description)<p class="text-[10px] text-slate-500 mt-0.5 max-w-[320px]">{{ $doc->description }}</p>@endif
                  </div>
                </div>
              </td>
              <td class="py-2.5 px-3 font-bold text-slate-900 whitespace-nowrap">{{ $doc->block->code }}</td>
              <td class="py-2.5 px-3 whitespace-nowrap">
                <span class="px-2 py-0.5 rounded bg-slate-100 font-semibold text-[10px]">{{ $doc->typeLabel() }}</span>
                @if ($doc->mcMeeting)<span class="block text-[10px] text-slate-400 mt-0.5">MC meeting #{{ $doc->mcMeeting->meeting_no }}</span>@endif
              </td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $doc->document_date?->format('d.m.Y') ?? '-' }}</td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $doc->sizeLabel() }}</td>
              <td class="py-2.5 px-3 whitespace-nowrap">{{ $doc->uploader?->name ?? '-' }}<span class="block text-[10px] text-slate-400">{{ $doc->created_at->format('d.m.Y H:i') }}</span></td>
              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                @if ($viewable)
                  <a href="{{ route('documents.download', [$doc, 'inline' => 1]) }}" target="_blank" class="px-1.5 text-slate-500 hover:text-blue-700" title="View"><i class="fa-solid fa-eye"></i></a>
                @endif
                <a href="{{ route('documents.download', $doc) }}" class="px-1.5 text-slate-500 hover:text-blue-700" title="Download"><i class="fa-solid fa-download"></i></a>
                @can('documents.delete')
                  <form method="POST" action="{{ route('documents.destroy', $doc) }}" class="inline" data-confirm="Delete &quot;{{ $doc->title }}&quot;? The file will be removed.">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-1.5 text-slate-400 hover:text-red-600" title="Delete"><i class="fa-solid fa-trash-can"></i></button>
                  </form>
                @endcan
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="py-8 text-center text-slate-400">
              <i class="fa-regular fa-folder-open text-xl mb-1 block"></i>
              No documents found. @can('documents.create') Use "Upload Document" to add one. @endcan
            </td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="p-2.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-[11px] text-slate-500">
      <span>Showing {{ $documents->count() }} of {{ $documents->total() }} documents</span>
      {{ $documents->links() }}
    </div>
  </div>

  @include('admin.layouts.document-modal')
@endsection
