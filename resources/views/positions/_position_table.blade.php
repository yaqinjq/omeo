<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs uppercase tracking-wide"
                style="background:#f8fafc;color:#64748b;border-bottom:2px solid #e2e8f0">
                <th class="px-4 py-3">Nama Posisi</th>
                @if($showDept)
                <th class="px-4 py-3">Departemen</th>
                @endif
                <th class="px-4 py-3 w-16 text-center">Level</th>
                <th class="px-4 py-3 w-24 text-center">Karyawan</th>
                <th class="px-4 py-3">Salary Range</th>
                <th class="px-4 py-3 w-24 text-center">Approval Lvl</th>
                <th class="px-4 py-3 w-20 text-center">Status</th>
                <th class="px-4 py-3 w-28 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($rows as $pos)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-medium text-gray-800">{{ $pos->name }}</td>
                @if($showDept)
                <td class="px-4 py-3 text-xs text-gray-500">
                    {{ $pos->department?->name ?? '—' }}
                </td>
                @endif
                <td class="px-4 py-3 text-center">
                    @if($pos->level)
                        <span class="text-xs px-2 py-0.5 rounded-full font-mono"
                              style="background:#f1f5f9;color:#475569">L{{ $pos->level }}</span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="text-sm font-semibold" style="color:#7c3aed">
                        {{ $pos->employees_count }}
                    </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600">
                    @if($pos->salary_min || $pos->salary_max)
                        {{ number_format($pos->salary_min ?? 0, 0, ',', '.') }}
                        –
                        {{ number_format($pos->salary_max ?? 0, 0, ',', '.') }}
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center text-xs text-gray-600">
                    {{ $pos->approval_level ?? '—' }}
                </td>
                <td class="px-4 py-3 text-center">
                    @if($pos->is_active)
                        <span class="text-xs px-2 py-0.5 rounded-full"
                              style="background:#dcfce7;color:#166534">Aktif</span>
                    @else
                        <span class="text-xs px-2 py-0.5 rounded-full"
                              style="background:#f3f4f6;color:#6b7280">Nonaktif</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <div class="flex gap-1.5 justify-end">
                        <button type="button"
                                @click="posModal.open({{ Js::from([
                                    'id'             => $pos->id,
                                    'name'           => $pos->name,
                                    'department_id'  => $pos->department_id,
                                    'level'          => $pos->level,
                                    'approval_level' => $pos->approval_level,
                                    'salary_min'     => $pos->salary_min,
                                    'salary_max'     => $pos->salary_max,
                                    'description'    => $pos->description,
                                    'is_active'      => (bool) $pos->is_active,
                                    'parent_position_id'         => $pos->parent_position_id,
                                    'representative_employee_id' => $pos->representative_employee_id,
                                    'employees'      => $pos->employees->map(fn ($e) => ['id' => $e->id, 'full_name' => $e->full_name])->values(),
                                ]) }})"
                                class="px-2 py-1 text-xs rounded"
                                style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe">
                            Edit
                        </button>
                        <form method="POST" action="{{ route('positions.destroy', $pos) }}"
                              onsubmit="return confirm('Hapus posisi {{ addslashes($pos->name) }}? Pastikan tidak dipakai karyawan.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="px-2 py-1 text-xs rounded"
                                    style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca">
                                Hapus
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ $showDept ? 8 : 7 }}"
                    class="px-4 py-10 text-center text-gray-400 text-sm">
                    Belum ada posisi
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
