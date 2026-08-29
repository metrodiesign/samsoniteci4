<?php

namespace App\Authentication;

use App\Presentation\LegacyViewRenderer;

final class ResetEmailRenderer
{
    public function render(ResetDelivery $delivery): string
    {
        return (new LegacyViewRenderer())->render('email/resetPassword', [
            'data' => [
                'name' => 'Customer',
                'message' => 'Please reset your password using the secure link below.',
                'reset_link' => esc($delivery->resetUrl(), 'attr'),
            ],
        ]);
    }
}
