<?php

namespace App\Controllers;

use App\Contact\ContactSubmissionWorkflow;
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
        $rawSearch = $this->request->getGet('search');
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

        return view('layout', [
            'title'   => 'Contact messages',
            'content' => view('contact_listing', [
                'contacts' => $query->get()->getResultArray(),
                'search'   => $search,
            ]),
        ]);
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

        return redirect()->to($language === 'th' ? '/contact-th?submitted=1' : '/contact?submitted=1');
    }

    private function render(string $language): string
    {
        return view('layout', [
            'title'   => $language === 'th' ? 'ติดต่อเรา' : 'Contact us',
            'content' => view('contact', [
                'language'     => $language,
                'submissionId' => bin2hex(random_bytes(16)),
                'submitted'    => $this->request->getGet('submitted') === '1',
                'values'       => [],
                'errors'       => [],
            ]),
        ]);
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $errors
     */
    private function renderInvalid(string $language, array $fields, string $submissionId, array $errors): ResponseInterface
    {
        $html = view('layout', [
            'title'   => $language === 'th' ? 'ติดต่อเรา' : 'Contact us',
            'content' => view('contact', [
                'language'     => $language,
                'submissionId' => $submissionId,
                'submitted'    => false,
                'values'       => $fields,
                'errors'       => $errors,
            ]),
        ]);

        return $this->response->setStatusCode(422)->setBody($html);
    }

    private function invalidResponse(): ResponseInterface
    {
        return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_contact']);
    }
}
