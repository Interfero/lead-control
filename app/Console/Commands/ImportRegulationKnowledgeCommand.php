<?php

namespace App\Console\Commands;

use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportRegulationKnowledgeCommand extends Command
{
    protected $signature = 'knowledge:import-regulation
                            {--docx= : Путь к DOCX-файлу регламента (если не указан — используется готовый HTML)}
                            {--html= : Путь к HTML-файлу (по умолчанию database/seeders/data/regulation-kp-goroda.html)}';

    protected $description = 'Импортировать регламент КП в базу знаний с оглавлением и якорными ссылками';

    public function handle(): int
    {
        $htmlPath = $this->option('html') ?: database_path('seeders/data/regulation-kp-goroda.html');
        $docxPath = $this->option('docx');

        if ($docxPath) {
            $script = base_path('scripts/docx_to_regulation_html.py');
            if (!File::exists($script)) {
                $this->error("Скрипт конвертации не найден: {$script}");

                return self::FAILURE;
            }

            $command = sprintf(
                'python %s %s %s',
                escapeshellarg($script),
                escapeshellarg($docxPath),
                escapeshellarg($htmlPath)
            );
            $this->info('Конвертация DOCX → HTML…');
            exec($command, $output, $exitCode);
            if ($exitCode !== 0) {
                $this->error('Ошибка конвертации DOCX. Убедитесь, что установлен Python 3.');
                $this->line(implode(PHP_EOL, $output));

                return self::FAILURE;
            }
        }

        if (!File::exists($htmlPath)) {
            $this->error("HTML-файл не найден: {$htmlPath}");

            return self::FAILURE;
        }

        $html = File::get($htmlPath);
        $author = User::whereHas('roles', fn ($q) => $q->where('role_code', 'developer'))->first();
        $createdBy = $author?->user_id ?? 1;

        $article = KnowledgeArticle::firstOrNew(['article_title' => 'Регламент КП Города']);
        $isNew = !$article->exists;

        $article->fill([
            'article_content' => $html,
            'content_format' => 'html',
            'article_category' => 'Регламент',
            'sort_order' => 0,
            'is_published' => true,
            'visible_roles' => null,
        ]);

        if ($isNew) {
            $article->created_by = $createdBy;
        }

        $article->save();

        $this->info("Статья «{$article->article_title}» обновлена (ID: {$article->article_id}).");

        return self::SUCCESS;
    }
}
