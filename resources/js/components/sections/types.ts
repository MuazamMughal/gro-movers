export type FieldType =
    | 'text'
    | 'textarea'
    | 'richtext'
    | 'image'
    | 'link'
    | 'toggle'
    | 'select'
    | 'number'
    | 'repeater'
    | 'icon'
    | 'services'
    | 'areas'
    | 'reviews'
    | 'faqs'
    | 'code';

export type FieldDef = {
    key: string;
    type: FieldType;
    label: string;
    help?: string;
    required?: boolean;
    max?: number;
    min?: number;
    placeholder?: string;
    preset?: string;
    options?: Record<string, string>;
    fields?: FieldDef[];
    min_items?: number;
    max_items?: number;
    default?: unknown;
    /** Show only when another field in the same section has this value, e.g. { mode: 'picked' }. */
    show_if?: Record<string, string>;
};

export type SectionTypeDef = {
    type: string;
    label: string;
    description: string;
    icon: string;
    fields: FieldDef[];
    defaults: Record<string, any>;
};

/** Shape of SectionRegistry::forAdmin(). */
export type SectionSchema = {
    types: Record<string, SectionTypeDef>;
    icons: Record<string, string>;
};

export type PickerOption = { id: number; label: string; hint?: string | null };

/** Shape of SectionAdmin::options(). */
export type SectionOptions = Record<'services' | 'areas' | 'reviews' | 'faqs', PickerOption[]>;

export type SectionItem = {
    id?: number | null;
    type: string;
    data: Record<string, any>;
    is_visible: boolean;
    /** Client-only stable key for list rendering (not sent to the server). */
    _key?: string;
};

export type LinkValue = { label: string; url: string; new_tab?: boolean } | null;

export const uid = () => Math.random().toString(36).slice(2, 10);

export const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value ?? null));
