<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeArticle;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    /**
     * Список статей базы знаний
     */
    public function index()
    {
        $user = auth()->user();
        $userRoleCodes = $user->roles->pluck('role_code')->toArray();

        $articles = KnowledgeArticle::where('is_published', true)
            ->orderBy('article_category')
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($article) use ($userRoleCodes, $user) {
                if ($user->hasAnyRole(['regional_director', 'general_director', 'developer'])) return true;
                if (empty($article->visible_roles)) return true;
                return !empty(array_intersect($article->visible_roles, $userRoleCodes));
            })
            ->groupBy('article_category');
        
        $canEdit = $user->hasAnyRole(['regional_director', 'general_director', 'developer']);
        
        return view('knowledge.index', compact('articles', 'canEdit'));
    }
    
    /**
     * Просмотр статьи
     */
    public function show(int $article_id)
    {
        $article = KnowledgeArticle::where('is_published', true)
            ->findOrFail($article_id);
        
        $user = auth()->user();
        if (!$user->hasAnyRole(['regional_director', 'general_director', 'developer']) 
            && !empty($article->visible_roles)) {
            $userRoleCodes = $user->roles->pluck('role_code')->toArray();
            if (empty(array_intersect($article->visible_roles, $userRoleCodes))) {
                abort(403);
            }
        }
        
        $canEdit = $user->hasAnyRole(['regional_director', 'general_director', 'developer']);
        
        return view('knowledge.show', compact('article', 'canEdit'));
    }
    
    /**
     * Форма создания статьи
     */
    public function create()
    {
        // Получаем существующие категории для подсказки
        $categories = KnowledgeArticle::whereNotNull('article_category')
            ->distinct()
            ->pluck('article_category')
            ->toArray();
        
        return view('knowledge.create', compact('categories'));
    }
    
    /**
     * Сохранение новой статьи
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'article_title' => 'required|string|max:255',
            'article_content' => 'required|string',
            'content_format' => 'nullable|in:text,html',
            'article_category' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'visible_roles' => 'nullable|array',
        ]);
        
        $article = new KnowledgeArticle([
            'article_title' => $validated['article_title'],
            'article_content' => $validated['article_content'],
            'content_format' => $validated['content_format'] ?? 'text',
            'article_category' => $validated['article_category'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_published' => true,
            'visible_roles' => $validated['visible_roles'] ?? null,
        ]);
        $article->created_by = auth()->id();
        $article->save();
        
        return redirect()->route('information.show', $article->article_id)
            ->with('success', 'Статья создана');
    }
    
    /**
     * Форма редактирования статьи
     */
    public function edit(int $article_id)
    {
        $article = KnowledgeArticle::findOrFail($article_id);
        
        // Получаем существующие категории для подсказки
        $categories = KnowledgeArticle::whereNotNull('article_category')
            ->distinct()
            ->pluck('article_category')
            ->toArray();
        
        return view('knowledge.edit', compact('article', 'categories'));
    }
    
    /**
     * Обновление статьи
     */
    public function update(Request $request, int $article_id)
    {
        $article = KnowledgeArticle::findOrFail($article_id);
        
        $validated = $request->validate([
            'article_title' => 'required|string|max:255',
            'article_content' => 'required|string',
            'content_format' => 'nullable|in:text,html',
            'article_category' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer|min:0',
            'is_published' => 'nullable|boolean',
            'visible_roles' => 'nullable|array',
        ]);
        
        $article->update([
            'article_title' => $validated['article_title'],
            'article_content' => $validated['article_content'],
            'content_format' => $validated['content_format'] ?? $article->content_format ?? 'text',
            'article_category' => $validated['article_category'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $article->sort_order,
            'is_published' => $request->has('is_published'),
            'visible_roles' => $validated['visible_roles'] ?? null,
            'updated_by' => auth()->id(),
        ]);
        
        return redirect()->route('information.show', $article->article_id)
            ->with('success', 'Статья обновлена');
    }
    
    /**
     * Удаление статьи
     */
    public function destroy(int $article_id)
    {
        $article = KnowledgeArticle::findOrFail($article_id);
        $article->delete();
        
        return redirect()->route('information.index')
            ->with('success', 'Статья удалена');
    }
}
