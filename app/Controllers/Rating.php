<?php

namespace App\Controllers;

use App\Rating\RatingWorkflow;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Throwable;

final class Rating extends BaseController
{
    public function form(string $trackId): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D', $trackId) !== 1) {
            throw PageNotFoundException::forPageNotFound();
        }
        $order = db_connect()->table('request_order')
            ->select(['request_id', 'trackID'])
            ->where('trackID', $trackId)
            ->whereIn('action_status', [5, 7])
            ->get()
            ->getRowArray();
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('layout_public', [
            'title'   => 'Service rating',
            'content' => view('rating', [
                'requestId' => (int) $order['request_id'],
                'trackId'   => (string) $order['trackID'],
            ]),
        ]);
    }

    public function legacySubmit(): ResponseInterface
    {
        $requestId = $this->positiveInteger($this->request->getPost('requestId'));
        $trackId = $this->request->getPost('ratingTrackId');
        $comment = $this->request->getPost('ratingComment');
        $scores = [];
        foreach (['One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight'] as $suffix) {
            $score = $this->positiveInteger($this->request->getPost('rating' . $suffix));
            if ($score === null || $score > 5) {
                return $this->legacyFailure('Invalid data.', 400);
            }
            $scores[] = $score;
        }
        if ($requestId === null || ! is_string($trackId)
            || ($comment !== null && ! is_string($comment))) {
            return $this->legacyFailure('Invalid data.', 400);
        }
        $comment = is_string($comment) ? $comment : '';
        if (mb_strlen($comment) > 2000) {
            return $this->legacyFailure('Invalid data.', 400);
        }

        try {
            $result = (new RatingWorkflow(db_connect()))->submit(
                $requestId,
                $trackId,
                $scores,
                $comment,
            );
        } catch (InvalidArgumentException) {
            return $this->legacyFailure('Invalid data.', 400);
        } catch (Throwable $exception) {
            log_message('error', 'Legacy rating workflow unavailable: {exception}', ['exception' => $exception::class]);

            return $this->legacyFailure('Sorry an error occurred, processing was unsuccessful.', 500);
        }

        return $result === 'recorded'
            ? $this->response->setJSON(['status' => true, 'message' => '', 'code' => 0])
            : $this->legacyFailure('Invalid data.', 400);
    }

    public function submit(): ResponseInterface
    {
        $requestId = $this->positiveInteger($this->request->getPost('request_id'));
        $trackId   = $this->request->getPost('track_id');
        $comment   = $this->request->getPost('rating_comment');
        $scores    = [];
        for ($question = 1; $question <= 8; $question++) {
            $score = $this->positiveInteger($this->request->getPost('rating_' . $question));
            if ($score === null || $score > 5) {
                return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_rating']);
            }
            $scores[] = $score;
        }
        if (
            $requestId === null
            || ! is_string($trackId)
            || ! is_string($comment)
            || mb_strlen($comment) > 2000
        ) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_rating']);
        }

        try {
            $result = (new RatingWorkflow(db_connect()))->submit(
                $requestId,
                $trackId,
                $scores,
                trim($comment),
            );
        } catch (InvalidArgumentException) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'invalid_rating']);
        } catch (Throwable $exception) {
            log_message('error', 'Rating workflow unavailable: {exception}', ['exception' => $exception::class]);

            return $this->response->setStatusCode(503)->setJSON(['error' => 'rating_unavailable']);
        }

        return match ($result) {
            'recorded' => $this->response->setStatusCode(201)->setJSON(['status' => 'rating_recorded']),
            'duplicate' => $this->response->setStatusCode(409)->setJSON(['error' => 'rating_already_recorded']),
            default => $this->response->setStatusCode(404)->setJSON(['error' => 'rating_order_not_found']),
        };
    }

    private function legacyFailure(string $message, int $code): ResponseInterface
    {
        return $this->response->setJSON(['status' => false, 'message' => $message, 'code' => $code]);
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $integer === false ? null : (int) $integer;
    }
}
