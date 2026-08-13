<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('tickets.create') ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'subject'       => ['required', 'string', 'max:255'],
            // A ticket with no description costs the IT team a round trip before
            // any work can start, so it is required rather than encouraged.
            'description'   => ['required', 'string', 'max:20000'],
            'company_id'    => ['required', 'integer', Rule::in($user->accessibleCompanyIds())],
            // Both fields are hidden inputs, so the location is re-checked against
            // the posted company: a ticket must never land on another entity's site.
            'location_id'   => ['nullable', 'integer',
                Rule::exists('oracle.locations', 'id')
                    ->where(fn ($q) => $q->where('company_id', $this->integer('company_id'))),
                Rule::in($user->accessibleLocationIds())],
            // No type_id: type has been dropped from the user journey and is left
            // null on new tickets, kept on the table only for historical records.
            'category_id'   => ['required', 'integer',
                Rule::exists('oracle.ticket_categories', 'id')->where(fn ($q) => $q->where('is_active', 1))],
            'priority_id'   => ['required', 'integer', Rule::exists('oracle.ticket_priorities', 'id')],
            // No department_id: TicketService takes it from the requester's record,
            // so it cannot be posted and cannot be wrong.
            'assigned_to'   => ['nullable', 'integer',
                Rule::exists('oracle.users', 'id')->where(fn ($q) => $q->where('is_active', 1))],
            'source'        => ['nullable', Rule::in(['WEB', 'EMAIL', 'PHONE', 'WALK_IN', 'MOBILE'])],
            'attachments'   => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_id.in'     => 'You do not have access to the selected company.',
            'location_id.in'    => 'You do not have access to the selected location.',
            'location_id.exists' => 'That location does not belong to the selected company.',
            'category_id.required' => 'Choose a category.',
            'attachments.*.max' => 'Each attachment must be 10 MB or smaller.',
        ];
    }
}
