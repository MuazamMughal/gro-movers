export type SettingsFieldType =
    | 'text'
    | 'email'
    | 'tel'
    | 'url'
    | 'number'
    | 'textarea'
    | 'code'
    | 'color'
    | 'toggle'
    | 'select'
    | 'secret'
    | 'email_list'
    | 'hours'
    | 'social'
    | 'media';

export type SettingsField = {
    key: string;
    type: SettingsFieldType;
    label: string;
    help?: string;
    width?: 'half' | 'third';
    group?: string;
    options?: Record<string, string>;
    preset?: string;
    max?: number;
    required: boolean;
    is_set: boolean | null;
};

export type HoursRow = { days: string; hours: string };
