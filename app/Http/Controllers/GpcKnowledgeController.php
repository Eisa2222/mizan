<?php

namespace App\Http\Controllers;

use App\Models\GpcArticle;
use Illuminate\Http\Request;

/**
 * GpcKnowledgeController
 * ──────────────────────
 * Browse and manage the authoritative knowledge base of:
 *   • نظام المنافسات والمشتريات الحكومية (م/128)
 *   • اللائحة التنفيذية (1242)
 *   • أدلة هيئة كفاءة الإنفاق
 *
 * Read-only browsing for everyone; future versions will allow editing
 * for org admins.
 */
class GpcKnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $query = GpcArticle::query();

        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('content', 'like', "%{$search}%")
                  ->orWhere('topic', 'like', "%{$search}%")
                  ->orWhere('article_label', 'like', "%{$search}%");
            });
        }

        $articles = $query->orderBy('source')->orderByRaw('CAST(article_number AS INTEGER)')->paginate(20);
        $sources = GpcArticle::SOURCES;
        $totalsBySource = GpcArticle::selectRaw('source, COUNT(*) as cnt')
            ->groupBy('source')
            ->pluck('cnt', 'source')
            ->toArray();

        return view('gpc-knowledge.index', compact('articles', 'sources', 'totalsBySource'));
    }
}
