<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RespondToInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invitation = $this->route('invitation');

        return $invitation
            && $this->user()
            && strcasecmp($invitation->email, $this->user()->email) === 0;
    }

    public function rules(): array
    {
        return [];
    }
}
