<?php

namespace App\Presentation;

use App\Master\MenuStore;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class AccessDeniedResponder
{
    public function respond(RequestInterface $request): ResponseInterface
    {
        if (! $this->prefersHtml($request)) {
            return service('response')
                ->setStatusCode(403)
                ->setContentType('application/json')
                ->setBody('{"error":"forbidden"}');
        }

        $session = service('session');
        $sessionData = $session->get();
        $data = (new AdminLayoutPresenter(new MenuStore(db_connect())))->present(
            is_array($sessionData) ? $sessionData : [],
            'Access Denied',
            view('access_denied'),
        );
        $data['subtitle'] = '';
        $data['actions'] = '';
        $data['accessDeniedProfile'] = true;

        return service('response')
            ->setStatusCode(403)
            ->setContentType('text/html')
            ->setBody(view('layout', $data));
    }

    private function prefersHtml(RequestInterface $request): bool
    {
        if (service('session')->get('isLoggedIn') !== true
            || strcasecmp(trim($request->getHeaderLine('X-Requested-With')), 'XMLHttpRequest') === 0) {
            return false;
        }

        $preferences = $this->mediaPreferences($request->getHeaderLine('Accept'));

        return $preferences !== null
            && $preferences['html'] !== null
            && $preferences['html'] > 0.0
            && ($preferences['json'] === null || $preferences['html'] > $preferences['json']);
    }

    /** @return array{html: ?float, json: ?float}|null */
    private function mediaPreferences(string $header): ?array
    {
        if ($header === '') {
            return null;
        }

        $preferences = ['html' => null, 'json' => null];
        foreach (explode(',', $header) as $range) {
            $parts = explode(';', trim($range));
            $media = strtolower(trim((string) array_shift($parts)));
            if ($media === '' || preg_match('/\A[a-z0-9!#$&^_.+*-]+\/[a-z0-9!#$&^_.+*-]+\z/D', $media) !== 1) {
                return null;
            }

            $quality = 1.0;
            $hasQuality = false;
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '' || preg_match('/\A([^=;\s"]+)=([^=;\s"]+)\z/D', $part, $match) !== 1) {
                    return null;
                }
                if (strtolower($match[1]) !== 'q') {
                    continue;
                }
                if ($hasQuality || preg_match('/\A(?:0(?:\.[0-9]{0,3})?|1(?:\.0{0,3})?)\z/D', $match[2]) !== 1) {
                    return null;
                }
                $hasQuality = true;
                $quality = (float) $match[2];
            }

            $representation = $media === 'text/html'
                ? 'html'
                : ($this->isJsonRange($media) ? 'json' : null);
            if ($representation === null) {
                continue;
            }
            if ($preferences[$representation] !== null) {
                return null;
            }
            $preferences[$representation] = $quality;
        }

        return $preferences;
    }

    private function isJsonRange(string $media): bool
    {
        return $media === 'application/json'
            || preg_match('/\Aapplication\/(?:[a-z0-9!#$&^_.+-]+|\*)\+json\z/D', $media) === 1;
    }
}
