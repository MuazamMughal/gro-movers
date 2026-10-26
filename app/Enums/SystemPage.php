<?php

namespace App\Enums;

/**
 * Pages the site cannot work without. They are seeded, cannot be deleted,
 * and index pages are served at fixed paths regardless of their slug.
 */
enum SystemPage: string
{
    case Home = 'home';
    case About = 'about';
    case ServicesIndex = 'services_index';
    case AreasIndex = 'areas_index';
    case ReviewsIndex = 'reviews_index';
    case BlogIndex = 'blog_index';
    case Contact = 'contact';
    case Quote = 'quote';
    case ThankYou = 'thank_you';
    case Privacy = 'privacy';
    case Terms = 'terms';
    case AreaServiceTemplate = 'area_service_template';

    public function defaultTitle(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::About => 'About Us',
            self::ServicesIndex => 'Moving Services',
            self::AreasIndex => 'Service Areas',
            self::ReviewsIndex => 'Reviews',
            self::BlogIndex => 'Blog & Moving Guides',
            self::Contact => 'Contact Us',
            self::Quote => 'Get a Free Quote',
            self::ThankYou => 'Thank You',
            self::Privacy => 'Privacy Policy',
            self::Terms => 'Terms of Service',
            self::AreaServiceTemplate => 'Area × Service Template',
        };
    }

    public function defaultSlug(): string
    {
        return match ($this) {
            self::Home => 'home',
            self::About => 'about-us',
            self::ServicesIndex => 'services',
            self::AreasIndex => 'service-areas',
            self::ReviewsIndex => 'reviews',
            self::BlogIndex => 'blog',
            self::Contact => 'contact-us',
            self::Quote => 'get-a-quote',
            self::ThankYou => 'thank-you',
            self::Privacy => 'privacy-policy',
            self::Terms => 'terms',
            self::AreaServiceTemplate => 'area-service-template',
        };
    }

    /**
     * Fixed public path, or null when the page is served at "/{slug}".
     * The area×service template has no public URL of its own.
     */
    public function fixedPath(): ?string
    {
        return match ($this) {
            self::Home => '/',
            self::ServicesIndex => '/services',
            self::AreasIndex => '/service-areas',
            self::ReviewsIndex => '/reviews',
            self::BlogIndex => '/blog',
            default => null,
        };
    }

    public function hasPublicUrl(): bool
    {
        return $this !== self::AreaServiceTemplate;
    }

    /** Slug is locked when the path is fixed (changing it would do nothing). */
    public function slugLocked(): bool
    {
        return $this->fixedPath() !== null || ! $this->hasPublicUrl();
    }

    public function indexable(): bool
    {
        return $this !== self::ThankYou && $this->hasPublicUrl();
    }
}
