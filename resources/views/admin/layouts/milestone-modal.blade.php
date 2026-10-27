{{--
  MODAL: MILESTONE EDIT & UPDATE
  Open it from a button: onclick="openMilestoneModal(this)" with data-milestone='{...}' (see milestoneJson below).
--}}
@can('milestones.edit')
@php $canEditDates = auth()->user()->can('milestones.edit_dates'); @endphp
<div id="modal-milestone" class="hidden fixed inset-0 bg-slate-950/60 z-50 flex items-center justify-center p-4" onclick="if (event.target === this) closeMilestoneModal()">
  <div class="bg-white rounded-lg shadow-2xl max-w-md w-full overflow-hidden border border-slate-200">
    <div class="px-4 py-3 bg-slate-900 text-white flex items-center justify-between">
      <h4 class="font-bold text-[13px]">Update RSC Milestone Record</h4>
      <button type="button" onclick="closeMilestoneModal()" class="text-slate-400 hover:text-white">
        <i class="fa-solid fa-xmark text-sm"></i>
      </button>
    </div>
    <form id="milestone-form" method="POST" action="" class="p-4 space-y-3 text-[11px]">
      @csrf
      @method('PUT')
      <div>
        <label class="block font-semibold text-slate-700 mb-0.5">Block</label>
        <input type="text" id="m-block" readonly class="w-full bg-slate-100 border border-slate-300 rounded px-2.5 py-1 font-bold text-slate-800">
      </div>
      <div>
        <label class="block font-semibold text-slate-700 mb-0.5">Item</label>
        <input type="text" id="m-item" readonly class="w-full bg-slate-100 border border-slate-300 rounded px-2.5 py-1 text-slate-700">
      </div>
      <div class="grid grid-cols-2 gap-2">
        <div>
          <label for="m-due" class="block font-semibold text-slate-700 mb-0.5">Due Date</label>
          @if ($canEditDates)
            <input type="date" id="m-due" name="due_date" required class="w-full border border-slate-300 rounded px-2.5 py-1 font-semibold text-slate-800">
          @else
            <input type="date" id="m-due" disabled class="w-full bg-slate-100 border border-slate-300 rounded px-2.5 py-1 font-semibold text-slate-500" title="You do not have permission to change due dates">
          @endif
        </div>
        <div>
          <label for="m-status" class="block font-semibold text-slate-700 mb-0.5">Status</label>
          <select id="m-status" name="status" class="w-full border border-slate-300 rounded px-2.5 py-1 font-bold text-slate-800">
            @foreach (\App\Models\Milestone::STATUS_LABELS as $value => $label)
              <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
      </div>
      <div>
        <label for="m-remarks" class="block font-semibold text-slate-700 mb-0.5">Remarks</label>
        <textarea id="m-remarks" name="remarks" rows="2" maxlength="1000" class="w-full border border-slate-300 rounded px-2.5 py-1 text-slate-800"></textarea>
      </div>
      <p id="m-last" class="text-[10px] text-slate-400"></p>
      <div class="pt-2 border-t border-slate-200 flex justify-end gap-2">
        <button type="button" onclick="closeMilestoneModal()" class="px-3 py-1 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium">Cancel</button>
        <button type="submit" class="px-3.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold">Save Update</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openMilestoneModal(btn) {
    var m = JSON.parse(btn.dataset.milestone);
    var form = document.getElementById('milestone-form');
    form.action = m.url;
    document.getElementById('m-block').value = m.block;
    document.getElementById('m-item').value = m.item;
    document.getElementById('m-due').value = m.due;
    document.getElementById('m-status').value = m.status;
    document.getElementById('m-remarks').value = m.remarks || '';
    document.getElementById('m-last').textContent = m.last ? 'Last updated: ' + m.last : '';
    document.getElementById('modal-milestone').classList.remove('hidden');
  }
  function closeMilestoneModal() {
    document.getElementById('modal-milestone').classList.add('hidden');
  }
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMilestoneModal(); });
</script>
@endcan
