export type MediaItem = {
    id: number;
    filename: string;
    mime: string;
    size: number;
    width: number | null;
    height: number | null;
    display_width: number | null;
    display_height: number | null;
    alt: string | null;
    title: string | null;
    caption: string | null;
    preset: string | null;
    crop: CropBox | null;
    is_svg: boolean;
    url: string;
    thumb_url: string;
    original_url: string;
    variants_count: number;
    variants_size: number;
    created_at: string | null;
};

export type CropBox = { x: number; y: number; w: number; h: number; rotate?: number };

export type Preset = {
    label: string;
    width: number | null;
    height: number | null;
    svg: boolean;
};

export type MediaUsageItem = { label: string; field: string };

export const formatBytes = (bytes: number) =>
    bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
