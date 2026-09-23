<?php

namespace App\Services;

use App\Models\CfmOperation;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CfmOperationDocumentService
{
    private function safeOriginalFileName(UploadedFile $file): string
    {
        $name = basename(str_replace("\0", '', $file->getClientOriginalName()));
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file';
        }

        return mb_strlen($name) > 200 ? mb_substr($name, 0, 200) : $name;
    }

    /**
     * @throws ValidationException
     */
    public function assertMimeAllowed(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        $isJpeg = in_array($extension, ['jpg', 'jpeg'], true);

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

        if ($isJpeg && str_starts_with($mimeType, 'image/')) {
            return;
        }

        if (! in_array($mimeType, $allowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'file' => "Неподдерживаемый тип файла: {$mimeType} (расширение: {$extension}). Разрешённые форматы: jpg, jpeg, png, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv",
            ]);
        }
    }

    /**
     * Сохраняет файл и создаёт запись Document, привязанную к операции КФМ.
     *
     * @throws ValidationException
     */
    public function attachToOperation(CfmOperation $operation, UploadedFile $file, int $uploadedByUserId): Document
    {
        $this->assertMimeAllowed($file);

        $safeName = $this->safeOriginalFileName($file);
        $fileName = time().'_'.$safeName;
        $folder = "documents/cfm/{$operation->cfm_id}";

        try {
            $filePath = $file->storeAs($folder, $fileName, 'local');
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' => 'Ошибка при сохранении файла: ' . $e->getMessage(),
            ]);
        }

        try {
            $document = new Document([
                'documentable_type' => CfmOperation::class,
                'documentable_id' => $operation->cfm_id,
                'document_category' => 'general',
                'file_name' => $safeName,
                'file_path' => $filePath,
                'file_mime' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
            $document->uploaded_by = $uploadedByUserId;
            $document->save();

            return $document;
        } catch (\Throwable $e) {
            if (isset($filePath) && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
            throw $e;
        }
    }
}
