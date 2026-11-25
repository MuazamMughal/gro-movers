export type SeoValues = {
    meta_title: string | null;
    meta_description: string | null;
    canonical_url: string | null;
    og_title: string | null;
    og_description: string | null;
    og_image_id: number | null;
    robots_index: boolean;
    robots_follow: boolean;
    /** Third-party code for this page only (needs the custom_code permission to change). */
    head_code?: string | null;
    body_code?: string | null;
};

/** Props built by App\Support\SeoForm::props(). */
export type SeoProps = {
    values: SeoValues;
    fallbacks: { title: string | null; description: string | null; image_id: number | null };
    /** Absolute public URL, or null while the record has no URL yet. */
    url: string | null;
};

/** Shared Inertia prop `seoDefaults` (SeoForm::defaults()). */
export type SeoDefaults = {
    title_suffix: string;
    default_description: string | null;
    default_image_id: number | null;
    site_name: string;
    site_url: string;
    title_limit: number;
    description_limit: number;
};

export const emptySeo = (): SeoValues => ({
    meta_title: null,
    meta_description: null,
    canonical_url: null,
    og_title: null,
    og_description: null,
    og_image_id: null,
    robots_index: true,
    robots_follow: true,
    head_code: null,
    body_code: null,
});
