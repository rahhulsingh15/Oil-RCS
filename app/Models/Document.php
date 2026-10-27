<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;

class Document extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'document_date' => 'date',
        'size' => 'integer',
    ];

    /** Validation rule for an uploaded file, from config/documents.php */
    public static function fileRules(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'file',
            'mimes:'.implode(',', config('documents.extensions')),
            'max:'.config('documents.max_kb')];
    }

    /**
     * Save an uploaded file for a block and create its record (+ audit entry).
     */
    public static function storeUpload(UploadedFile $file, Block $block, array $attributes, User $user): self
    {
        $path = $file->store('documents/'.str($block->code)->slug(), 'local');

        $document = static::create($attributes + [
            'block_id' => $block->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $user->id,
        ]);

        AuditLog::record('uploaded', $document, [], [
            'block' => $block->code, 'type' => $document->type, 'title' => $document->title, 'file' => $document->original_name,
        ], "Document uploaded: {$block->code} · {$document->title}");

        return $document;
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function mcMeeting(): BelongsTo
    {
        return $this->belongsTo(McMeeting::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Only documents of blocks the user may see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->visibleBlockIds();

        return $ids === null ? $query : $query->whereIn('block_id', $ids);
    }

    public function typeLabel(): string
    {
        return config("documents.types.{$this->type}", $this->type);
    }

    /** "2.4 MB", "830 KB" */
    public function sizeLabel(): string
    {
        return $this->size >= 1048576
            ? round($this->size / 1048576, 1).' MB'
            : max(1, round($this->size / 1024)).' KB';
    }

    /** Font Awesome icon for the file type. */
    public function icon(): string
    {
        return match (strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION))) {
            'pdf' => 'fa-file-pdf text-red-600',
            'doc', 'docx' => 'fa-file-word text-blue-600',
            'xls', 'xlsx' => 'fa-file-excel text-emerald-600',
            'ppt', 'pptx' => 'fa-file-powerpoint text-orange-600',
            'jpg', 'jpeg', 'png' => 'fa-file-image text-violet-600',
            default => 'fa-file text-slate-500',
        };
    }
}
