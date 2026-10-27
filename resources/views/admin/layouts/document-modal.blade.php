{{--
  MODAL: UPLOAD DOCUMENT
  Needs $uploadBlocks (blocks the user may upload to). Open with openDocumentModal(blockId?).
--}}
@can('documents.create')
@php $input = 'w-full border border-slate-300 rounded px-2.5 py-1 text-slate-800 focus:outline-none focus:border-blue-500'; @endphp
<div id="modal-document" class="hidden fixed inset-0 bg-slate-950/60 z-50 flex items-center justify-center p-4" onclick="if (event.target === this) closeDocumentModal()">
  <div class="bg-white rounded-lg shadow-2xl max-w-md w-full overflow-hidden border border-slate-200">
    <div class="px-4 py-3 bg-slate-900 text-white flex items-center justify-between">
      <h4 class="font-bold text-[13px]">Upload Document</h4>
      <button type="button" onclick="closeDocumentModal()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-sm"></i></button>
    </div>
    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="p-4 space-y-3 text-[11px]">
      @csrf
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label for="d-block" class="block font-semibold text-slate-700 mb-0.5">Block *</label>
          <select id="d-block" name="block_id" required class="{{ $input }} font-bold">
            <option value="">Select block</option>
            @foreach ($uploadBlocks as $b)
              <option value="{{ $b->id }}" @selected(old('block_id') == $b->id)>{{ $b->code }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label for="d-type" class="block font-semibold text-slate-700 mb-0.5">Document Type *</label>
          <select id="d-type" name="type" required class="{{ $input }}">
            @foreach (config('documents.types') as $key => $label)
              @continue($key === 'MC Minutes')
              <option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div>
        <label for="d-title" class="block font-semibold text-slate-700 mb-0.5">Title *</label>
        <input id="d-title" name="title" required maxlength="255" value="{{ old('title') }}" placeholder="e.g. WCR of OBRA well" class="{{ $input }}">
      </div>
      <div>
        <label for="d-date" class="block font-semibold text-slate-700 mb-0.5">Document Date</label>
        <input id="d-date" name="document_date" type="date" value="{{ old('document_date') }}" class="{{ $input }}">
      </div>
      <div>
        <label for="d-desc" class="block font-semibold text-slate-700 mb-0.5">Description</label>
        <textarea id="d-desc" name="description" rows="2" maxlength="2000" class="{{ $input }}">{{ old('description') }}</textarea>
      </div>
      <div>
        <label for="d-file" class="block font-semibold text-slate-700 mb-0.5">File *</label>
        <input id="d-file" name="file" type="file" required accept=".{{ implode(',.', config('documents.extensions')) }}" class="w-full text-[11px] file:mr-2 file:px-2.5 file:py-1 file:rounded file:border-0 file:bg-slate-100 file:text-slate-700 file:font-semibold">
        <p class="mt-0.5 text-[10px] text-slate-400">{{ strtoupper(implode(', ', config('documents.extensions'))) }} · max {{ round(config('documents.max_kb') / 1024) }} MB</p>
      </div>
      <div class="pt-2 border-t border-slate-200 flex justify-end gap-2">
        <button type="button" onclick="closeDocumentModal()" class="px-3 py-1 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium">Cancel</button>
        <button type="submit" class="px-3.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold"><i class="fa-solid fa-upload mr-1"></i> Upload</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openDocumentModal(blockId) {
    if (blockId) document.getElementById('d-block').value = blockId;
    document.getElementById('modal-document').classList.remove('hidden');
  }
  function closeDocumentModal() { document.getElementById('modal-document').classList.add('hidden'); }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeDocumentModal(); });
  @if ($errors->hasAny(['block_id', 'type', 'title', 'document_date', 'description', 'file']) && old('title') !== null)
    openDocumentModal(); // reopen with the user's input after a validation error
  @endif
</script>
@endcan
