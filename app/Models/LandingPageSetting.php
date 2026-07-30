<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class LandingPageSetting extends Model
{
    protected $fillable = [
        'website_name',
        'short_tagline',
        'footer_description',
        'office_email',
        'office_address',
        'copyright_text',
        'logo_path',
        'hero_badge',
        'hero_headline',
        'hero_highlight',
        'hero_subheadline',
        'hero_image_path',
        'hero_background_path',
        'primary_button_label',
        'primary_button_url',
        'secondary_button_label',
        'secondary_button_url',
        'cta_title',
        'cta_description',
        'cta_button_label',
        'cta_button_url',
    ];

    public static function defaults(): array
    {
        return [
            'website_name' => 'OMEO HR Suite',
            'short_tagline' => 'HRD Console',
            'footer_description' => 'Aplikasi Management Karyawan & HRIS SaaS terpercaya untuk mengelola rekrutmen, appraisal, absensi, dan operasional HRD Franchise Anda.',
            'office_email' => 'hrd@mykopio.com',
            'office_address' => 'Surabaya, Jawa Timur, Indonesia',
            'copyright_text' => '(c) 2024 OMEO HR Suite. All rights reserved.',
            'hero_badge' => 'Sistem HRD & Rekrutmen Terpadu',
            'hero_headline' => 'OMEO HR Suite',
            'hero_highlight' => 'HRD Console.',
            'hero_subheadline' => 'Sistem Management Karyawan modern. Kelola seleksi kandidat, pengingat probation, penilaian appraisal, presensi, hingga LMS dalam satu alur kerja terpusat untuk bisnis Franchise Anda.',
            'primary_button_label' => 'Masuk Dashboard',
            'primary_button_url' => '/dashboard',
            'secondary_button_label' => 'Lihat Lowongan',
            'secondary_button_url' => '/karir',
            'cta_title' => 'Siap Transformasi HR Anda?',
            'cta_description' => 'Tingkatkan efisiensi management karyawan di semua cabang Anda sekarang.',
            'cta_button_label' => 'Mulai Gratis Sekarang',
            'cta_button_url' => '/register',
        ];
    }

    public static function current(): self
    {
        if (! Schema::hasTable((new static())->getTable())) {
            return new static(static::defaults());
        }

        $setting = static::query()->first();

        if ($setting) {
            return $setting;
        }

        return new static(static::defaults());
    }

    public static function editable(): self
    {
        if (! Schema::hasTable((new static())->getTable())) {
            return new static(static::defaults());
        }

        return static::query()->firstOrCreate([], static::defaults());
    }

    public function value(string $key): ?string
    {
        $value = $this->getAttribute($key);

        return filled($value) ? (string) $value : (static::defaults()[$key] ?? null);
    }

    public function imageUrl(?string $path): ?string
    {
        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->imageUrl($this->logo_path);
    }

    public function getHeroImageUrlAttribute(): ?string
    {
        return $this->imageUrl($this->hero_image_path);
    }

    public function getHeroBackgroundUrlAttribute(): ?string
    {
        return $this->imageUrl($this->hero_background_path);
    }
}
