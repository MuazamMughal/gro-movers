import { email, enhanceForm, maskPhone, usPhone } from '../lib/form';

/** Contact form island: validation, phone mask and AJAX submit. */
export default function mount(form: HTMLFormElement): void {
    const phone = form.elements.namedItem('phone') as HTMLInputElement | null;
    if (phone) maskPhone(phone);

    enhanceForm(form, {
        event: 'contact_submit',
        rules: {
            name: (v) => (v.length >= 2 ? null : 'Please enter your name.'),
            email,
            phone: usPhone(true),
            message: (v) => (!v ? 'Please write your message.' : v.length < 5 ? 'Please write a little more.' : null),
        },
    });
}
