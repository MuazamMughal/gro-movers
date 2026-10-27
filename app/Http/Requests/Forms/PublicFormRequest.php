<?php

namespace App\Http\Requests\Forms;

use App\Support\Phone;
use App\Support\Seo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Shared behaviour of the public quote and contact forms.
 *
 * Without JS the form is a normal POST from a static page, which cannot show
 * session errors. A failed submission therefore gets a live page with the
 * errors and the form filled in again (status 422) instead of a redirect.
 * With JS (Accept: application/json) Laravel's usual 422 JSON is returned.
 */
abstract class PublicFormRequest extends FormRequest
{
    /** "quote" or "contact": picks the form shown on the error page. */
    abstract protected function formName(): string;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (filled($this->input('phone')) && Phone::e164($this->input('phone')) === null && ! $validator->errors()->has('phone')) {
                $validator->errors()->add('phone', 'Please enter a valid US phone number, e.g. (214) 555-0100.');
            }
        }];
    }

    /** The normalized phone number (+1XXXXXXXXXX), or null when none was given. */
    public function phoneE164(): ?string
    {
        return Phone::e164($this->input('phone'));
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }

        throw new HttpResponseException(response()->view('forms.invalid', [
            'form' => $this->formName(),
            'errors' => $validator->errors(),
            'old' => $this->except(['website', '_started']),
            'seo' => Seo::make(title: 'Please check your details', path: $this->path(), index: false),
        ], 422));
    }

    /** RFC check everywhere; the DNS (MX) lookup is skipped in tests. */
    public static function emailRule(): string
    {
        return app()->runningUnitTests() ? 'email:rfc' : 'email:rfc,dns';
    }

    /**
     * Hidden fields filled by the page script (page URL, original referrer, UTM tags).
     *
     * @return array<string, mixed>
     */
    public static function trackingRules(): array
    {
        return [
            'source_url' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'utm' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
