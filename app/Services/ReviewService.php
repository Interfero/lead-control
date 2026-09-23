<?php

namespace App\Services;

use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /**
     * Список отзывов с пагинацией
     */
    public function getList(int $perPage = 20): LengthAwarePaginator
    {
        return Review::with(['createdBy', 'documents'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Создать отзыв (с опциональными вложениями — записи Document)
     *
     * @param  array<int, UploadedFile|null>  $documentFiles
     */
    public function create(array $data, array $documentFiles = []): Review
    {
        return DB::transaction(function () use ($data, $documentFiles) {
            $review = Review::create([
                'link' => $data['link'] ?? null,
                'image' => null,
                'partner_id' => $data['partner_id'] ?? null,
                'order_number' => $data['order_number'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $docService = app(ReviewDocumentService::class);
            $userId = (int) ($data['created_by'] ?? 0);
            foreach ($documentFiles as $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }
                $docService->attachToReview($review, $file, $userId);
            }

            return $review;
        });
    }
}
