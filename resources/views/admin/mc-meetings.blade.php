@extends('admin.layouts.master')

@php $input = 'w-full border border-slate-300 rounded px-2.5 py-1 text-slate-800 focus:outline-none focus:border-blue-500'; @endphp

@section('title', 'MC Meetings')
@section('heading', 'Management Committee Meetings')
@section('subheading', 'Meetings held per block, their minutes, and when the next one is due (half-yearly)')
@section('filters')
  <div class="flex flex-wrap items-center gap-2.5">
    @include('admin.layouts.filters')
    @can('mc_meetings.edit')
      <button type="button" onclick="document.getElementById('modal-meeting').classList.remove('hidden')" class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold text-[11px] no-print"><i class="fa-solid fa-plus mr-1"></i> Add Meeting</button>
    @endcan
  </div>
@endsection

@section('content')
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-3.5">
    @forelse ($blocks as $block)
      @php
        $last = $block->mcMeetings->whereNotNull('meeting_date')->sortBy('meeting_date')->last();
        $next = $last?->meeting_date->copy()->addMonthsNoOverflow(6);
      @endphp
      <div class="bg-white rounded border border-oilCardBorder shadow-xs overflow-hidden {{ $block->isRelinquished() ? 'opacity-70' : '' }}">
        <div class="px-3.5 py-2.5 border-b border-slate-100 flex items-center justify-between gap-2">
          <div>
            <a href="{{ route('blocks.show', $block) }}#mc" class="font-bold text-[13px] text-slate-900 hover:text-blue-700">{{ $block->code }}</a>
            <span class="text-[10px] text-slate-500 ml-1">{{ $block->mcMeetings->count() }} meetings held</span>
          </div>
          @if ($block->isRelinquished())
            @include('admin.layouts.badge', ['status' => 'Relinquished'])
          @elseif ($next)
            <span class="text-[10px] font-semibold {{ $next->isPast() ? 'text-red-600' : 'text-slate-600' }}">
              Next due: {{ $next->format('d.m.Y') }} {{ $next->isPast() ? '(overdue)' : '' }}
            </span>
          @else
            <span class="text-[10px] text-slate-400">No meeting held yet</span>
          @endif
        </div>

        <div class="overflow-x-auto">
          <table class="w-full table-fixed text-left text-[11px] min-w-[500px]">
            <colgroup>
              <col class="w-[26%]"><col class="w-[18%]"><col>@can('mc_meetings.edit')<col class="w-[19%]">@endcan
            </colgroup>
            <thead class="bg-slate-50 text-[9px] uppercase font-bold text-slate-500">
              <tr><th class="py-2 px-3.5">Meeting</th><th class="py-2 px-3.5">Date</th><th class="py-2 px-3.5">Minutes</th>@can('mc_meetings.edit')<th class="py-2 px-3.5 text-right">Action</th>@endcan</tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
              @forelse ($block->mcMeetings->sortByDesc('meeting_no') as $meeting)
                @php $latest = $meeting->minutes->first(); $more = $meeting->minutes->count() - 1; @endphp
                <tr class="h-10">
                  <td class="py-2 px-3.5 font-semibold text-slate-900 whitespace-nowrap">MC Meeting #{{ $meeting->meeting_no }}</td>
                  <td class="py-2 px-3.5 whitespace-nowrap">{{ $meeting->meeting_date?->format('d.m.Y') ?? 'Not recorded' }}</td>
                  <td class="py-2 px-3.5">
                    @if ($latest)
                      <a href="{{ route('documents.download', [$latest, 'inline' => 1]) }}" target="_blank" class="flex items-center gap-1.5 min-w-0 text-blue-700 hover:underline" title="{{ $latest->original_name }} · uploaded by {{ $latest->uploader?->name }} on {{ $latest->created_at->format('d.m.Y') }}">
                        <i class="fa-solid {{ $latest->icon() }} flex-shrink-0"></i>
                        <span class="truncate">{{ $latest->original_name }}</span>
                      </a>
                      @if ($more > 0)
                        <a href="{{ route('documents.index', ['block' => $block->id, 'type' => 'MC Minutes']) }}" class="text-[9px] text-slate-400 hover:underline">+{{ $more }} older file{{ $more > 1 ? 's' : '' }}</a>
                      @endif
                    @else
                      <span class="text-slate-400">Not uploaded</span>
                    @endif
                  </td>
                  @can('mc_meetings.edit')
                    <td class="py-2 px-3.5 text-right whitespace-nowrap">
                      <form method="POST" action="{{ route('mc-meetings.minutes', $meeting) }}" enctype="multipart/form-data">
                        @csrf
                        <label class="cursor-pointer text-[10px] font-semibold text-blue-600 hover:underline" title="{{ $latest ? 'Upload a newer version of the minutes' : 'Upload the minutes of this meeting' }}">
                          <i class="fa-solid fa-upload mr-0.5"></i> Upload
                          <input type="file" name="file" class="hidden" accept=".{{ implode(',.', config('documents.extensions')) }}" onchange="this.form.submit()">
                        </label>
                      </form>
                    </td>
                  @endcan
                </tr>
              @empty
                <tr><td colspan="4" class="py-4 text-center text-slate-400">No MC meetings held yet</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    @empty
      <div class="bg-white rounded border border-oilCardBorder p-6 text-center text-slate-400 text-[11px]">No blocks match these filters</div>
    @endforelse
  </div>

  @can('mc_meetings.edit')
    <!-- MODAL: ADD MC MEETING -->
    <div id="modal-meeting" class="{{ $errors->hasAny(['meeting_date', 'block_id']) ? '' : 'hidden' }} fixed inset-0 bg-slate-950/60 z-50 flex items-center justify-center p-4" onclick="if (event.target === this) this.classList.add('hidden')">
      <div class="bg-white rounded-lg shadow-2xl max-w-md w-full overflow-hidden border border-slate-200">
        <div class="px-4 py-3 bg-slate-900 text-white flex items-center justify-between">
          <h4 class="font-bold text-[13px]">Add MC Meeting</h4>
          <button type="button" onclick="document.getElementById('modal-meeting').classList.add('hidden')" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark text-sm"></i></button>
        </div>
        <form method="POST" action="{{ route('mc-meetings.store') }}" enctype="multipart/form-data" class="p-4 space-y-3 text-[11px]">
          @csrf
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label for="mc-block" class="block font-semibold text-slate-700 mb-0.5">Block *</label>
              <select id="mc-block" name="block_id" required class="{{ $input }} font-bold">
                <option value="">Select block</option>
                @foreach ($uploadBlocks as $b)
                  <option value="{{ $b->id }}" @selected(old('block_id') == $b->id)>{{ $b->code }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label for="mc-date" class="block font-semibold text-slate-700 mb-0.5">Meeting Date *</label>
              <input id="mc-date" name="meeting_date" type="date" required max="{{ today()->format('Y-m-d') }}" value="{{ old('meeting_date') }}" class="{{ $input }}">
            </div>
          </div>
          <div>
            <label for="mc-file" class="block font-semibold text-slate-700 mb-0.5">Minutes (optional)</label>
            <input id="mc-file" name="file" type="file" accept=".{{ implode(',.', config('documents.extensions')) }}" class="w-full text-[11px] file:mr-2 file:px-2.5 file:py-1 file:rounded file:border-0 file:bg-slate-100 file:text-slate-700 file:font-semibold">
            <p class="mt-0.5 text-[10px] text-slate-400">Can also be uploaded later · max {{ round(config('documents.max_kb') / 1024) }} MB</p>
          </div>
          <p class="text-[10px] text-slate-500 bg-slate-50 rounded p-2">The meeting number is set automatically. The next MC meeting due date (6 months later) updates in the Compliance Monitor.</p>
          <div class="pt-2 border-t border-slate-200 flex justify-end gap-2">
            <button type="button" onclick="document.getElementById('modal-meeting').classList.add('hidden')" class="px-3 py-1 border border-slate-300 rounded text-slate-700 hover:bg-slate-50 font-medium">Cancel</button>
            <button type="submit" class="px-3.5 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold">Save Meeting</button>
          </div>
        </form>
      </div>
    </div>
  @endcan
@endsection
