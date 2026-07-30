<?php

namespace App\Services\Notifications;

use App\Mail\PlainTextNotificationMail;
use App\Models\Candidate;
use App\Models\HrNotification;
use App\Models\User;
use App\Support\MailConfiguration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class UnifiedNotificationService
{
    public function __construct(private readonly NotificationSettingsService $settingsService)
    {
    }

    public function notifyCandidateStatusChanged(Candidate $candidate, string $oldStatus, string $newStatus): void
    {
        if (! in_array($newStatus, [Candidate::STATUS_SHORTLISTED, Candidate::STATUS_ACCEPTED], true)) {
            return;
        }

        $eventKey = $newStatus === Candidate::STATUS_ACCEPTED
            ? 'candidate_status_accepted'
            : 'candidate_status_shortlisted';

        $email = trim((string) ($candidate->email ?: $candidate->user?->email ?: ''));
        if ($email === '') {
            return;
        }

        $payload = [
            'title' => '',
            'message' => '',
            'route' => route('dashboard'),
            'unique_key' => 'candidate-status-' . $candidate->id . '-' . $newStatus,
            'audience' => 'candidate',
            'variables' => [
                'name' => trim((string) ($candidate->full_name ?: $candidate->user?->name ?: 'Peserta')),
                'status_label' => Candidate::STATUS_LABELS[$newStatus] ?? ucfirst($newStatus),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
        ];

        $this->dispatch(
            eventKey: $eventKey,
            user: $candidate->user,
            email: $email,
            whatsappNumber: null,
            payload: $payload
        );
    }

    public function notifyEmployeeProfileChangeReviewed(User $user, string $title, string $message, int $changeRequestId, string $status): void
    {
        $payload = [
            'title' => $title,
            'message' => $message,
            'route' => route('employee-profile.show'),
            'unique_key' => 'profile-change-reviewed-' . $changeRequestId . '-' . $status,
            'audience' => 'employee',
            'variables' => [
                'name' => trim((string) ($user->name ?: 'Karyawan')),
                'message' => $message,
                'status' => $status,
            ],
            'meta' => [
                'change_request_id' => $changeRequestId,
                'status' => $status,
                'route' => route('employee-profile.show'),
            ],
        ];

        $this->dispatch(
            eventKey: 'employee_profile_change_reviewed',
            user: $user,
            email: trim((string) ($user->email ?: $user->employee?->email_private ?: '')),
            whatsappNumber: $this->resolveEmployeeWhatsapp($user),
            payload: $payload
        );
    }

    public function dispatch(string $eventKey, ?User $user, ?string $email, ?string $whatsappNumber, array $payload): void
    {
        $settings = $this->settingsService->resolve();
        $channels = (array) ($settings['events'][$eventKey] ?? []);
        $templates = (array) ($settings['templates'][$eventKey] ?? []);
        $variables = (array) ($payload['variables'] ?? []);
        $resolvedTitle = trim((string) ($payload['title'] ?? '')) ?: $this->interpolate((string) ($templates['title'] ?? ''), $variables);
        $resolvedMessage = trim((string) ($payload['message'] ?? '')) ?: $this->interpolate((string) ($templates['body'] ?? ''), $variables);
        $audience = trim((string) ($payload['audience'] ?? 'employee'));

        if ($resolvedTitle === '' && $resolvedMessage === '') {
            return;
        }

        if (($channels['internal'] ?? false) && $user && $audience !== 'candidate') {
            $this->sendInternalNotification($user, $eventKey, $resolvedTitle, $resolvedMessage, $payload);
        }

        if (($channels['email'] ?? false) && $email !== '') {
            $this->sendEmailNotification($email, $resolvedTitle, $resolvedMessage);
        }

        if (($channels['whatsapp'] ?? false) && $audience === 'employee' && $whatsappNumber !== '') {
            $this->sendWhatsappNotification($whatsappNumber, $resolvedTitle, $resolvedMessage, (array) ($settings['whatsapp'] ?? []));
        }
    }

    private function sendInternalNotification(User $user, string $eventKey, string $title, string $message, array $payload): void
    {
        if (! Schema::hasTable('hr_notifications')) {
            return;
        }

        HrNotification::query()->create([
            'user_id' => $user->id,
            'type' => $eventKey,
            'title' => $title,
            'body' => $message,
            'due_date' => now()->toDateString(),
            'is_read' => false,
            'unique_key' => (string) ($payload['unique_key'] ?? ($eventKey . '-' . $user->id . '-' . now()->timestamp)),
            'meta' => array_merge((array) ($payload['meta'] ?? []), [
                'route' => $payload['route'] ?? null,
            ]),
        ]);
    }

    private function sendEmailNotification(string $email, string $title, string $message): void
    {
        try {
            MailConfiguration::applyFromSettings();

            Mail::to($email)->send(new PlainTextNotificationMail($title, $message));
        } catch (\Throwable $exception) {
            Log::warning('Failed to send email notification', [
                'email' => $email,
                'title' => $title,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function sendWhatsappNotification(string $number, string $title, string $message, array $settings): void
    {
        $provider = trim((string) ($settings['provider'] ?? 'official_api'));
        $phoneNumberId = trim((string) ($settings['phone_number_id'] ?? ''));
        $accessToken = trim((string) ($settings['access_token'] ?? ''));
        $apiVersion = trim((string) ($settings['api_version'] ?? 'v23.0')) ?: 'v23.0';

        if ($provider !== 'official_api' || $phoneNumberId === '' || $accessToken === '') {
            Log::info('WhatsApp notification skipped because provider is not configured', [
                'number' => $number,
                'provider' => $provider,
            ]);
            return;
        }

        $normalizedNumber = $this->normalizeWhatsappNumber($number, trim((string) ($settings['default_country_code'] ?? '62')) ?: '62');
        if ($normalizedNumber === '') {
            Log::warning('WhatsApp notification skipped because number is invalid', ['number' => $number]);
            return;
        }

        $url = sprintf('https://graph.facebook.com/%s/%s/messages', $apiVersion, $phoneNumberId);

        try {
            Http::withToken($accessToken)
                ->acceptJson()
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $normalizedNumber,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => trim($title . "\n\n" . $message),
                    ],
                ])
                ->throw();
        } catch (\Throwable $exception) {
            Log::warning('Failed to send WhatsApp notification', [
                'number' => $normalizedNumber,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveEmployeeWhatsapp(User $user): string
    {
        $employeeNumber = trim((string) ($user->employee?->phone_number ?? ''));
        if ($employeeNumber !== '') {
            return $employeeNumber;
        }

        return trim((string) ($user->applicantProfile?->whatsapp ?? ''));
    }

    private function normalizeWhatsappNumber(string $number, string $defaultCountryCode): string
    {
        $digits = preg_replace('/\D+/', '', $number);
        $digits = is_string($digits) ? $digits : '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return $defaultCountryCode . substr($digits, 1);
        }

        return $digits;
    }

    private function interpolate(string $template, array $variables): string
    {
        if ($template === '') {
            return '';
        }

        return preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($variables) {
            $key = $matches[1] ?? '';

            return (string) ($variables[$key] ?? '');
        }, $template) ?? $template;
    }
}
