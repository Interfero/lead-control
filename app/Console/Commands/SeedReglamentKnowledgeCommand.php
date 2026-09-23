<?php

namespace App\Console\Commands;

use App\Models\KnowledgeArticle;
use Illuminate\Console\Command;
use ZipArchive;

class SeedReglamentKnowledgeCommand extends Command
{
    protected $signature = 'knowledge:seed-reglament
                            {--docx= : Путь к файлу .docx}
                            {--force : Перезаписать существующую статью}';

    protected $description = 'Добавить регламент приёма заявок в базу знаний из .docx';

    public function handle(): int
    {
        $path = $this->option('docx') ?: database_path('seeders/assets/reglament_kp_goroda.docx');
        if (! is_readable($path)) {
            $this->error('Файл не найден: '.$path);

            return self::FAILURE;
        }

        $content = $this->extractDocxText($path);
        if ($content === '') {
            $this->error('Не удалось извлечь текст из документа.');

            return self::FAILURE;
        }

        $title = 'Регламент приёма заявок (города)';
        $existing = KnowledgeArticle::query()->where('article_title', $title)->first();
        if ($existing && ! $this->option('force')) {
            $this->warn('Статья уже существует (article_id='.$existing->article_id.'). Используйте --force для перезаписи.');

            return self::SUCCESS;
        }

        $article = KnowledgeArticle::query()->firstOrNew(['article_title' => $title]);
        $article->fill([
            'article_content' => $content,
            'article_category' => 'Регламент',
            'sort_order' => 0,
            'is_published' => true,
            'visible_roles' => ['call_center', 'senior_dispatcher', 'senior_manager', 'branch_head', 'order_manager'],
        ]);
        if (! $article->exists) {
            $article->created_by = 1;
        } else {
            $article->updated_by = 1;
        }
        $article->save();

        $this->info('Статья сохранена: article_id='.$article->article_id.' ('.mb_strlen($content).' символов)');

        return self::SUCCESS;
    }

    private function extractDocxText(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml) || $xml === '') {
            return '';
        }

        $text = preg_replace('/<w:tab\/>/', ' ', $xml);
        $text = preg_replace('/<\/w:p>/', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/TOC\s+\\\\[^\n"]*/u', '', $text);
        $text = preg_replace('/\bPAGEREF\s+[^\n]*/u', '', $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", trim($text));

        return $text;
    }
}
