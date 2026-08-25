<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLegalPageRequest;
use App\Models\LegalPage;
use App\Support\SafeHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LegalPageController extends Controller
{
    public function __construct(private readonly SafeHtml $safeHtml) {}

    public function about(Request $request): Response
    {
        return $this->show($request, 'about');
    }

    public function terms(Request $request): Response
    {
        return $this->show($request, 'terms_of_service');
    }

    public function privacy(Request $request): Response
    {
        return $this->show($request, 'privacy_policy');
    }

    public function licenses(Request $request): Response
    {
        return $this->show($request, 'licenses');
    }

    public function update(UpdateLegalPageRequest $request, LegalPage $legalPage): RedirectResponse
    {
        $data = $request->validated();

        $legalPage->update([
            'title' => $data['title'],
            'content' => $this->safeHtml->clean($data['content']),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Page mise à jour.');
    }

    private function show(Request $request, string $pageType): Response
    {
        $page = LegalPage::query()
            ->where('page_type', $pageType)
            ->firstOrFail();

        return Inertia::render('Legal/Show', [
            'page' => [
                'id' => $page->id,
                'pageType' => $page->page_type,
                'title' => $page->title,
                'content' => $page->content,
                'safeContentHtml' => $this->safeHtml->clean($page->content),
                'updatedAt' => $page->updated_at->toDateString(),
            ],
            'canEdit' => $request->user()?->isSuperAdmin() ?? false,
        ]);
    }
}
