<?php

namespace App\Http\Controllers;

use App\Models\HrNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HrNotificationController extends Controller
{
    private const TYPE_LABELS = [
        'daily_worker_contract'       => 'Kontrak Karyawan',
        'appraisal_invitation'        => 'Appraisal',
        'appraisal_reminder'          => 'Reminder Appraisal',
        'appraisal_probation_reminder'=> 'Reminder Probation',
        'profile_change_request'      => 'Perubahan Profil',
        'probation_reminder'          => 'Reminder Probation',
        'probation_official_profile'  => 'Profil Resmi',
        'candidate_status_accepted'   => 'Status Kandidat',
        'candidate_status_shortlisted'=> 'Status Kandidat',
    ];

    private const TYPE_ICONS = [
        'daily_worker_contract'       => '📄',
        'appraisal_invitation'        => '⭐',
        'appraisal_reminder'          => '⏰',
        'appraisal_probation_reminder'=> '⏰',
        'profile_change_request'      => '👤',
        'probation_reminder'          => '📋',
        'probation_official_profile'  => '👤',
        'candidate_status_accepted'   => '🎉',
        'candidate_status_shortlisted'=> '✅',
    ];

    public function index(Request $request): View
    {
        if (! Schema::hasTable('hr_notifications')) {
            return view('hr_notifications.index', [
                'notifications'  => $this->emptyPaginator($request, 20),
                'typeLabels'     => self::TYPE_LABELS,
                'typeIcons'      => self::TYPE_ICONS,
                'typeCounts'     => collect(),
                'totalUnread'    => 0,
                'availableTypes' => collect(),
                'activeType'     => 'all',
                'unreadOnly'     => false,
                'dateFrom'       => null,
                'dateTo'         => null,
                'moduleWarning'  => 'Tabel notifikasi HR belum tersedia di environment ini.',
            ]);
        }

        $userId   = (int) $request->user()->id;
        $userRole = $request->user()->role ?? 'employee';
        $isHrd    = in_array($userRole, ['admin', 'finance'], true);

        $base = HrNotification::where(function ($q) use ($userId, $isHrd) {
            $q->where('user_id', $userId);
            if ($isHrd) {
                $q->orWhereNull('user_id');
            }
        })->latest('created_at');

        // Filter 1: unread only
        $unreadOnly = $request->boolean('unread');
        if ($unreadOnly) {
            $base->where('is_read', false);
        }

        // Filter 2: type tab
        $activeType = $request->input('type', 'all');
        if ($activeType !== 'all') {
            $base->where('type', $activeType);
        }

        // Filter 3: date range
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');
        if ($dateFrom) {
            $base->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $base->whereDate('created_at', '<=', $dateTo);
        }

        $notifications = $base->paginate(20)->withQueryString();

        // Unread count per type (untuk badge di tab)
        $typeCounts = HrNotification::where(function ($q) use ($userId, $isHrd) {
            $q->where('user_id', $userId);
            if ($isHrd) {
                $q->orWhereNull('user_id');
            }
        })
            ->where('is_read', false)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        $totalUnread = $typeCounts->sum();

        // Type yang pernah diterima user ini
        $availableTypes = HrNotification::where(function ($q) use ($userId, $isHrd) {
            $q->where('user_id', $userId);
            if ($isHrd) {
                $q->orWhereNull('user_id');
            }
        })
            ->distinct()
            ->pluck('type')
            ->filter(fn ($t) => isset(self::TYPE_LABELS[$t]))
            ->values();

        return view('hr_notifications.index', [
            'notifications'  => $notifications,
            'typeLabels'     => self::TYPE_LABELS,
            'typeIcons'      => self::TYPE_ICONS,
            'typeCounts'     => $typeCounts,
            'totalUnread'    => $totalUnread,
            'availableTypes' => $availableTypes,
            'activeType'     => $activeType,
            'unreadOnly'     => $unreadOnly,
            'dateFrom'       => $dateFrom,
            'dateTo'         => $dateTo,
            'moduleWarning'  => null,
        ]);
    }

    public function markRead(Request $request, int $id)
    {
        if (! Schema::hasTable('hr_notifications') || ! Schema::hasColumn('hr_notifications', 'is_read')) {
            return back()->with('error', 'Tabel notifikasi HR belum siap.');
        }

        HrNotification::query()
            ->where('id', $id)
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->update(['is_read' => true]);

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllRead(Request $request)
    {
        if (! Schema::hasTable('hr_notifications') || ! Schema::hasColumn('hr_notifications', 'is_read')) {
            return back()->with('error', 'Tabel notifikasi HR belum siap.');
        }

        HrNotification::query()
            ->where('is_read', false)
            ->where(function ($q) use ($request) {
                $q->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->update(['is_read' => true]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        if (! Schema::hasTable('hr_notifications')) {
            return response()->json(['count' => 0]);
        }

        $userId   = (int) $request->user()->id;
        $isHrd    = in_array($request->user()->role ?? 'employee', ['admin', 'finance'], true);

        $count = HrNotification::where(function ($q) use ($userId, $isHrd) {
            $q->where('user_id', $userId);
            if ($isHrd) {
                $q->orWhereNull('user_id');
            }
        })->where('is_read', false)->count();

        return response()->json(['count' => $count]);
    }

    public function preview(Request $request): JsonResponse
    {
        if (! Schema::hasTable('hr_notifications')) {
            return response()->json(['notifications' => [], 'total_unread' => 0]);
        }

        $userId = (int) $request->user()->id;
        $isHrd  = in_array($request->user()->role ?? 'employee', ['admin', 'finance'], true);

        $baseQuery = HrNotification::where(function ($q) use ($userId, $isHrd) {
            $q->where('user_id', $userId);
            if ($isHrd) {
                $q->orWhereNull('user_id');
            }
        })->where('is_read', false);

        $notifications = (clone $baseQuery)
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($n) => [
                'id'      => $n->id,
                'type'    => $n->type,
                'icon'    => self::TYPE_ICONS[$n->type]  ?? '🔔',
                'label'   => self::TYPE_LABELS[$n->type] ?? $n->type,
                'title'   => $n->title,
                'body'    => $n->body,
                'route'   => $n->meta['route'] ?? null,
                'time'    => $n->created_at->diffForHumans(),
                'is_read' => $n->is_read,
            ]);

        $total = (clone $baseQuery)->count();

        return response()->json([
            'notifications' => $notifications,
            'total_unread'  => $total,
        ]);
    }

    private function emptyPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, $request->integer('page', 1), [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
