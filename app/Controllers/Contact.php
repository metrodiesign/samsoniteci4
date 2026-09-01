<?php

namespace App\Controllers;

use App\Contact\ContactSubmissionWorkflow;
use App\Presentation\LegacyViewRenderer;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Throwable;

final class Contact extends BaseController
{
    public function form(): string
    {
        return $this->render('en');
    }

    public function formThai(): string
    {
        return $this->render('th');
    }

    public function submit(): RedirectResponse|ResponseInterface
    {
        return $this->submitLanguage('en');
    }

    public function submitThai(): RedirectResponse|ResponseInterface
    {
        return $this->submitLanguage('th');
    }

    public function listing(): string
    {
        if ((int) service('session')->get('role') !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
        // CI3 names the box `searchText`; keep `search` working so existing links do not break.
        $rawSearch = $this->request->getMethod() === 'POST'
            ? $this->request->getPost('searchText')
            : ($this->request->getGet('searchText') ?? $this->request->getGet('search'));
        $search    = is_string($rawSearch) ? trim($rawSearch) : '';
        if (mb_strlen($search) > 128) {
            $search = '';
        }
        $query = db_connect()->table('contact')
            ->select(['id', 'fullname', 'email', 'samsoniteid', 'phone', 'detail', 'cdate'])
            ->orderBy('id', 'DESC')
            ->limit(100);
        if ($search !== '') {
            $query->groupStart()
                ->like('fullname', $search)
                ->orLike('email', $search)
                ->orLike('samsoniteid', $search)
                ->orLike('phone', $search)
                ->groupEnd();
        }

        $content = (new LegacyViewRenderer())->render('master/contactlist', [
            'contactRecords' => LegacyViewRenderer::escapedRecords($query->get()->getResultArray()),
            'searchText' => esc($search),
        ]);

        return $this->layout('Tracking : contact', $content, ['contentOwnsWrapper' => true]);
    }

    public function legacyListing(?string $rawOffset = null): string
    {
        $offset = is_string($rawOffset) && preg_match('/^[0-9]+$/D', $rawOffset) === 1
            ? (int) $rawOffset
            : 0;
        $rawSearch = $this->request->getMethod() === 'POST'
            ? $this->request->getPost('searchText')
            : null;
        $search = is_string($rawSearch) ? $rawSearch : '';
        $filterSearch = $search === '0' ? '' : $search;
        $query = $this->legacyContactQuery($filterSearch)
            ->orderBy('id', 'DESC')
            ->limit(50, $offset);
        $total = $this->legacyContactQuery($filterSearch)->countAllResults();
        $content = (new LegacyViewRenderer(null, $this->legacyPagination($offset, $total)))
            ->render('master/contactlist', [
                'contactRecords' => LegacyViewRenderer::escapedRecords($query->get()->getResultArray()),
                'searchText' => esc($search),
            ]);

        return $this->layout('Tracking : contact', $content, ['contentOwnsWrapper' => true]);
    }

    public function legacyControllerListing(?string $ignoredOffset = null): string
    {
        unset($ignoredOffset);

        return $this->legacyListing();
    }

    private function legacyContactQuery(string $search): \CodeIgniter\Database\BaseBuilder
    {
        $query = db_connect()->table('contact')
            ->select(['id', 'fullname', 'email', 'samsoniteid', 'phone', 'detail', 'cdate']);
        if ($search !== '') {
            $query->groupStart()
                ->like('fullname', $search)
                ->orLike('email', $search)
                ->orLike('samsoniteid', $search)
                ->orLike('phone', $search)
                ->groupEnd();
        }

        return $query;
    }

    private function legacyPagination(int $offset, int $total): string
    {
        $pages = (int) ceil($total / 50);
        if ($pages <= 1) {
            return '';
        }
        $firstUrl = base_url('contactListing/');
        $currentOffset = $offset > $total ? ($pages - 1) * 50 : $offset;
        $currentPage = intdiv($currentOffset, 50) + 1;
        $numberLinks = 5;
        $start = ($currentPage - $numberLinks) > 0 ? $currentPage - ($numberLinks - 1) : 1;
        $end = ($currentPage + $numberLinks) < $pages ? $currentPage + $numberLinks : $pages;
        $links = '<nav><ul class="pagination">';

        if ($currentPage > $numberLinks + 1) {
            $links .= '<li class="arrow"><a href="' . $firstUrl
                . '" data-ci-pagination-page="1" rel="start">First</a></li>';
        }
        if ($currentPage !== 1) {
            $previousOffset = $currentOffset - 50;
            $previousUrl = $previousOffset === 0 ? $firstUrl : $firstUrl . $previousOffset;
            $links .= '<li class="arrow"><a href="' . $previousUrl
                . '" data-ci-pagination-page="' . ($currentPage - 1)
                . '" rel="prev">Previous</a></li>';
        }
        for ($number = max(1, $start - 1); $number <= $end; $number++) {
            if ($number === $currentPage) {
                $links .= '<li class="active"><a href="#">' . $number . '</a></li>';
                continue;
            }
            $pageOffset = ($number - 1) * 50;
            $url = $pageOffset === 0 ? $firstUrl : $firstUrl . $pageOffset;
            $relation = $pageOffset === 0 ? ' rel="start"' : '';
            $links .= '<li><a href="' . $url . '" data-ci-pagination-page="' . $number . '"'
                . $relation . '>' . $number . '</a></li>';
        }
        if ($currentPage < $pages) {
            $nextOffset = $currentPage * 50;
            $links .= '<li class="arrow"><a href="' . $firstUrl . $nextOffset
                . '" data-ci-pagination-page="' . ($currentPage + 1)
                . '" rel="next">Next</a></li>';
        }
        if ($currentPage + $numberLinks < $pages) {
            $lastOffset = ($pages - 1) * 50;
            $links .= '<li class="arrow"><a href="' . $firstUrl . $lastOffset
                . '" data-ci-pagination-page="' . $pages . '">Last</a></li>';
        }

        return $links . '</ul></nav>';
    }

    private function submitLanguage(string $language): RedirectResponse|ResponseInterface
    {
        $fields = [];
        foreach (['fullname', 'email', 'phone', 'detail'] as $field) {
            $value = $this->request->getPost($field);
            if (! is_string($value)) {
                return $this->invalidResponse();
            }
            $fields[$field] = $value;
        }
        $submissionId = $this->request->getPost('submission_id');
        if (! is_string($submissionId)) {
            return $this->invalidResponse();
        }
        $recipient = ENVIRONMENT === 'production' ? null : 'contact@example.invalid';
        if ($recipient === null) {
            return $this->response->setStatusCode(503)->setJSON(['error' => 'contact_unavailable']);
        }

        $errors = [];
        try {
            $workflow = new ContactSubmissionWorkflow(db_connect(), service('encrypter'), $recipient);
            $errors   = $workflow->validate($fields);
            if ($errors === []) {
                $workflow->submit($submissionId, $fields);
            }
        } catch (InvalidArgumentException) {
            return $this->invalidResponse();
        } catch (Throwable $exception) {
            log_message('error', 'Contact workflow unavailable: {exception}', ['exception' => $exception::class]);

            return $this->response->setStatusCode(503)->setJSON(['error' => 'contact_unavailable']);
        }

        if ($errors !== []) {
            return $this->renderInvalid($language, $fields, $submissionId, $errors);
        }

        service('session')->setFlashdata('success', 'Message received');

        return redirect()->to($language === 'th' ? '/contact-th?submitted=1' : '/contact?submitted=1');
    }

    private function render(string $language): string
    {
        $submissionId = bin2hex(random_bytes(16));
        if ($this->request->getGet('submitted') === '1') {
            service('session')->setFlashdata('success', $language === 'th' ? 'รับข้อความแล้ว' : 'Message received');
        }
        $content = (new LegacyViewRenderer())->render($language . '/contact', [], [
            'submission_id' => $submissionId,
        ]);

        return view('layout_public', [
            'title'                => 'Samsonite',
            'language'             => $language,
            'legacyContactProfile' => true,
            'content'              => $content,
        ]);
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $errors
     */
    private function renderInvalid(string $language, array $fields, string $submissionId, array $errors): ResponseInterface
    {
        $messages = [];
        foreach (array_keys($errors) as $field) {
            $messages[$field] = match ([$language, $field]) {
                ['th', 'email'] => 'กรุณากรอกอีเมล',
                ['en', 'email'] => 'Please enter a valid email address',
                ['th', 'fullname'] => 'กรุณากรอกชื่อ-สกุล',
                ['th', 'phone'] => 'กรุณากรอกเบอร์โทรศัพท์',
                ['th', 'detail'] => 'กรุณากรอกรายละเอียด',
                default => 'Please enter a valid ' . $field,
            };
        }
        $content = (new LegacyViewRenderer(null, '', $messages, $fields))->render($language . '/contact', [], [
            'submission_id' => $submissionId,
        ]);
        $html = view('layout_public', [
            'title'                => 'Samsonite',
            'language'             => $language,
            'legacyContactProfile' => true,
            'content'              => $content,
        ]);

        return $this->response->setStatusCode(422)->setBody($html);
    }

    private function invalidResponse(): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_contact']);
    }
}
