<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Order;
use App\Models\CfmOperation;
use App\Models\Review;
use App\Models\User;
use App\Services\CfmOperationDocumentService;
use App\Services\CfmService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    private const MAX_FILES_PER_ORDER_CATEGORY = 20;

    /**
     * Имя файла для хранения и отображения: без путей и нулевых байт, разумная длина.
     */
    private function safeOriginalFileName(UploadedFile $file): string
    {
        $name = basename(str_replace("\0", '', $file->getClientOriginalName()));
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file';
        }

        return mb_strlen($name) > 200 ? mb_substr($name, 0, 200) : $name;
    }

    /**
     * Загрузка документа
     */
    public function store(Request $request)
    {
        try {
            $user = auth()->user();
            $routeName = $request->route()->getName();
            
        // Определяем тип сущности по пути запроса (более надёжно, чем по имени маршрута)
        // path() без ведущего слеша; под префиксом /crm это "crm/orders/4/documents"
        $path = ltrim($request->path(), '/');
        $pathWithoutPrefix = preg_replace('#^crm/#', '', $path) ?? $path;
        $isOrderPath = (bool) preg_match('#^orders/\d+/documents#', $pathWithoutPrefix);
        $isCfmPath = (bool) preg_match('#^cfm/\d+/documents#', $pathWithoutPrefix);
        $isHrPath = (bool) preg_match('#^hr/\d+/documents#', $pathWithoutPrefix);

        if (config('app.debug')) {
            \Log::debug('Document upload', [
                'path' => $path,
                'is_order' => $isOrderPath,
                'is_cfm' => $isCfmPath,
                'is_hr' => $isHrPath,
            ]);
        }

        if ($isOrderPath || ($routeName && str_starts_with($routeName, 'orders.documents'))) {
            // Для заказов ID передаётся как order_id в маршруте
            $orderId = $request->route('order_id') ?? $request->input('order_id');
            
            // Если не нашли в маршруте, пытаемся извлечь из пути
            if (! $orderId && preg_match('#(?:^|/)orders/(\d+)/documents#', $pathWithoutPrefix, $matches)) {
                $orderId = $matches[1];
            }
            
            if (!$orderId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID заказа не указан',
                ], 400);
            }
            $documentable = Order::findOrFail($orderId);

            if ($user->hasRole('general_director')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Генеральный директор не может загружать документы к заказам',
                ], 403);
            }
            
            $isClosedOrder = in_array($documentable->order_status, ['completed', 'cancelled_cc', 'cancelled_city'], true);
            if ($isClosedOrder && ! $user->hasAnyRole(['developer', 'senior_dispatcher'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя добавлять документы к завершённому или отменённому заказу',
                ], 403);
            }
            
            // Проверка доступа к городу заказа
            if (!$user->hasRole('developer') && !$user->hasAccessToCity($documentable->address->city_id)) {
                abort(403);
            }
            
            $validated = $request->validate([
                'file' => [
                    'required',
                    'file',
                    'max:10240',
                    Document::orderPhotoMimeValidationRule(),
                ],
                'category' => 'required|in:contract,receipts,parts_photos,storage_receipt',
            ], [
                'file.required' => 'Файл не выбран',
                'file.file' => 'Загруженный файл недействителен',
                'file.uploaded' => 'Не удалось загрузить файл. Обычно файл больше лимита сервера — сожмите фото или выберите файл до 10 МБ',
                'file.max' => 'Размер файла не должен превышать 10 МБ',
                'file.mimes' => 'Можно загружать только фотографии: '.Document::orderPhotoFormatsLabel(),
                'category.required' => 'Категория документа не указана',
                'category.in' => 'Недопустимая категория документа',
            ]);
            
            $file = $request->file('file');
            if (! $file instanceof UploadedFile || ! Document::isAllowedOrderPhoto($file)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Можно загружать только фотографии: '.Document::orderPhotoFormatsLabel(),
                ], 400);
            }
            
            $category = $validated['category'];
            $existingCount = Document::query()
                ->where('documentable_type', Order::class)
                ->where('documentable_id', $documentable->order_id)
                ->where('document_category', $category)
                ->count();

            if ($existingCount >= self::MAX_FILES_PER_ORDER_CATEGORY) {
                return response()->json([
                    'success' => false,
                    'message' => 'Максимум '.self::MAX_FILES_PER_ORDER_CATEGORY.' файлов в этой категории',
                ], 422);
            }

            $folder = "documents/orders/{$documentable->order_id}/{$category}";
            $documentableType = Order::class;
            $documentableId = $documentable->order_id;
            
        } elseif ($isCfmPath || ($routeName && str_starts_with($routeName, 'cfm.documents'))) {
            $cfmId = $request->route('cfm_id') ?? $request->input('cfm_id');
            
            // Если не нашли в маршруте, пытаемся извлечь из пути
            if (! $cfmId && preg_match('#(?:^|/)cfm/(\d+)/documents#', $pathWithoutPrefix, $matches)) {
                $cfmId = $matches[1];
            }
            
            if (!$cfmId) {
                return response()->json(['success' => false, 'message' => 'ID кассовой операции не указан'], 400);
            }
            $documentable = CfmOperation::findOrFail($cfmId);
            
            // Проверка доступа к кассе: все роли с входом в Кассу могут приложить файл (в т.ч. после проведения)
            if (! $user->hasAnyRole(CfmService::CASH_ACCESS_ROLES)) {
                abort(403);
            }

            // Проверка доступа к городу кассовой операции
            if (! $user->hasRole('developer') && ! $user->hasAccessToCity($documentable->city_id)) {
                abort(403, 'Нет доступа к городу этой кассовой операции');
            }
            
            $request->validate([
                'file' => [
                    'required',
                    'file',
                    'max:10240',
                    'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,odt,txt,csv',
                ],
            ], [
                'file.required' => 'Файл не выбран',
                'file.file' => 'Загруженный файл недействителен',
                'file.max' => 'Размер файла не должен превышать 10 МБ',
                'file.mimes' => 'Неподдерживаемый формат файла. Разрешённые форматы: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv',
            ]);

            $file = $request->file('file');

            try {
                $document = app(CfmOperationDocumentService::class)->attachToOperation($documentable, $file, $user->user_id);
            } catch (ValidationException $e) {
                $message = collect($e->errors())->flatten()->first() ?? 'Ошибка валидации';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'errors' => $e->errors(),
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Файл загружен',
                'document' => [
                    'document_id' => $document->document_id,
                    'file_name' => $document->file_name,
                    'human_size' => $document->human_size,
                    'file_mime' => $document->file_mime,
                ],
            ]);

        } elseif ($isHrPath || ($routeName && (str_contains($routeName, 'hr.documents') || str_contains($routeName, '.hr.documents')))) {
            // Получаем user_id из маршрута
            $userId = $request->route('user_id') ?? $request->input('user_id');
            
            // Если не нашли в маршруте, пытаемся извлечь из пути
            if (! $userId && preg_match('#(?:^|/)hr/(\d+)/documents#', $pathWithoutPrefix, $matches)) {
                $userId = $matches[1];
            }
            
            if (!$userId) {
                return response()->json([
                    'success' => false, 
                    'message' => 'ID сотрудника не указан',
                ], 400);
            }
            $documentable = User::with(['cities', 'roles'])->findOrFail($userId);
            
            // Проверка доступа к сотрудникам
            if (!$user->hasAnyRole(['branch_head', 'regional_director', 'developer', 'general_director'])) {
                abort(403);
            }

            // Проверка доступа к городам сотрудника
            if (!$user->hasAnyRole(['developer', 'general_director'])) {
                $employeeCityIds = $documentable->cities->pluck('city_id')->toArray();
                $userCityIds = $user->cities->pluck('city_id')->toArray();
                $hasAccess = !empty(array_intersect($employeeCityIds, $userCityIds));
                
                if (!$hasAccess) {
                    abort(403, 'Нет доступа к городам этого сотрудника');
                }
            }
            
            try {
                $validated = $request->validate([
                    'file' => [
                        'required',
                        'file',
                        'max:10240',
                        'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,odt,txt,csv',
                    ],
                ], [
                    'file.required' => 'Файл не выбран',
                    'file.file' => 'Загруженный файл недействителен',
                    'file.max' => 'Размер файла не должен превышать 10 МБ',
                    'file.mimes' => 'Неподдерживаемый формат файла. Разрешённые форматы: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $e->errors(),
                ], 400);
            }
            
            // Дополнительная проверка MIME-типа для jpg/jpeg файлов
            $file = $request->file('file');
            $mimeType = $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Проверяем расширение файла для jpg/jpeg
            $isJpeg = in_array($extension, ['jpg', 'jpeg']);
            
            $allowedMimeTypes = [
                'image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/gif',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.oasis.opendocument.text',
                'text/plain', 'text/csv', 'application/csv',
            ];
            
            // Для jpg/jpeg файлов разрешаем любые варианты MIME-типа изображения
            if ($isJpeg && str_starts_with($mimeType, 'image/')) {
                // Разрешаем jpg/jpeg файлы
            } elseif (!in_array($mimeType, $allowedMimeTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => "Неподдерживаемый тип файла: {$mimeType} (расширение: {$extension}). Разрешённые форматы: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv",
                ], 400);
            }
            
            $folder = "documents/hr/{$documentable->user_id}";
            $documentableType = User::class;
            $documentableId = $documentable->user_id;
            $category = 'general';
            
        } else {
            \Log::warning('Unknown document upload path', [
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Неизвестный тип документа',
            ], 400);
        }
        
        // Получаем файл (если он ещё не получен в блоках выше)
        if (!isset($file)) {
            $file = $request->file('file');
        }
        
        if (!$file) {
            \Log::error('File not found in request');
            return response()->json([
                'success' => false,
                'message' => 'Файл не найден в запросе',
            ], 400);
        }
        
        $safeName = $this->safeOriginalFileName($file);
        $fileName = time().'_'.$safeName;

        // Сохраняем файл
        try {
            $filePath = $file->storeAs($folder, $fileName, 'local');
            if (config('app.debug')) {
                \Log::debug('Document file saved', ['file_path' => $filePath]);
            }
        } catch (\Exception $e) {
            \Log::error('File save error', [
                'message' => $e->getMessage(),
                'folder' => $folder,
            ]);
            throw $e;
        }
        
        // Создаём запись в БД
        try {
            $document = new Document([
                'documentable_type' => $documentableType,
                'documentable_id' => $documentableId,
                'document_category' => $category,
                'file_name' => $safeName,
                'file_path' => $filePath,
                'file_mime' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
            $document->uploaded_by = $user->user_id;
            $document->save();
            if (config('app.debug')) {
                \Log::debug('Document created', ['document_id' => $document->document_id]);
            }
        } catch (\Exception $e) {
            \Log::error('Document create error', array_filter([
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]));
            // Удаляем файл, если запись в БД не создалась
            if (isset($filePath) && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
            throw $e;
        }
        
            return response()->json([
                'success' => true,
                'message' => 'Файл загружен',
                'document' => [
                    'document_id' => $document->document_id,
                    'file_name' => $document->file_name,
                    'human_size' => $document->human_size,
                    'file_mime' => $document->file_mime,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Document upload validation error', [
                'errors' => $e->errors(),
                'message' => $e->getMessage(),
            ]);
            $flat = collect($e->errors())->flatten()->filter()->implode(' ');

            return response()->json([
                'success' => false,
                'message' => $flat !== '' ? $flat : 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Document upload error', array_filter([
                'message' => $e->getMessage(),
                'file' => config('app.debug') ? $e->getFile() : null,
                'line' => config('app.debug') ? $e->getLine() : null,
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ]));
            $userMessage = config('app.debug')
                ? 'Ошибка при загрузке файла: '.$e->getMessage()
                : 'Ошибка при загрузке файла. Попробуйте позже или обратитесь к администратору.';

            return response()->json([
                'success' => false,
                'message' => $userMessage,
            ], 500);
        }
    }
    
    /**
     * Удаление документа
     */
    public function destroy(int $document_id)
    {
        $document = Document::findOrFail($document_id);
        $user = auth()->user();
        $shouldSyncGmDelete = false;
        $gmSyncOrder = null;
        
        // Проверка доступа
        if ($document->documentable_type === Order::class) {
            $shouldSyncGmDelete = true;
            $order = Order::find($document->documentable_id);
            $gmSyncOrder = $order;
            
            // Проверка статуса заказа - запрещаем удаление документов для завершённых или отменённых заказов
            if ($order && in_array($order->order_status, ['completed', 'cancelled_cc', 'cancelled_city'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя удалять документы из завершённого или отменённого заказа',
                ], 403);
            }
            
            if (!$user->hasRole('developer') && !$user->hasAccessToCity($order->address->city_id)) {
                abort(403);
            }
        } elseif ($document->documentable_type === CfmOperation::class) {
            $operation = CfmOperation::find($document->documentable_id);
            if ($operation && $operation->cfm_closed_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя удалять документы из проведённой операции',
                ], 403);
            }

            if (! $user->hasAnyRole(['developer', 'branch_head', 'regional_director', 'senior_manager'])) {
                abort(403);
            }

            if ($operation && ! $user->hasRole('developer') && ! $user->hasAccessToCity($operation->city_id)) {
                abort(403, 'Нет доступа к городу этой кассовой операции');
            }
        } elseif ($document->documentable_type === User::class) {
            // Удаление документов HR только разработчику
            if (!$user->hasRole('developer')) {
                abort(403, 'Удаление документов сотрудника доступно только разработчику');
            }
        } elseif ($document->documentable_type === Review::class) {
            if (! $user->hasAnyRole(['developer', 'call_center'])) {
                abort(403);
            }
        } else {
            abort(403, 'Неизвестный тип документа');
        }

        // Удаляем файл из storage
        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }
        
        // Удаляем запись из БД
        $deletedDocumentId = (int) $document->document_id;
        $document->delete();

        if ($shouldSyncGmDelete) {
            \App\Jobs\DeleteGmOrderDocumentJob::dispatch(
                $deletedDocumentId,
                $gmSyncOrder?->order_id ? (int) $gmSyncOrder->order_id : null
            );
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Файл удалён'
        ]);
    }
    
    /**
     * Скачивание документа
     */
    public function download(int $document_id)
    {
        $document = Document::findOrFail($document_id);
        $user = auth()->user();
        
        // Проверка доступа
        if ($document->documentable_type === Order::class) {
            $order = Order::find($document->documentable_id);
            if (!$user->hasRole('developer') && !$user->hasAccessToCity($order->address->city_id)) {
                abort(403);
            }
        } elseif ($document->documentable_type === CfmOperation::class) {
            if (! $user->hasAnyRole(['developer', 'investor', 'branch_head', 'regional_director', 'general_director', 'call_center', 'senior_dispatcher'])) {
                abort(403);
            }
            
            // Проверка доступа к городу кассовой операции
            $operation = CfmOperation::find($document->documentable_id);
            if ($operation && ! $user->hasRole('developer') && ! $user->hasRole('general_director') && ! $user->hasAllCitiesAccess() && ! $user->hasAccessToCity($operation->city_id)) {
                abort(403, 'Нет доступа к городу этой кассовой операции');
            }
        } elseif ($document->documentable_type === User::class) {
            if (!$user->hasAnyRole(['branch_head', 'regional_director', 'developer'])) {
                abort(403);
            }
            
            // Проверка доступа к городам сотрудника
            if (!$user->hasRole('developer')) {
                $employee = User::with('cities')->find($document->documentable_id);
                if ($employee) {
                    $employeeCityIds = $employee->cities->pluck('city_id')->toArray();
                    $userCityIds = $user->cities->pluck('city_id')->toArray();
                    $hasAccess = !empty(array_intersect($employeeCityIds, $userCityIds));
                    
                    if (!$hasAccess) {
                        abort(403, 'Нет доступа к городам этого сотрудника');
                    }
                }
            }
        } elseif ($document->documentable_type === Review::class) {
            if (! $user->hasAnyRole(['developer', 'call_center', 'senior_manager', 'branch_head', 'regional_director', 'general_director'])) {
                abort(403);
            }
        } else {
            abort(403, 'Неизвестный тип документа');
        }

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'Файл не найден');
        }
        
        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }
    
    /**
     * Просмотр документа (для изображений и PDF)
     */
    public function show(int $document_id)
    {
        $document = Document::findOrFail($document_id);
        $user = auth()->user();
        
        // Проверка доступа
        if ($document->documentable_type === Order::class) {
            $order = Order::find($document->documentable_id);
            if (!$user->hasRole('developer') && !$user->hasAccessToCity($order->address->city_id)) {
                abort(403);
            }
        } elseif ($document->documentable_type === CfmOperation::class) {
            if (! $user->hasAnyRole(['developer', 'investor', 'branch_head', 'regional_director', 'general_director', 'call_center', 'senior_dispatcher'])) {
                abort(403);
            }
            
            // Проверка доступа к городу кассовой операции
            $operation = CfmOperation::find($document->documentable_id);
            if ($operation && ! $user->hasRole('developer') && ! $user->hasRole('general_director') && ! $user->hasAllCitiesAccess() && ! $user->hasAccessToCity($operation->city_id)) {
                abort(403, 'Нет доступа к городу этой кассовой операции');
            }
        } elseif ($document->documentable_type === User::class) {
            if (!$user->hasAnyRole(['branch_head', 'regional_director', 'developer'])) {
                abort(403);
            }
            
            // Проверка доступа к городам сотрудника
            if (!$user->hasRole('developer')) {
                $employee = User::with('cities')->find($document->documentable_id);
                if ($employee) {
                    $employeeCityIds = $employee->cities->pluck('city_id')->toArray();
                    $userCityIds = $user->cities->pluck('city_id')->toArray();
                    $hasAccess = !empty(array_intersect($employeeCityIds, $userCityIds));
                    
                    if (!$hasAccess) {
                        abort(403, 'Нет доступа к городам этого сотрудника');
                    }
                }
            }
        } elseif ($document->documentable_type === Review::class) {
            if (! $user->hasAnyRole(['developer', 'call_center', 'senior_manager', 'branch_head', 'regional_director', 'general_director'])) {
                abort(403);
            }
        } else {
            abort(403, 'Неизвестный тип документа');
        }

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'Файл не найден');
        }
        
        $file = Storage::disk('local')->get($document->file_path);
        
        return response($file, 200)
            ->header('Content-Type', $document->file_mime)
            ->header('Content-Disposition', 'inline; filename="' . $document->file_name . '"');
    }
}
