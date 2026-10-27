<?php

namespace App\Http\Requests\Forms;

use App\Support\SiteTime;
use Illuminate\Validation\Rule;

/**
 * The public quote form: exactly Full Name, Email, Phone, Moving From, Moving To,
 * Moving Date and Type of Move (plus hidden Places details and tracking fields).
 */
class QuoteRequest extends PublicFormRequest
{
    protected function formName(): string
    {
        return 'quote';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => trim((string) $this->input('full_name')),
            'email' => strtolower(trim((string) $this->input('email'))),
            'from_state' => strtoupper((string) $this->input('from_state')) ?: null,
            'to_state' => strtoupper((string) $this->input('to_state')) ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = SiteTime::local(now())->toDateString();
        $latest = SiteTime::local(now())->addMonths(18)->toDateString();

        $address = fn (string $p) => [
            "{$p}_address" => ['required', 'string', 'min:5', 'max:255'],
            "{$p}_city" => ['nullable', 'string', 'max:100'],
            "{$p}_state" => ['nullable', 'string', 'size:2', 'alpha'],
            "{$p}_zip" => ['nullable', 'regex:/^\d{5}(-\d{4})?$/'],
            "{$p}_lat" => ['nullable', 'numeric', 'between:-90,90'],
            "{$p}_lng" => ['nullable', 'numeric', 'between:-180,180'],
        ];

        return [
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'max:150', static::emailRule()],
            'phone' => ['required', 'string', 'max:30'],
            ...$address('from'),
            ...$address('to'),
            'move_date' => ['required', 'date_format:Y-m-d', "after_or_equal:{$today}", "before_or_equal:{$latest}"],
            'move_type_id' => ['required', 'integer', Rule::exists('move_types', 'id')->where('is_active', true)],
            ...static::trackingRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Please enter your full name.',
            'full_name.min' => 'Please enter your full name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'phone.required' => 'Please enter your phone number.',
            'from_address.required' => 'Where are you moving from?',
            'from_address.min' => 'Please enter a full address, city or ZIP code.',
            'to_address.required' => 'Where are you moving to?',
            'to_address.min' => 'Please enter a full address, city or ZIP code.',
            'move_date.required' => 'Please choose your moving date.',
            'move_date.after_or_equal' => 'The moving date cannot be in the past.',
            'move_date.before_or_equal' => 'Please choose a date within the next 18 months.',
            'move_date.date_format' => 'Please choose your moving date.',
            'move_type_id.required' => 'Please choose the type of move.',
            'move_type_id.exists' => 'Please choose the type of move.',
        ];
    }
}
