<?php

namespace App\Controllers;

use App\Master\MenuStore;
use App\Presentation\AdminLayoutPresenter;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */

    protected $helpers = ['form', 'url'];

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        // $this->session = service('session');
    }

    /**
     * @param array<string, mixed> $page reserved slots for later work: 'subtitle', 'actions'.
     *   Both 'subtitle' and 'actions' are trusted HTML (same contract as $content): layout.php
     *   echoes them raw without esc(), so any caller-supplied value MUST already be escaped by
     *   the caller. Never pass unescaped DB or request data into these slots.
     */
    protected function layout(string $title, string $content, array $page = [], string $profile = 'admin'): string
    {
        $sessionData = service('session')->get();
        $data = (new AdminLayoutPresenter(new MenuStore(db_connect())))->present(
            is_array($sessionData) ? $sessionData : [],
            $title,
            $content,
            $profile,
        );
        $data['subtitle'] = (string) ($page['subtitle'] ?? '');
        $data['actions'] = (string) ($page['actions'] ?? '');
        $data['accessDeniedProfile'] = false;

        return view($profile === 'order' ? 'layout_order' : 'layout', $data);
    }

    /**
     * Builds an escaped action anchor for the $page['actions'] slot. layout.php echoes
     * that slot raw, so escaping is centralised here (one place for master/menu/background).
     * Uses html-context esc(): it neutralises attribute breakout ("<>&) without turning the
     * path separators into &#x2F; the way esc(..., 'attr') would, keeping the href byte-identical.
     */
    protected function actionLink(string $url, string $label): string
    {
        return '<a href="' . esc($url) . '">' . esc($label) . '</a>';
    }
}
