<?php

namespace App\Controllers;

use App\Master\BackgroundStore;
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

    public function english(string $trackId): string
    {
        return $this->render('en', $trackId, (new TrackingLookup(db_connect()))->timeline($trackId));
    }

    public function thai(string $trackId): string
    {
        return $this->render('th', $trackId, (new TrackingLookup(db_connect()))->timeline($trackId));
    }

    private function fromQuery(string $language): string
    {
        $value   = $this->request->getGet('tracking_id');
        $trackId = is_string($value) ? trim($value) : '';
        $timeline = $trackId === '' ? [] : (new TrackingLookup(db_connect()))->timeline($trackId);

        return $this->render($language, $trackId, $timeline);
    }

    /** @param list<array{status_id: int, status_name: string, status_name_th: string, occurred_at: string}> $timeline */
    private function render(string $language, string $trackId, array $timeline): string
    {
        $suffix      = $language === 'th' ? '_th' : '';
        $backgrounds = new BackgroundStore(db_connect());

        if ($timeline === []) {
            $content = view('tracking_form', [
                'language'              => $language,
                'trackId'               => $trackId,
                'backgroundImage'       => $backgrounds->published('image_track_laptop' . $suffix),
                'backgroundImageMobile' => $backgrounds->published('image_track_mobile' . $suffix),
            ]);
        } else {
            $content = view('tracking_result', [
                'language'              => $language,
                'trackId'               => $trackId,
                'timeline'              => $timeline,
                'backgroundImage'       => $backgrounds->published('image_trackstatus_laptop' . $suffix),
                'backgroundImageMobile' => $backgrounds->published('image_trackstatus_mobile' . $suffix),
            ]);
        }

        return view('layout_public', [
            'title'    => $language === 'th' ? 'ติดตามงานซ่อม' : 'Repair tracking',
            'language' => $language,
            'content'  => $content,
        ]);
    }
}
