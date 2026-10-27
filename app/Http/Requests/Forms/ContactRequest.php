<?php

namespace App\Http\Requests\Forms;

/** The public contact form: name, email, phone (optional) and message. */
class ContactRequest extends PublicFormRequest
{
    protected function formName(): string
    {
        return 'contact';
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'max:150', static::emailRule()],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            ...static::trackingRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'name.min' => 'Please enter your name.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'message.required' => 'Please write your message.',
            'message.min' => 'Please write a little more.',
        ];
    }
}
