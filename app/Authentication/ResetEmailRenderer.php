<?php

namespace App\Authentication;

final class ResetEmailRenderer
{
    public function render(ResetDelivery $delivery): string
    {
        return view('email/reset_password', [
            'resetUrl' => $delivery->resetUrl(),
        ]);
    }
}
