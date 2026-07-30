<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\LandingPageSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function edit(): View
    {
        return view('hrd.landing_page.edit', [
            'setting' => LandingPageSetting::editable(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = LandingPageSetting::editable();
        $data = $request->validate([
            'website_name' => ['required', 'string', 'max:100'],
            'short_tagline' => ['nullable', 'string', 'max:160'],
            'footer_description' => ['nullable', 'string', 'max:1000'],
            'office_email' => ['nullable', 'email', 'max:255'],
            'office_address' => ['nullable', 'string', 'max:500'],
            'copyright_text' => ['nullable', 'string', 'max:255'],
            'hero_badge' => ['nullable', 'string', 'max:160'],
            'hero_headline' => ['nullable', 'string', 'max:160'],
            'hero_highlight' => ['nullable', 'string', 'max:160'],
            'hero_subheadline' => ['nullable', 'string', 'max:1200'],
            'primary_button_label' => ['nullable', 'string', 'max:80'],
            'primary_button_url' => ['nullable', 'string', 'max:255'],
            'secondary_button_label' => ['nullable', 'string', 'max:80'],
            'secondary_button_url' => ['nullable', 'string', 'max:255'],
            'cta_title' => ['nullable', 'string', 'max:160'],
            'cta_description' => ['nullable', 'string', 'max:1000'],
            'cta_button_label' => ['nullable', 'string', 'max:80'],
            'cta_button_url' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'hero_image' => ['nullable', 'image', 'max:4096'],
            'hero_background' => ['nullable', 'image', 'max:4096'],
        ]);

        foreach ([
            'logo' => 'logo_path',
            'hero_image' => 'hero_image_path',
            'hero_background' => 'hero_background_path',
        ] as $input => $column) {
            if ($request->hasFile($input)) {
                $data[$column] = $request->file($input)->store('landing-page', 'public');
            }
        }

        unset($data['logo'], $data['hero_image'], $data['hero_background']);

        $setting->fill($data);
        $setting->save();

        return back()->with('success', 'Konten landing page berhasil diperbarui.');
    }
}
