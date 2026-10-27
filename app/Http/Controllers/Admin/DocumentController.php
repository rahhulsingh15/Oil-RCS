<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Block;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Document Repository: upload, list, download and delete block documents */
class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $documents = Document::query()
            ->with(['block:id,code', 'uploader:id,name', 'mcMeeting:id,meeting_no'])
            ->whereIn('block_id', $this->filteredBlockIds($request))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->input('q').'%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)
                    ->orWhere('original_name', 'like', $term)
                    ->orWhere('description', 'like', $term));
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $all = Document::visibleTo($request->user());

        return view('admin.documents', [
            'documents' => $documents,
            'typeCounts' => (clone $all)->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'totalCount' => (clone $all)->count(),
            'totalSize' => (clone $all)->sum('size'),
            'uploadBlocks' => Block::visibleTo($request->user())->orderBy('code')->get(['id', 'code']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'block_id' => ['required', 'integer', 'exists:blocks,id'],
            'type' => ['required', Rule::in(array_keys(config('documents.types')))],
            'title' => ['required', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'file' => Document::fileRules(),
        ]);

        $block = Block::findOrFail($data['block_id']);
        abort_unless($request->user()->canAccessBlock($block), 403, 'You can only upload documents for your assigned blocks.');

        Document::storeUpload($request->file('file'), $block, [
            'type' => $data['type'],
            'title' => $data['title'],
            'document_date' => $data['document_date'] ?? null,
            'description' => $data['description'] ?? null,
        ], $request->user());

        return back()->with('success', "Document \"{$data['title']}\" uploaded for {$block->code}.");
    }

    /** Download, or open in the browser with ?inline=1 (PDF and images). */
    public function download(Request $request, Document $document)
    {
        abort_unless($request->user()->canAccessBlock($document->block_id), 403);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'The file is missing on the server.');

        if ($request->boolean('inline')) {
            return response()->file(Storage::disk('local')->path($document->file_path), [
                'Content-Type' => $document->mime_type,
                'Content-Disposition' => 'inline; filename="'.addslashes($document->original_name).'"',
            ]);
        }

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroy(Request $request, Document $document)
    {
        abort_unless($request->user()->canAccessBlock($document->block_id), 403);

        AuditLog::record('deleted', $document, [
            'block' => $document->block->code, 'type' => $document->type, 'title' => $document->title, 'file' => $document->original_name,
        ], [], "Document deleted: {$document->block->code} · {$document->title}");

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', "Document \"{$document->title}\" deleted.");
    }
}
