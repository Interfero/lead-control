<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewController extends Controller
{
    public function __construct(
        private ReviewService $reviewService
    ) {}

    /**
     * Список отзывов (маршрут: developer, call_center)
     */
    public function index(Request $request)
    {
        $reviews = $this->reviewService->getList(20);

        return view('complaints.reviews', compact('reviews'));
    }

    /**
     * Форма добавления отзыва (маршрут: developer, call_center)
     */
    public function create()
    {
        return view('complaints.reviews.create');
    }

    /**
     * Карточка отзыва
     */
    public function show(Review $review)
    {
        $review->load(['createdBy', 'documents']);

        return view('complaints.reviews.show', compact('review'));
    }

    /**
     * Сохранить отзыв (только call_center / developer)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'link' => [
                'nullable',
                'string',
                'max:2000',
                'regex:/^https?:\/\/[^\s<>"\']+$/iu',
            ],
            'partner_id' => ['nullable', 'string', 'max:100', 'regex:/^[\p{L}\p{N}]*$/u'],
            'order_number' => ['nullable', 'string', 'max:100', 'regex:/^[\p{L}\p{N}]*$/u'],
            'documents' => 'nullable|array',
            'documents.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,odt,txt,csv',
            ],
        ], [
            'link.regex' => 'Ссылка должна начинаться с http:// или https://, без пробелов и опасных символов.',
            'documents.*.max' => 'Размер каждого файла не должен превышать 10 МБ',
            'documents.*.mimes' => 'Неподдерживаемый формат файла. Разрешённые форматы: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv',
            'partner_id.regex' => 'ID партнёра: только буквы и цифры, без спецсимволов.',
            'order_number.regex' => 'Номер заявки: только буквы и цифры, без спецсимволов.',
        ]);

        $documentsRaw = $request->file('documents', []);
        $documents = is_array($documentsRaw) ? $documentsRaw : array_filter([$documentsRaw]);

        try {
            $review = $this->reviewService->create([
                'link' => $validated['link'] ?? null,
                'partner_id' => $validated['partner_id'] ?? null,
                'order_number' => $validated['order_number'] ?? null,
                'created_by' => auth()->id(),
            ], $documents);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('complaints.reviews.show', $review)
            ->with('success', 'Отзыв добавлен');
    }

    /**
     * Отдать картинку отзыва из storage (storage/app/reviews)
     */
    public function showImage(string $path): StreamedResponse
    {
        $normalized = str_replace('\\', '/', $path);
        if (str_contains($normalized, '..')) {
            abort(404);
        }
        if (! str_starts_with($normalized, 'reviews/')) {
            abort(404);
        }
        $basePath = rtrim(Storage::disk('local')->path('reviews'), DIRECTORY_SEPARATOR);
        $fullPath = Storage::disk('local')->path($normalized);
        $realBase = realpath($basePath);
        $realFile = realpath($fullPath);
        if ($realBase === false || $realFile === false || ! str_starts_with($realFile, $realBase.DIRECTORY_SEPARATOR)) {
            abort(404);
        }
        if (! Storage::disk('local')->exists($normalized)) {
            abort(404);
        }

        return Storage::disk('local')->response($normalized);
    }
}
