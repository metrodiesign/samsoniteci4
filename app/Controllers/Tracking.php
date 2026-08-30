<?php

namespace App\Controllers;

use App\Presentation\LegacyViewRenderer;
use App\Tracking\TrackingLookup;

final class Tracking extends BaseController
{
    public function form(): string
    {
        return $this->fromQuery('en');
    }

    public function formThai(): string
    {
        return $this->fromQuery('th');
    }

    public function legacyEnglish(): string
    {
        return $this->fromPost('en');
    }

    public function legacyThai(): string
    {
        return $this->fromPost('th');
    }

    public function english(string $trackId): string
    {
        return $this->fromSegment('en', $trackId);
    }

    public function thai(string $trackId): string
    {
        return $this->fromSegment('th', $trackId);
    }

    private function fromQuery(string $language): string
    {
        $canonical = $this->request->getGet('tracking_id');
        $value     = $canonical === null ? $this->request->getGet('searchText') : $canonical;

        return $this->fromValue($language, $value, $value !== null);
    }

    private function fromPost(string $language): string
    {
        $value = $this->request->getPost('searchText');

        return $this->fromValue($language, $value, $value !== null);
    }

    private function fromSegment(string $language, string $trackId): string
    {
        $prefix = $language === 'th' ? 'tracking-th/' : 'tracking/';
        $path   = ltrim($this->request->getUri()->getPath(), '/');
        $value  = str_starts_with($path, $prefix)
            ? rawurldecode(substr($path, strlen($prefix)))
            : $trackId;

        return $this->fromValue($language, $value, true);
    }

    private function fromValue(string $language, mixed $value, bool $requested): string
    {
        $trackId = is_string($value) ? $value : '';
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D', $trackId) !== 1) {
            $trackId = '';
        }

        $timeline = $trackId === '' ? [] : (new TrackingLookup(db_connect()))->timeline($trackId);

        return $this->render($language, $trackId, $timeline, $requested && $timeline === []);
    }

    /** @param list<array{status_id: int, status_name: string, status_name_th: string, occurred_at: string}> $timeline */
    private function render(string $language, string $trackId, array $timeline, bool $notFound): string
    {
        $renderer = new LegacyViewRenderer(oldValues: ['searchText' => $trackId]);
        if ($timeline === [] && ! $notFound) {
            $content = $renderer->render($language . '/track');
        } else {
            $records = array_map(static fn (array $event): array => [
                'action_id' => null,
                'update_id' => null,
                'status_name' => $event['status_name'],
                'status_name_th' => $event['status_name_th'],
                's_date' => $event['occurred_at'],
            ], $timeline);
            $content = $renderer->render($language . '/trackstatus', [
                'OrdersRecords' => LegacyViewRenderer::escapedRecords($records),
            ]);
        }

        return view('layout_public', [
            'language' => $language,
            'content' => $content,
        ]);
    }
}
